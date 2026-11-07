# Merge Tenants, Contracts and Policies into one Tenants page

**Status: implemented Nov 1 2026 on branch `merge/emman`, pending Axcee's manual verification (checklist at the end).** Planned with Opus 5; implemented with Sonnet 5. Suite: 1100 passed.
Mockups: https://claude.ai/artifact/276HiZxWhBs6cxoSxg4VGV (private to Axcee). Chosen: **mockup 1**
(one list, filtered by stage) **plus the "Waiting for a unit" box from mockup 3**.

## Why

Three sidebar items list the same rentals:

| Page | One row is | Problem |
|---|---|---|
| Tenants (`landlord/tenants/index`) | a roster entry (`landlord_tenants`) | Status (Active / Pending / Former) is derived from the contracts, so it repeats Contracts |
| Contracts (`landlord/contracts/index`) | a reservation in Signed / Reserved / Occupied / Completed | Same people as Tenants, plus dates and rent |
| Policies › Sent & signed (`leases/index?tab=sent`) | a reservation with a `lease_snapshot` | Same rows as Contracts, sorted by agreement |
| Policies › By property | a setting | Not a duplicate. Stays, but leaves the sidebar |

After: the sidebar has one **Tenants** item. Lease policies is a button on that page.

## Decisions

1. **One row = one tenant** (a `landlord_tenants` row), with their *current* contract on it. Current = newest
   reservation in `LandlordTenant::ACTIVE_STATUSES` (Signed, Reserved, Occupied); if none, the newest Completed
   one, for Former tenants. Older or parallel contracts are listed inside the modal.
2. **Unsigned agreements stay on Reservations.** A reservation in `Pending Rental Agreement` is not on the
   roster yet (`ReservationObserver` adds the tenant at Signed). That is the request pipeline's
   "Agreement" stage (`ReservationController::STAGES`), so it stays there. The Tenants page shows a small
   link: "2 agreements waiting for a signature → Reservations". **This differs from the mockup**, which
   drew Ana "Not signed" on the Tenants list.
3. **Cancelled / Rejected rows from Sent & signed are dropped.** They are already in Reservations › History.
4. **Policy setup keeps its page** (`landlord.leases.index`, By property view only) and its URLs. It loses
   its sidebar item and gains a back link "‹ Tenants". The Sent & signed tab and `sentLeases()` are deleted.
5. **Route names stay** (`landlord.tenants.*`, `landlord.contracts.*` for store / show / renew,
   `landlord.leases.*`), so the mobile API and forms keep working. Only the two list pages change.

## The page (`landlord/tenants/index`)

**Header:** title "Tenants"; buttons **Lease policies** (outline, → `landlord.leases.index`), **Export**
(outline), **+ Add tenant** (coral). On phones: Add tenant full width, Policies beside it, Export in the
`⋯` overflow.

**Waiting for a unit box** (only when Pending tenants exist and the chip is All): amber dashed box,
"Waiting for a unit · N", one line per tenant with **Give contract** (opens the Add contract modal with that
tenant picked: `?new=1&tenant=<landlord_tenant_id>`, already supported). Max 3 lines, then "See all N" sets the
No contract chip.

**Search + filters:** search (name, email, TN code, unit label), property select, and stage chips that
replace both stat-card rows:

| Chip | Rows | Source |
|---|---|---|
| All | every visible roster row | default |
| Living there | current contract `Occupied` | |
| Moving in | current contract `Rental Agreement Signed` or `Reserved` | `contractStatus()` gives Awaiting payment / Awaiting move-in as the pill |
| Ending soon | `Occupied` and ends within `rentals.renewal_window_days`, or past end date | shown only when count > 0 |
| No agreement | current contract with `leaseStatus() === 'missing'` | shown only when count > 0 (was the "Agreement missing" stat) |
| No contract | `statusFrom()` = Pending | |
| Former | `statusFrom()` = Former | |

Chip counts come from the same in-memory collection (`TenantController::rows()` already loads every
reservation per tenant), so no extra queries.

**Phone (below `lg`), card per tenant:** name · TN code · property · unit, status pill top right; bottom line
"Until Mar 31, 2027" or "Moving in Nov 15" on the left, rent or the one action on the right (**Renew** when
ending soon, **Give contract** when none). The whole card opens the modal. 44px tap targets, 16px search input.

**Desktop table:** Tenant (name + TN code, "added by you" note) · Unit (property · unit) · Lease (start → end)
· Rent (right, tabular) · Agreement (Signed / Uploaded / Missing pill + "Docs 2/3" when documents exist) ·
Status (`contractStatus()` pill, or No contract / Former) · action (Open / Renew / Give contract) plus the
existing Message icon with its concern badge.

**Empty states:** no tenants yet ("Add a tenant, then give them a contract"), and no match for the filters
(with Clear).

## One modal

**As built:** `TenantController::show` returns the *contract* modal (`contracts/partials/_details`) for a tenant's current contract, with the tenant's contact, Resend invite and an "Other contracts with you" list added to it, and the old tenant-details partial only when there is no contract. One URL, one modal, no nested modals. Both `contracts.show` and `tenants.show` share `ContractController::detailsData()`. The sketch below is the original idea.

Clicking a row opens `open-tenant` with `landlord.tenants.show`. That partial (`tenants/partials/_details`)
becomes the single modal:

1. Tenant block (contact, emergency contact, TN code, Edit / Invite / Message), as now.
2. **Current contract**: render the existing `contracts/partials/_details` body inline (Confirm move-in,
   End / Cancel, Renew, Open ledger, held money, View agreement, rating form). Pass the same variables
   `ContractController::show()` builds; extract that into a private `contractViewData(Reservation)` used by both.
3. **Other contracts**: a short list (unit, dates, status); each opens the contract modal as today
   (`open-contract` → `landlord.contracts.show`). Keep `_view-modal` for that.
4. No contract: a **Give contract** button (exists today as "Add contract").

`?open=<reservation_id>` (used by renew, rating, lease and payment redirects) keeps opening the contract
modal directly, now on the Tenants page.

## Redirects and links

- `GET /landlord/contracts` → redirect to `landlord.tenants.index`, keeping `open`, `rate`, `new`, `tenant`,
  `search`, `property`; map `status`: `active`→`living`, `awaiting`→`moving`, `ended`→`former`,
  `lease_missing=1`→`no-agreement`. Keep the route name `landlord.contracts.index` on the redirect.
- `GET /landlord/leases?tab=sent` → redirect to `landlord.tenants.index` (`status=awaiting` →
  `landlord.reservations.index?stage=agreement`).
- Point internal callers straight at `landlord.tenants.index` (no extra hop): `ContractController` (store,
  line ~179), `TenantRatingController` (4 spots), `LeaseController` (`$backToModal`), `RenewContractRequest`,
  `payments/index`, `properties/show`, `reservations/index` (3 links; reword "Contracts" to "Tenants"),
  `tenancies/show`, `tenants/partials/_details`, and the `/tenants/walk-in/create` redirect in `routes/web.php`.
- `properties/edit` and `units/edit`: "Change on the Policies page" becomes "Change in Lease policies".

## Sidebar (`layouts/landlord.blade.php`)

- Remove the Contracts and Policies items.
- Tenants is active for `landlord.tenants.*`, `landlord.contracts.*`, `landlord.tenancies.*`,
  `landlord.leases.index`, `landlord.lease-templates.*`.
- Line ~73 route list: drop `landlord.contracts.index` and `landlord.leases.index` only if they are no longer
  rendered pages (check what that list controls first).

## Files

| File | Change |
|---|---|
| `app/Http/Controllers/Landlord/TenantController.php` | `index`: stage chips, counts, property / search over unit label, `pendingAgreements` count for the Reservations link; `rows()` adds `current` fallback to newest Completed, `stage` key, `lease` and docs info; `show`: adds current-contract data |
| `app/Http/Controllers/Landlord/ContractController.php` | `index` → redirect; extract `contractViewData()`; pass `tenantOptions` / `unitOptions` to the Tenants page for the Add contract modal (move to a small shared helper) |
| `app/Http/Controllers/Landlord/LeaseTemplateController.php` | drop `tab=sent`, `sentLeases()`, `SENT_STATUSES`, `SENT_STATUS_MAP`, `sentStatusOf()` (grep for other users first) |
| `resources/views/landlord/tenants/index.blade.php` | rewrite per above; include `contracts/partials/_add-modal` and `_view-modal`, `js/date-picker.js` |
| `resources/views/landlord/tenants/partials/_details.blade.php` | add current contract + other contracts |
| `resources/views/landlord/contracts/index.blade.php` | delete |
| `resources/views/landlord/leases/index.blade.php` | remove tabs and Sent & signed; add back link; title "Lease policies" |
| `resources/views/layouts/landlord.blade.php` | sidebar |
| link callers listed above | point at Tenants |
| `tests/Feature/TenantsPageTest.php`, `ContractsPageTest.php`, `LeaseTemplatesTest.php`, `ContractRenewalTest.php`, `TenantConcernsTest.php`, `TenancyDocumentsTest.php`, `PaymentsLedgerSplitTest.php`, `ReservationsPipelineTest.php`, `PerformanceBaselineTest.php` | update URLs and assertions; add tests below |
| `context/CHANGELOG.md` | entry with the manual test guide |

## Tests to add

- Each chip returns the right tenants (one tenant per stage in the fixture), and counts match.
- A tenant with a Completed and an Occupied contract shows the Occupied one; a Former tenant shows the Completed one.
- Waiting box lists Pending tenants only, and hides when the No contract chip or a search is active.
- `/landlord/contracts?status=active&open=5` redirects to `/landlord/tenants?status=living&open=5`.
- `/landlord/leases?tab=sent` redirects; `?tab=sent&status=awaiting` lands on Reservations › Agreement.
- Another landlord's tenants never appear (keep the existing isolation test).
- Query count on the Tenants page stays flat as tenants grow (`PerformanceBaselineTest`).

## Manual test guide (browser, 375px first, then desktop)

1. Sidebar shows **Tenants**, no Contracts or Policies. Tenants stays lit on Lease policies, the policy
   editor and a tenancy ledger page.
2. Open Tenants on a phone width: cards, no sideways scroll, search input doesn't zoom on focus.
3. Add a tenant. They appear in the **Waiting for a unit** box and under **No contract**.
4. Press **Give contract** in the box. The Add contract modal opens with that tenant picked. Save it: the
   tenant moves to **Moving in**, the box empties.
5. Tap each chip: Living there, Moving in, Ending soon, No agreement, No contract, Former. Counts match the rows.
6. Tap a tenant with a contract: one modal with their details and the contract actions (Renew, Confirm
   move-in, Open ledger, View agreement). Renew from it; you land back on Tenants with the modal open.
7. Rate a former tenant from the notification link: lands on Tenants with the rating form open.
8. Old links: `/landlord/contracts`, `/landlord/contracts?status=ended`, `/landlord/leases?tab=sent` all
   land on the right filter.
9. **Lease policies** button opens the policy page (no tabs, "‹ Tenants" back link); changing a property's
   policy still saves.
10. Reservations page: a sent-but-unsigned agreement is under the Agreement tab, and the Tenants page shows
    "N agreements waiting for a signature" linking there.
11. Desktop 1280px: table with Agreement and Status columns, Message icon with concern badge.
12. **Add tenant** is one modal. Desktop: two columns (tenant and unit picker on the left, contract on the right; the right shows a dashed "pick a unit" box until you choose one). Pick a unit, fill the dates and rent, press **Add tenant and contract**: you land on Tenants with the contract modal open, the tenant shows Moving in (or Living there if the move-in date is today or earlier), and the unit is Reserved or Occupied.
13. Add tenant with no unit: the tenant lands in the Waiting box as before.
14. Try to break it: a move-in date of yesterday with no money recorded, or no move-out date. You get the error next to the field, and no tenant was created (check the list and your email).
15. At 375px: three steps (Tenant, Unit, Contract) with Back and Next, nothing cut off. Submit with the first name empty from step 3: it jumps back to step 1. Edit a tenant (pencil): narrow modal, tenant fields only.
