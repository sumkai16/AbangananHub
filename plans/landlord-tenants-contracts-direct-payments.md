# Landlord Tenants + Contracts pages (before the demo); normalization after

**Status: phases 1–4 implemented Oct 31 2026 on branch `emman`, pending Axcee's manual verification (checklist under Verification). "After the demo" not started.** Planned with Opus 5; implement with Sonnet 5.
Visual version (ERD, mockups): https://claude.ai/artifact/S7xbx1PWCDxXCExkLbCZJu (private to Axcee's account).
Supersedes all of `plans/normalize-reservations-into-rentals.md` (moved to "After the demo" below).

## Context

Axcee wants the landlord side to work like a property-management tool: add a tenant first (Pending,
with a Tenant ID), then bind them to a unit with a contract (Active; unit Reserved before move-in,
Occupied after), and view, edit, message, delete, renew and end from modals.

**Direct payments are already on `main`** (`plans/direct-payments-record-and-confirm.md`, merged
Oct 25): tenants pay the landlord by the QR shared in chat and report it; the landlord confirms. PayMongo
stays in the code behind an admin switch, **off by default**. Decided Oct 30 2026: keep it that way until
after the demo (a teammate chose the switch on purpose; deleting it adds nothing users can see). A first
attempt at deleting PayMongo was started and fully undone the same day.

Decisions confirmed with Axcee: email invite for tenants added by hand; renewal may change rent from a
chosen month; landlord confirms move-in; walk-in wizard retired; activation before full payment is allowed
with a warning. Defaults: tenant status derived, never stored; delete never destroys payment history
(archive instead); only landlord-managed tenants editable; an email that already has an account is refused
in Add Tenant.

## Status vocabulary (no new reservation statuses, no ENUM change)

| Contract shows | `rental_status` | Unit |
|---|---|---|
| Awaiting payment | `Rental Agreement Signed`, `move_in_paid_at` null | Available (unchanged) |
| Active · awaiting move-in | `Rental Agreement Signed` + `move_in_paid_at` set, or `Reserved` (added by hand, future move-in) | **Reserved** |
| Active | `Occupied` | Occupied |
| Ends in N days / Past end date (badges) | `Occupied` near or past `target_move_out_date` | Occupied |
| Ended | `Completed` | Available |

**Activation = the landlord confirming the move-in money** (a tenant's report, or "Record move-in payment"
for any amount, which already warns when short). That is the moment the unit becomes Reserved, so the
online flow gains no new button. Either party's "Confirm move-in" (existing `markOccupied`) makes it Occupied.

Tenant status (derived): **Pending** = no contract; **Active** = a Signed, Reserved or Occupied contract with
this landlord; **Former** = only ended contracts.

## Phase 1 — Data
- `landlord_tenants`: landlord_id, tenant_id, tenant_number, archived_at; UNIQUE(landlord, tenant),
  UNIQUE(landlord, number). `code()` → `TN-0001`. Backfill: walk-ins (`created_by_landlord_id`) + tenants
  with a Signed / Reserved / Occupied / Completed reservation, numbered by first contract date.
- `lease_renewals`: reservation_id, new_move_out_date, new_monthly_rent, rent_effective_from, renewed_by.
- `ReservationObserver` keeps the roster in sync (`LandlordTenant::ensureFor()` on Signed / Reserved / Occupied).
- Unit becomes Reserved when move-in money is settled (`move_in_paid_at` set, in `PaymentClaims`),
  released by the existing cancel/reject paths (`releaseUnitIfUnclaimed`).
- SCHEMA.md (`SchemaDocTest`).

## Phase 2 — Contracts page (new sidebar item)
- `LeaseContractCreator` lifted unchanged from `WalkInTenantController::createWalkIn()` (unit lock,
  double-booking 409, Reserved/Occupied by date, lease snapshot, lease file, `MoveInPaymentRecorder`);
  the mobile walk-in endpoint calls it too.
- Index grouped by property; stat cards Active · Awaiting move-in · Ending in 60 days · Lease missing.
- Add contract modal (tenant from roster → property → unit → dates → rent, deposit, due day, occupants,
  terms, lease file, payment received). View modal (tenant, emergency contact, unit, dates, rent, deposit,
  due day, status, renewals) with Confirm move-in, End (Occupied) / Cancel (Reserved), Open ledger, Renew.

## Phase 3 — Tenants page (rework `landlord/tenants/index`)
- Roster-based table/cards: Tenant ID · name · contact · current unit · status · view/edit/message/delete.
- Add Tenant modal (name, email, mobile, emergency contact, ID) → walk-in style user + roster row + invite.
- Invite: `passwords.invites` broker (7 days); accepting sets a password, `is_walk_in = false`, active.
- Delete: refused with a live contract; hard delete only with zero reservations; otherwise archive.
- Retire the walk-in wizard (routes, view, web controller); property page links to Add contract.

## Phase 4 — Renewal
Renew modal: new end date, optional new rent, "new rent starts" month. `Reservation::rentFor($period)` used
by `RentLedger::periods()`, `RentPaymentRecorder`, `RentPaymentAllocator`, rent-claim default amount and the
(switched-off) PayMongo rent checkout.

Each phase: tests, full suite green, `npm run build`, Pint, `impeccable` on changed views, CHANGELOG entry,
then Axcee checks it in a browser at 375px and desktop before the next phase.

## After the demo (not started; needs the teammate's agreement)
- Delete PayMongo, escrow clocks, disputes and payouts instead of switching them off.
- Normalize: split `reservations` into reservations (request) + contracts + lease_agreements; re-point
  payments, claims, deposit charges, reminders, ratings, renewals and the roster to contracts; payment_voids;
  landlord payment methods. Target ERD in the artifact above.

## Verification
Tests: TN numbering per landlord and under concurrency; status Pending → Active → Former; unit Reserved when
move-in money is confirmed, Available again on cancel; contract create future → Reserved, today → Occupied,
double booking 409, cross-landlord 403; delete refused / hard / archived with payments kept; invite accept;
renewal rent applies from `rent_effective_from` only.
Manual (Axcee): add tenant → Pending → add contract → Active, unit Reserved → confirm move-in → Occupied →
renew with new rent → ledger correct → end → Ended, tenant Former → delete (archived). Online: inquiry →
agreement → sign → report payment → landlord confirms → unit Reserved → confirm move-in → Occupied.
