# CHANGELOG.md — what changed and why (Oct 2026)

Newest first. One entry per piece of work, with the files to read. Longer reasoning lives in ARCHITECTURE.md (Key Decisions Log), DESIGN.md §31, RULES.md and SCHEMA.md; this file is the index. Entries up to `94f2e15` came from branch `joseph`; the two Oct 8 fixes below are on `axci`.

## Oct 8 2026 — home page redesign reverted (`b8be7d1`, branch `axci`)
- `properties/index.blade.php` restored to its `c18e701` version, undoing the `94f2e15` changes to that file. `components/section-texture.blade.php` restored too: the old page uses it and `94f2e15` had deleted it.
- **Reverted:** two-column hero and "Why AbangananHub" panel, minimal "Browse by neighborhood", two-row home grid, wider filters modal, the texture removal.
- **Kept from `94f2e15`:** category icons, header search band `py-5`, 404 font, inline form errors, the browse-sort index migration.
- Checked: `/properties` and `/properties?sort=price_asc` return 200 with no log errors. Not checked in a browser. DESIGN.md §31 notes which parts are gone.
- `origin/joseph` still has the redesign, so a future merge of `joseph` could bring it back.

## Oct 8 2026 — browse-sort index migration rollback fixed (`6a7240d`, branch `axci`)
- `2026_10_08_000000_add_browse_sort_indexes` could run but not roll back: `down()` failed with MySQL error 1553 because the new `reviews_property_rating_index` had become the `reviews.property_id` foreign key's index.
- `down()` now adds `reviews_property_id_index` before dropping the composite. Checked on `abanganan_hub_test`: rollback, migrate, rollback, migrate all succeed; suite 70 passing. Dev database unaffected (only `down()` changed). Rule: RULES.md "Laravel Conventions".
- `origin/joseph` still has the old `down()`.

## Oct 8 2026 — teacher feedback pass + home page redesign (`94f2e15`)
> The `properties/index.blade.php` and `section-texture` parts of this entry were reverted in `b8be7d1` (above).
A teacher reviewed the public home page. Each suggestion was checked against the code; some were applied, some declined. Plan: `plans/teacher-feedback-pass.md`. Design details: DESIGN.md §31.

Applied:
- **Hero** two columns from `lg` with a "Why AbangananHub" trust panel; search pill centered below. `properties/index.blade.php`.
- **"Browse by neighborhood"** centered and restyled minimal (no borders or pills, rounded-3xl, lighter gradient, quiet "View all areas" tile). Same file.
- **Home grid** capped at two rows at every width (cards hidden per breakpoint, "View all" tile stays last).
- **Filters modal** wider (`max-w-7xl`) and tighter, to avoid the desktop scrollbar.
- **Search band spacing** `py-4` → `py-5` in `layouts/app.blade.php`.
- **Category icons** redrawn for Apartment, Condominium, Boarding House (`components/category-strip.blade.php`).
- **404 page** uses Plus Jakarta Sans instead of the only serif on the site (`errors/404.blade.php`).
- **Removed** `components/section-texture.blade.php` (dots, stripes, wavy lines in the side gutters).
- **Forms:** `novalidate` + per-field inline errors on login, register, forgot/reset password, profile edit, password change, delete account. Login/forgot/reset lost their single top banner. RULES.md "Forms".
- **Sort performance:** migration `2026_10_08_000000_add_browse_sort_indexes` (SCHEMA.md). No measurable change on dev data (~8 ms before and after).

Declined (with the reason):
- **"Font looks like Times New Roman":** the app uses Inter and Plus Jakarta Sans, never a serif. The Times look was unstyled text from CSS failing to build when npm packages were missing (see Environment below).
- **"Profile: Show all properties must stay on the profile page":** no such redirect exists. `landlord/profile/show.blade.php` "View all" switches tabs in place and "Show more" loads the next 12 via fetch.
- **Native validation removed from all forms:** limited to auth + profile on purpose (RULES.md "Forms").
- **A cache layer for sorting:** `CACHE_STORE=database` and the `cache` table is what froze MariaDB.

## Oct 6 2026 — landlord: unit double-booking bug (`c88056c`)
- `Reservation::releaseUnit()` freed a unit even when another live reservation held it, so rejecting or cancelling one inquiry could re-list a unit another tenant had reserved. The controller guards ran after the unit was already freed.
- New `Reservation::releaseUnitIfUnclaimed()` is used by `reject()` and `cancel()`; `endTenancy()` still uses `releaseUnit()`. Guards removed from `Landlord\ReservationController` and `Api\Landlord\ReservationController`. Tenant-side cancel is covered too.
- Checked by creating two live reservations on one unit in tinker (cancel one → unit stays Reserved; reject the second → Available) and by the reservation/tenancy/cancel tests.

## Oct 5 2026 — admin: deposit charge oversight (`2be180b`)
- `ReservationPolicy::voidDepositCharge()` now also allows Admin. `recordDepositCharge` unchanged.
- New `Admin\DepositChargeController` (`index` with Active / Voided / All tabs, `void` in a locked transaction with `AuditLog`, notifies tenant **and** landlord), routes `admin.deposit-charges.index` / `.void`, view `admin/deposit-charges/index.blade.php` with a void-reason `<x-modal>`, and a "Deposit Charges" sidebar link in `layouts/admin.blade.php`.
- Checked in tinker: admin sees and voids a charge, both notifications created, a tenant hitting the route gets 403, the landlord's own void still works. Test data deleted afterwards.

## Oct 5 2026 — tenant: read-only lease view (`3e86290`)
- `viewLease` already allowed tenants but no tenant route existed. New `Tenant\LeaseController` with `tenancy.lease` and `tenancy.lease.file` (gated by `viewLease`, not the landlord-only `manageLease`).
- `leases/show.blade.php` takes `isLandlordView`; `Landlord\LeaseController::show` passes `true`. The "View lease" link in `tenant/tenancy/show.blade.php` was fixed.
- Checked in tinker: own tenant 200, a different tenant 403, landlord unchanged.

## Environment (not in a commit)
- `npm install` was needed: `@fontsource/inter`, `plus-jakarta-sans`, `dm-serif-display` and `chart.js` were in `package.json` but not installed, so Vite showed an error overlay and `npm run build` failed. Fixed by installing; build passes. Rule: RULES.md "Dev environment gotchas".
- Pending `emman` migrations (viewing requests, blocked dates, viewing hours, lease fields, deposit charges, property-type changes) were run on the dev DB.
- Dev MariaDB froze on the `cache` table; restarted `mysqld`. ARCHITECTURE.md Known Tradeoffs.
- Branch check: `joseph` was fully merged with `origin/main` (`c18e701`) before the teacher pass.

## Known and still open (landlord survey, Oct 6 2026)
Verified but not fixed; full list in ARCHITECTURE.md Known Tradeoffs.
- Mobile list views: Tenants page empty on phones when `tenantsView` is `table`; Units defaults to a wide table; properties and tenancy ledger tables lack a mobile card fallback.
- No audit log or tenant notification in `Landlord\PaymentController::store()` and `Landlord\LeaseController::upload()`.
- `landlord.properties.units.show` has no controller method (500); `data-confirm-type="danger"` is invalid in `landlord/viewings/_row.blade.php`; no sidebar highlight on the Viewings tab; six landlord loops miss `depositCharges` eager loading; no viewing, lease or deposit-charge endpoints on the landlord API.
- Admin has no oversight yet of viewing scheduling or lease agreements (only deposit charges were done).

## Not verified in a browser
Everything above was checked with tinker requests, Blade compile and the test suite (36 passing for auth, profile, browse, property, reservation and viewing tests), never by clicking through. Pending a manual pass: hero layout, filters modal scrollbar, two-row cap per breakpoint, icons, dark mode, mobile width, and the empty-form inline errors.
Update Oct 8 2026: `ViewingSchedulingTest::test_only_the_reservations_tenant_can_request` now passes. The tests build their own fixtures (`tests/Support/CreatesMarketplaceFixtures.php`, `b529738`), and the full suite runs green on `abanganan_hub_test` (70 tests).
