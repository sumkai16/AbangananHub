# Reservation payment arrangements (per unit)

## Context
Business rules say the landlord picks a payment arrangement per unit (Reservation Fee / Advance + Deposit / Full Payment), sets amount, deadline and expiry, and a unit is only held/occupied once the required payment is verified. Today every unit uses one fixed flow: agreement -> sign -> pay move-in money (rent x advance_months + deposit) -> confirm -> Occupied. Verification, walk-in recording, overlap blocking and the occupancy gate already exist. Missing: per-unit arrangement, reservation fee, per-unit deadline/expiry, and any check that the amount paid covers what is due.

Scope chosen: core only (no landlord refund policy). Reservation fee holds the unit as soon as it is verified.

## Status of the 12 rules
Done: 5, 6, 8. Partial: 1, 4, 7, 10, 12. Missing: 2, 3, 9, 11. (Rule 9 refund/cancellation conditions deferred.)

## Changes
1. **Migration on `property_units`**: `payment_arrangement` (enum `reservation_fee|advance_deposit|full_payment`, default `advance_deposit` = today's behaviour), `reservation_fee_amount` (nullable), `payment_deadline_days` (nullable, 1-7), `reservation_conditions` (nullable text). Update `context/SCHEMA.md`.
2. **Payment category**: add `Reservation Fee` to `payments.payment_type` enum (pattern: `2026_07_26_000000_add_qrph_to_payments_payment_method.php`). Do not add `Advance` (SCHEMA rule).
3. **Amount due**: extend `Reservation::moveInDue()` / `agreementDefaults()` to compute by arrangement: fee = fee amount; advance_deposit = rent x advance_months + deposit; full = those plus other upfront charges. Split fee vs balance in `app/Support/MoveInPaymentBreakdown.php`.
4. **Amount check (rule 11/12)**: in `PaymentClaims::file`, `PaymentClaims::confirm`, `PaymentClaims::recordMoveIn`, `MoveInPaymentRecorder` and `Reservation::moveInSettled()`/`markOccupied()`: fee settles the hold; occupancy needs the full balance paid. Keep web and `Api/*` paths in sync (they share the model methods).
5. **Hold on fee**: verified fee calls `holdUnit()` and sets `Reserved`; overlap check `unitHeldByAnother()` unchanged.
6. **Deadline/expiry per unit**: `Reservation::paymentDeadline()` and `ProcessStaleReservations` read `payment_deadline_days`, falling back to `config/rentals.php` `stale_reservations`.
7. **Landlord UI**: arrangement picker, fee, deadline, conditions on the unit form (`Landlord\PropertyUnitController` + views, mobile-first per DESIGN.md). Show arrangement and deadline to the tenant on `resources/views/agreements/show.blade.php`.
8. Update `context/CHANGELOG.md`.

## Reuse
`PaymentClaims`, `MoveInPaymentRecorder`, `MoveInPaymentBreakdown`, `Reservation::holdUnit/unitHeldByAnother/paymentDeadline`, `ProcessStaleReservations`.

## Verification
- Feature tests per arrangement: fee hold, shortfall blocked from Occupied, full balance allows Occupied, overlap still blocked, per-unit deadline expiry.
- Run `php artisan test`, `npm run build`.
- Manual in browser at 375px: set arrangement on a unit, tenant pays fee (direct claim), landlord confirms, unit shows Reserved; try move-in with balance unpaid (blocked); pay balance, mark Occupied; walk-in record with each arrangement.
