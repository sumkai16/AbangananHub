# Reservation flow: inquiry dates + occupants, agreement with the final terms

**Status: implemented Oct 31 2026 on branch `emman`, pending Axcee's manual check (see Verification).** Steps 2 and 3/6 of Axcee's 8-step flow (see
`plans/landlord-tenants-contracts-direct-payments.md`); steps 1, 4–5, 7 and 8 are already built.

## Context
- **Step 2 (inquiry):** the form asks a duration (1/3/6/12 months) and no occupant count. Axcee's flow asks for a
  preferred move-in date, an expected move-out date and the number of occupants.
- **Step 3/6 (agreement):** the landlord and tenant agree rent, deposit, advance, dates and rules in chat, but the
  Send-agreement form only takes extra text: rent and deposit are copied from the listing and the move-in amount
  is fixed at one month + deposit.

Decided Oct 31 2026: move-in **required**, move-out **optional** (at least a month after move-in when given),
occupants **required** (1 to the unit's limit); the agreement carries **advance months 0–3** (default 1, today's
behaviour).

## Changes
1. **Inquiry** — `StoreReservationRequest` (+ the mobile `Api\Tenant\ReservationController::store` inline rules):
   `target_move_in_date` required; `duration_months` replaced by `target_move_out_date` (nullable, ≥ move-in + 1
   month); `occupants_count` required, ≤ unit `occupancy_limit`. Both forms in `properties/show.blade.php` (desktop
   and phone): move-out date picker instead of the stay-length select, plus an occupants field.
2. **Agreement** — one shared field partial for the chat panel's and the reservations list's Send-agreement forms:
   monthly rent (prefill: agreed or listing), security deposit (prefill: listing), advance months (0–3, default 1),
   move-in, move-out, rent due day, occupants, extra terms (prefill: property standard terms). One
   `SendAgreementRequest` for web and API (all term fields optional so the mobile app keeps working; missing
   ones fall back to today's defaults). `Reservation::advanceToPendingAgreement()` stores them before the lease
   snapshot; the snapshot gains `deposit` (agreed) and `advance_months`.
3. **Move-in amount** — `Reservation::advanceMonths()` and `moveInDue()` (= advance × rent + deposit) replace
   "monthly rent + deposit" on the pay card, the record-move-in form and the lease document.

## Verification
Tests: inquiry requires move-in and occupants, refuses occupants over the limit and a move-out under a month;
agreement stores the final terms and snapshot; move-in due follows advance months; the mobile endpoint still sends
an agreement with no term fields. Suite, Pint, build. Manual: inquire at 375px → accept → send agreement with ₱3,500
rent, ₱3,500 deposit, 1 month advance → tenant sees ₱7,000 due → lease shows the same.
