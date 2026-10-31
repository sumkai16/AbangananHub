# Normalize `reservations` into reservations + rentals

**Status: superseded Oct 30 2026** by `plans/landlord-tenants-contracts-direct-payments.md` (contracts table, escrow retired first). Kept for its blast-radius numbers.

## Goal
Split the 31-column `reservations` table by lifecycle stage so a reservation is only the request, and a
`rentals` row exists only for accepted bookings. Payments, deposits, reminders and ratings point at the rental.

## Blast radius (measured Oct 17 2026)
| Area | Files touching moved columns |
|---|---|
| `app/` | 32 (heaviest: `Reservation.php` 96 refs, `EscrowVerify` 72, `ProcessMoveInDeadlines` 33, `ReservationResource` 20, `EscrowScenarios` 18) |
| `resources/views` | 15 |
| `database/` (migrations, seeders, factories) | 9 |
| `tests/` | 7 |
| Migrations with a `reservation_id` FK | 7 |

## Target tables
- `reservations`: reservation_id, tenant_id, unit_id, reservation_date, rental_status (request states only), rejection_reason, remarks. Drop `property_id` (derive via unit) and dead `duration_of_stay`.
- `rentals`: rental_id, reservation_id UNIQUE, agreed_monthly_rent, rent_due_day, target/actual move-in and move-out dates, occupants_count, agreement_terms_notes, status.
- `lease_agreements`: rental_id UNIQUE, lease_snapshot, lease_file_*, agreed_at/ip, landlord/tenant T&C times.
- `move_in_handovers`: rental_id UNIQUE, handover_* fields, keys_turned_over_at, move_in_deadline_at, dispute fields, last reminder.
- Re-point FKs: payments, deposit_charges, rent_reminders, tenant_ratings -> rental_id.

## Open decision (answer before Phase 1)
When is the rental row created: at acceptance (`Pending Rental Agreement`) or at lease signing? Recommended: acceptance,
because the lease and negotiated rent already exist then. Rental statuses become Pending Agreement / Signed / Occupied / Completed.

## Phases (each ends with a green test run; no phase leaves the app broken)
1. **Additive migration.** Create the 3 new tables and nullable `rental_id` columns on the 4 child tables. Idempotent backfill command (`rentals:backfill`) that copies every existing row. Dry-run mode and row-count checks. Back up first with `mysqldump`.
2. **Model layer.** Add `Reservation::rental()`, `Rental::lease()`, `Rental::handover()`. Keep the old attribute names working through accessors that delegate to the new relations, so views and API resources keep rendering unchanged. Move `TERMINAL_STATUSES`, `isTurnoverClock()`, `monthlyRent()`, `rentDueDay()`, `leaseStatus()` to `Rental`.
3. **Write paths.** Switch writers to the new tables: `StoreReservationRequest`, tenant/landlord/admin/API `ReservationController`, `WalkInTenantController`, `LeaseController` (both), `HandoverController` (web + API), `ReservationObserver`.
4. **Read paths and commands.** `ProcessMoveInDeadlines`, `ProcessRentReminders`, `EscrowVerify`, `EscrowScenarios`, `WalkInScenarios`, `BuildsEscrowFixtures`, dashboards, `ReservationResource`, 15 views. Eager-load to avoid N+1 (RULES.md).
5. **Tests.** Update the 7 test files, add backfill and relation tests, run the full suite.
6. **Manual verification by Axcee** in-browser: inquiry -> negotiate -> lease -> sign -> handover -> move-in -> dispute -> complete, as tenant, landlord and admin, on a 375px phone width.
7. **Drop migration.** Remove the moved columns from `reservations` only after Phase 6 passes. Keep `down()` able to restore them from the new tables.

## Risks
| Risk | Mitigation |
|---|---|
| Money/escrow logic reads moved columns (`EscrowVerify`) | Do escrow in its own pass, compare ledger totals before and after on a DB copy |
| Two clocks on one deadline column | Keep the single `move_in_deadline_at` on `move_in_handovers`; port `isTurnoverClock()` unchanged |
| Local PHP is 8.2, project requires 8.4 | Install PHP 8.4 first so tests run on the real target |
| Concurrent writes during backfill | Run in a transaction per reservation; app in maintenance mode for the final cutover |
| API consumers (mobile) rely on resource shape | Keep `ReservationResource` output keys identical; add a contract test |

## Estimate
| | Estimate |
|---|---|
| Agent working time | roughly 5 to 8 hours across 2 to 3 sessions |
| Calendar time incl. manual checks | 2 to 4 days |
| Tokens (rough, uncertain) | about 400k to 700k new tokens; 2M to 4M processed including cached re-reads |
| Cheaper variant: rentals + lease only, leave handover | about 60% of the above |

Models per CLAUDE.md: plan with Opus 5, implement with Sonnet 5. Phases 3 and 4 can be split across agents by file
ownership once the model API from Phase 2 is fixed.
