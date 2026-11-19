# Flexible online payment (pay in parts, finish before move-in)

## Context
Direct payment (QR / cash) lets a tenant pay part of the move-in money now and the rest later, before move-in
(`Reservation::minimumMoveInPayment()`, `PaymentClaims::assertCoversMoveIn`). Online (PayMongo) only takes the
full amount in one go, and is switched off once any move-in money exists (`moveInPaid() > 0`).
Goal (user): online works the same way. The tenant chooses how much to pay online, and can pay the remaining
amount online later, in as many payments as needed, until move-in is fully paid.

Rules (same as direct): first payment >= `minimumMoveInPayment()` (the reservation fee on a fee unit, else the
shortfall); later payments any amount up to `moveInShortfall()`. Each online payment adds the online fee
(`ServiceFee::for`) on top and is held in escrow (Held), as today. Money is allocated fee → deposit → rent, the
order `MoveInPaymentBreakdown` / `MoveInPaymentRecorder::record` already use.

## Changes
1. **Take an amount** — `app/Http/Controllers/Tenant/PaymentController.php::createCheckoutSession`
   - Validate `amount`: >= `minimumMoveInPayment()`, <= `moveInShortfall()`; default = full shortfall.
   - Remove the "part of your move-in payment is already recorded" refusal.
   - Replace the `hasSettledInitialPayment()` guard with "shortfall > 0" (an `Initial` row can now exist before everything is paid).
2. **Split into rows** — `ReconcilesPaymongoCheckout::openCheckout` (move-in branch) + `Reservation::moveInPayLines()`
   - Create only rows with amount > 0: `Reservation Fee`, `Deposit`, `Initial` (rent), per the allocation order, net of what is already paid.
   - Checkout `$lines` = those rows + online fee. `Payment::checkoutTotal()` / `paidAmountMatches` must equal the charge.
   - The re-check under the lock and `clearStaleCheckouts` (looks at `Initial` + `Deposit` rows) must also cover `Reservation Fee`.
3. **Settle** — `PaymongoCheckout::settle` already holds non-Monthly rows and calls `holdUnit()`.
   After settling, if `moveInShortfall() <= 0` set `move_in_paid_at` like `PaymentClaims::applyMoveInPayment` does;
   otherwise leave the balance owed. Fix the system message ("completed the initial payment") for part payments.
4. **"Settled" logic** — `Reservation::hasSettledInitialPayment/moveInSettled/paidOnline` assume one online `Initial`
   row means done. Make them follow the shortfall so a part payment is not read as "fully paid" (stage tabs, unit hold, agreement page).
5. **UI** — `resources/views/components/payment-route-choice.blade.php`
   - Remove the `moveInPaid() > 0` switch-off of `$online`.
   - Online card: amount field with quick buttons ("Reservation fee" / "Full amount"), live online fee + total, min/max hint,
     and the same "remaining ₱X due before move-in" note as the cash card. Drop the "pay only the fee → pay directly" note.
   - After a part payment the card shows "Balance before move-in" with the remaining amount, ready to pay online again.
6. **Escrow / refunds / payouts** — `LandlordPayouts`, escrow release, refund code were built around one `Initial` + `Deposit`
   checkout. Check they handle several checkouts and a held `Reservation Fee` row (likely type lists and "latest checkout" assumptions).
7. **API + docs** — `Api/Tenant/PaymentController` if it has its own move-in checkout; `context/CHANGELOG.md`, `PRD.md`, `SCHEMA.md` if rules change.

## Verification
- Fee unit, online: pay the fee only → unit Reserved, balance shown; pay part of the balance → still owed; pay the rest → move-in settled, `move_in_paid_at` set.
- Pay full in one go → same result as today. Each PayMongo total = amount + online fee.
- Amount below minimum or above the shortfall is refused; an open checkout or pending "I paid" report blocks a second payment.
- Non-fee units: minimum is the full amount, so behaviour is unchanged.
- Escrow release / refund of a tenant who paid in several online payments.
- `php artisan test` for payments; add tests for fee-only → balance, and over/under amounts.
- Manual pass in PayMongo test mode at 375px (per CLAUDE.md `test-before-commit`).
