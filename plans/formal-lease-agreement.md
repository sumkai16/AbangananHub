# Formal lease for online and walk-in tenants

**Date:** 2026-09-28
**Status:** Implemented 2026-09-28. Pending manual verification.

## Context
Axcee wants walk-in tenants to go **details → lease → payment** instead of straight to payment, and a
formal agreement for online tenants too. Agreed approach (not a stored "Pending" status): one lease
template for both flows, steps ordered inside the walk-in form, "upload later" allowed.

What exists today:
- **Online:** landlord "Send agreement" (free-text `agreement_terms_notes` ≤ 2000 + T&C) → tenant signs on
  `agreements/show` (`Tenant\AgreementController::sign`, IP + time) → payment. Order is already right; the
  document is thin (parties, rent, deposit, dates, free text).
- **Walk-in:** `Landlord\WalkInTenantController::store` writes `Occupied` + optional payment. No lease.
- **Gaps found:** (1) the agreement renders from *live* data, so editing house rules/deposit after signing
  silently changes a signed document; (2) the landlord can't open the agreement page (`viewAgreement` is
  tenant-only); (3) walk-ins have nothing to print or attach.

## Decisions
| Decision | Choice | Why |
|---|---|---|
| One lease, two ways to sign | Same generated lease. Online: tenant e-signs (existing). Walk-in: print → sign on paper → upload scan, or "upload later" | One document, no second template to drift |
| Freeze the terms | `reservations.lease_snapshot` (JSON) written when the landlord sends the agreement (online) or saves the walk-in; the lease renders from it | A signed contract must not change when the property is edited later. Old rows without a snapshot render live, as today |
| What the lease contains | Parties, premises (address, property, unit), term (start, end or month-to-month), rent + due day, deposit, utilities included/not (property booleans), house rules (`properties.house_rules`), occupants, notice period (config `lease_notice_days`, default 30), standard clauses (use of premises, repairs, deposit return, termination), landlord's extra terms, signatures, platform disclaimer | All from existing columns; nothing new for the landlord to fill in |
| Walk-in upload | Optional file (PDF/JPG/PNG, ≤ 10 MB) on the private `local` disk, same pattern as `property_documents` | ID-grade document, must not go to public Cloudinary |
| Storage | Columns on `reservations`: `lease_file_path`, `lease_file_name`, `lease_uploaded_at`. One file per tenancy; replacing deletes the old file | A tenancy has one signed lease; a history table is more than needed |
| Walk-in without a lease | Allowed ("Upload later"); tenancy + tenants list show a **Lease missing** badge until uploaded | Landlords onboarding tenants who already live there can't be blocked |
| Not doing | Stored Pending status for walk-ins; Reserved-until-move-in for future walk-ins; PDF generation library (browser Print/Save PDF, already on the page) | Agreed scope |

## Changes
**Data**
- Migration `add_lease_fields_to_reservations_table`: `lease_snapshot` JSON null, `lease_file_path`, `lease_file_name` VARCHAR null, `lease_uploaded_at` TIMESTAMP null. Cast snapshot to array in `Reservation`.
- `config/rentals.php`: `lease_notice_days` => 30.

**Lease builder**
- New `app/Support/LeaseTerms.php`: `snapshot(Reservation): array` from `monthlyRent()`, `rentDueDay()`, unit deposit, dates, occupants, property utilities + `house_rules`, `agreement_terms_notes`, notice days, generated_at. `for(Reservation): array` returns the stored snapshot or a live build (legacy rows).
- New `resources/views/leases/_document.blade.php`: the printable lease body rendered from that array (numbered clauses, signature block). Used by the tenant agreement page and the landlord lease page.

**Online**
- `Landlord\ReservationController::advanceToPendingAgreement` (and the chat-panel send form, same route): write `lease_snapshot` in the same save.
- `agreements/show.blade.php`: replace the inline body with `leases/_document`; signature block unchanged.
- `ReservationPolicy`: new `viewLease` (tenant of it, or landlord of its property).

**Walk-in**
- `walk-in/create.blade.php`: new **Lease** card between "Unit & terms" and "Initial payment": radio *Upload signed lease now* (file input) / *Upload later*, with a line that the printable lease is on the tenancy page. Form gets `enctype="multipart/form-data"`.
- `StoreWalkInTenantRequest`: `lease_file` nullable, `file|mimes:pdf,jpg,jpeg,png|max:10240`.
- `WalkInTenantController::store`: write `lease_snapshot`; store the file before the transaction, save its path on the reservation inside it, and delete the stored file if the transaction throws (no orphaned uploads).

**Landlord lease page + upload**
- New `Landlord\LeaseController`: `show` (printable lease, `leases/show.blade.php` wrapping `_document`, Print/Save PDF), `upload` (store/replace file), `file` (inline preview/download). Authorize with `viewLease` / landlord-of-property.
- Routes under `landlord.` prefix: `GET tenancies/{reservation}/lease`, `POST tenancies/{reservation}/lease-file`, `GET tenancies/{reservation}/lease-file`.
- `landlord/tenancies/show.blade.php`: **Lease** card — status (Signed online on …, Signed copy uploaded on …, or Lease missing), buttons *View / print lease*, *Upload signed copy* / *Replace*, *Open file*.
- `landlord/tenants/index.blade.php`: "Lease missing" badge for walk-ins with no file.
- Tenant tenancy page (`tenant/tenancy/show`): "View lease" link to `agreements.show`.

**Docs:** `context/SCHEMA.md` (new columns), `context/ARCHITECTURE.md` (lease snapshot + walk-in lease), copy of this plan to `plans/formal-lease-agreement.md`.

## Verification
Feature tests (`tests/Feature/LeaseTest.php`, DatabaseTransactions on the dev DB — never RefreshDatabase):
- Sending the agreement stores a snapshot; changing the property's house rules afterwards doesn't change the rendered lease
- Walk-in with a file stores it on the local disk and sets `lease_uploaded_at`; without one, the tenancy shows "Lease missing"
- Upload later from the tenancy page; replace deletes the old file
- Wrong landlord → 403 on lease page, upload and file; tenant can't hit landlord routes
- Bad file type / > 10 MB refused
- Existing reservation with no snapshot still renders

Manual (375px + desktop): add a walk-in with and without a lease file → tenancy Lease card states; print the lease; online flow: send agreement → tenant sees the full lease → signs → pays.
