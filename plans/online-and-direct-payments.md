# Online payments next to direct payments: tenant chooses, platform escrows, PayMongo pays out

**Status:** Phases 1 to 4 built Oct 31 2026 (suite 833 passed), all pending manual verification
(checklists below) and a real PayMongo sandbox pass. Replaces `plans/online-payment-service-fee.md` (folded into phase 2, file deleted).

## Context
- **Today** the admin switch `rentals.online_payments_enabled` picks *one* route for everyone:
  off = direct payment (Route A, default since Oct 24), on = PayMongo checkout. The views branch on
  `Payments::online()` as either/or (`agreements/show.blade.php:240`, `tenant/tenancy/show.blade.php:183/188`).
- **Goal:** when the switch is on, the tenant picks **per payment**: pay online (protected, fee) or
  pay the landlord directly (free, unprotected). Route A never goes away.
- **Gaps found while planning:**
  1. `PayMongoWebhookController::verifySignature` only checks `te` (test signature). Live events
     are signed with `li`, so **every live webhook would be rejected**.
  2. Neither the webhook nor `ReconcilesPaymongoCheckout` checks that the amount paid matches what
     we expected.
  3. A checkout can start while the tenant has a Pending "I paid" claim for the same money (claims
     already refuse during an open checkout; the reverse check is missing).
  4. Online move-in charges rent only; direct move-in is rent + deposit (`_direct-pay.blade.php:13`).
  5. No refund path exists, though PayMongo has a Refunds API (GCash refundable within 180 days).
  6. Payouts are typed in by hand by one admin.

## Decisions (Oct 31 2026, with Axcee)
| Question | Decision |
|---|---|
| Who holds online money | AbangananHub's own PayMongo account; the app escrows it |
| Online move-in covers | Rent + deposit. Both released to the landlord at move-in; the landlord keeps the deposit as today |
| Payout | Automatic PayMongo Send Money to the landlord's GCash, **sent immediately** when money becomes owed (rent the moment it is paid; move-in the moment it is released). A daily sweep only retries what could not be sent |
| Payout safety | 48-hour hold after a GCash change; admin can pause a landlord |
| Who chooses | Tenant picks each time; a landlord can opt out of online for their account |
| Problem move-ins | Admin decides in the review queue: **Refund tenant** or **Release to landlord** |
| Fee on refund | Kept by default; admin ticks "refund the fee too" when the landlord was at fault |
| Fee scope | Online move-in and online rent. Direct and cash: never |

## Who holds the money, by route
| Route | Tenant pays | Money sits with | Landlord gets it | Protection |
|---|---|---|---|---|
| Direct (QR) | Landlord's own QR | Landlord, immediately | Immediately | None. Verified landlord, signed lease, claim record |
| Cash | Landlord in person | Landlord | Immediately | None. Landlord records it |
| Online move-in | PayMongo checkout | Platform PayMongo wallet, `Held` until move-in | Immediately after move-in is confirmed (or Clock 2 expires, or admin releases) | Escrow: admin can refund |
| Online rent | PayMongo checkout | Passes through the platform wallet only until the transfer goes out | Immediately after it is paid | Paid record only (no hold, as today) |

**The "immediately" caveat:** a Send Money transfer can only spend money that has *settled* into the
platform wallet. If PayMongo settles checkout money on a schedule (the default merchant payout is
weekly), the transfer waits in `Processing` until funds arrive and is retried, so "immediately" means
"as soon as PayMongo makes the money available". Confirm the settlement timing with PayMongo (pre-live
item 3). The only way the platform *never* touches rent is PayMongo Platforms (rent lands in the
landlord's own sub-account); that is a later project, not this plan.

## Security, by route
**Direct (already built, keep):** QR validated as QR Ph and redrawn from text (`App\Support\QrPh`),
only shareable by the conversation's landlord, tenant notified on QR change, reference number
required, only a landlord confirmation counts as money (`App\Services\PaymentClaims`).
Remaining risk (told to the tenant in plain words): no recourse if the landlord takes the money.

**Online (to add):**
| Risk | Fix |
|---|---|
| Forged webhook | Keep HMAC + 300 s replay window; pick `li` when `data.attributes.livemode` is true, else `te` |
| Wrong amount settled | Settle only if PayMongo's paid total equals rows' `amount` + `service_fee`; else leave Pending and alert admins |
| Double payment across routes | Checkout refuses while a Pending claim exists for the same move-in / month (mirror of `PaymentClaims::freshCheckoutOpen`) |
| Hijacked landlord account redirects payouts | Changing GCash needs the current password, notifies by email + in-app, and sets a 48 h payout hold |
| Payout sent twice | Payout row created and payments linked inside one locked transaction *before* the API call; payout id used as the idempotency key; job runs `withoutOverlapping()->onOneServer()` |
| Money stuck in a failed transfer | Failed payout unlinks its payments (back to Pending Payout) and alerts admin + landlord |
| Admin mistakes | Every release, refund, pause and payout writes `AuditLog::record()` |
| Platform breach | Secret keys only in `.env`; GCash numbers stay out of nested payloads (`UserResource`); escrow window is short (deposit leaves at move-in) |

## Phase 1: both routes side by side
- `App\Support\Payments`: add `onlineFor(Reservation $r): bool` =
  `online() && $r->property->landlord->acceptsOnlinePayments()`. Keep `online()` for global copy.
- `User::acceptsOnlinePayments()`: has a payout destination (`hasPayoutDestination()`) and has not
  opted out. Opt-out lives on `user_payout_accounts.accepts_online` (bool, default true).
  No GCash = no online option (dashboard nudge: "Add your GCash to accept online payments").
- **Tenant choice UI** (375 px first), one shared component `<x-payment-route-choice :reservation :type :period>`:
  two cards, "Pay online" (₱X + fee, held until you move in / receipt instantly) and
  "Pay your landlord directly" (no fee, straight to landlord, no protection). Picking direct opens
  the existing `payments.partials._direct-pay`; online posts to the existing checkout routes.
  Used in `agreements/show.blade.php` (replace the `!$online` / online branches at 240–268) and
  `tenant/tenancy/show.blade.php` (183–212). Chat-panel links and `Reservation::tenantNextStep`
  (line 976) switch to `onlineFor()`.
- Swap `Payments::online()` gates for `onlineFor($reservation)` in web + API
  `Tenant\PaymentController` (checkout + rent checkout). API `AgreementController` /
  `TenancyController` return `online_available` per reservation (keep `online_payments_enabled`).
- Checkout refuses when a Pending claim exists (same type/period), inside the existing lock.
- Webhook: live signature fix + amount check. Reconcile: same amount check.
- Landlord Account Settings → Payouts: GCash fields (already exist), "Accept online payments" toggle.
  `User::showsPayoutTools()` already shows this when online is on.

## Phase 2: move-in = rent + deposit, and the service fee
- **One checkout, several rows** (same idea as walk-in move-in rows = one collection event):
  the `Initial` row keeps the rent (so `RentLedger::isPlatformInitial` and
  `hasSettledInitialPayment` keep working unchanged), plus a `Deposit` row when
  `agreedDeposit() > 0`. Both carry the same `paymongo_checkout_session_id`.
  - Migration: drop UNIQUE on `paymongo_checkout_session_id` and `paymongo_payment_intent_id`, keep plain indexes.
  - `Payment::checkoutGroup()` returns the rows of one session. Every transition moves the whole
    group in the same locked transaction: webhook, `reconcilePayment`, `clearStaleCheckouts`,
    `Reservation::confirmMoveIn`, `Admin\PaymentController::release`, `ProcessMoveInDeadlines`, refund.
  - Views that read `->where('status','Held')->first()->amount` switch to the group sum
    (`cancellationBlockedReason` already sums).
- **Service fee:** `payments.service_fee` decimal(10,2) default 0, stored on the `Initial` /
  `Monthly` row only. `App\Support\ServiceFee::for(float $amount)` =
  `round(amount × percent / 100 + fixed, 2)`; settings `online_payment_fee_percent` (0–20) and
  `online_payment_fee_fixed` (0–500) in `Setting::DEFINITIONS` group Payments, default 0.
  Frozen on the row when the checkout is created.
- **De-duplicate the four checkout calls** into `PaymongoCheckout::create(Reservation, array $lines, string $description, string $successUrl, string $cancelUrl)`;
  lines = rent, deposit (move-in), "Online payment fee".
- `amount` stays "what the landlord is owed", so payouts (`PayoutController` sums `amount`), ledger
  and analytics are unchanged. Receipt / pending page / `PaymentResource` show rent, deposit, fee, total.

## Phase 3: refunds (admin review queue)
- `App\Services\PaymongoRefunds::refund(string $paymongoPaymentId, int $centavos, string $reason)`
  → `POST /v1/refunds`. Fails closed.
- `Admin\PaymentController::refund(Payment)`: Held groups only. Form: reason (required),
  "refund the online payment fee too" checkbox. Locked transaction: call PayMongo, mark the group
  `Refunded` (enum value already exists), cancel the reservation through the existing cancel path
  so the unit is released, post a system message, notify both parties, audit log.
- New columns on `payments`: `refunded_at`, `refunded_by`, `refund_reason`, `paymongo_refund_id`,
  `refund_includes_fee`.
- Webhook handles `payment.refund.updated`: a failed refund alerts admins (row stays Refunded with a
  "refund failed" flag shown in admin).
- Copy updates: the "held by AbangananHub" panel and the held-cancel message now say an admin can
  refund. Update `docs/specs/2026-07-22-move-in-confirmation-window-design.md` Phase 2 note.

## Phase 4: automatic payouts
- `payouts` gains `status` enum('Processing','Sent','Failed'), `paymongo_transfer_id`,
  `failure_reason`; `paid_out_at` becomes nullable (set when Sent). Existing rows backfill to `Sent`.
- `user_payout_accounts` gains `payouts_hold_until` and `paused_at` / `paused_by`.
- Extract the batch logic in `Admin\PayoutController::markPaidOut` into
  `App\Services\LandlordPayouts::batch(User $landlord): ?Payout` (locks pending rows, snapshots GCash,
  links `payout_id`). Manual "mark paid out" stays as an admin fallback through the same service.
- `App\Services\PaymongoTransfers::send(Payout)` → PayMongo Send Money to the GCash number.
  **Verify the request fields in the live Transfers reference before coding** (only summaries were read).
- **Immediate send:** a queued job `SendLandlordPayout(landlordId)` is dispatched (`afterCommit`)
  wherever `payout_owed_at` is set: the webhook and `reconcilePayment` (rent `Paid`),
  `Reservation::confirmMoveIn`, `Admin\PaymentController::release`, `ProcessMoveInDeadlines`
  auto-release. Hook it in `PaymentObserver` on the `payout_owed_at` change so no path is missed.
  The job calls `LandlordPayouts::batch()` then `PaymongoTransfers::send()`, wrapped in a per-landlord
  `Cache::lock` so two payments paid at once become one transfer, not a race.
- **Retry sweep:** command `payouts:send`, scheduled hourly in `routes/console.php`,
  `withoutOverlapping()->onOneServer()`, picks up anything still owed or `Processing`.
  Skips landlords that are paused, in the 48 h hold, or have no destination (their money waits and
  goes out when the hold ends). Insufficient platform balance = leave it and retry (not Failed).
- Transfer result by webhook (transfer events) with a poll fallback in the sweep.
- **Transfer cost:** ₱10 per transfer, now one per rent payment. Set the fee's fixed part to cover it
  (e.g. PayMongo's rate + ₱10).
- Changing GCash (`User::savePayoutAccount`, web + API `ProfileController`): require current
  password, set `payouts_hold_until = now()+48h`, notify by email + in-app.
- Admin payouts page: Processing / Sent / Failed, Retry, Pause / Resume per landlord. Landlord payout
  history shows status.

## Docs and legal
`context/SCHEMA.md`, `ARCHITECTURE.md` (decision row: tenant-chosen route, escrow, refunds, auto
payouts), `PRD.md`, `CHANGELOG.md`; `<x-payment-consent>` and the Terms page (fee, refund policy,
who holds money) with a `rentals.terms_version` bump so everyone re-agrees.

## Before going live (not code)
1. Ask PayMongo (and if needed BSP) whether a standard account may collect for landlords and pay them out.
2. Activate the live account; confirm Wallet + Send Money are enabled.
3. Confirm when checkout money reaches the wallet (default settlement is weekly) — payouts can't
   send money that hasn't settled.
4. Look up real GCash / QR Ph fee rates and set the fee.
5. Register the live webhook: `checkout_session.payment.paid`, `payment.refund.updated`, transfer events.

## Verification
Automated (`php artisan test`): webhook signature (te/li, bad signature, stale timestamp), amount
mismatch leaves Pending, checkout blocked by pending claim and vice versa, group transitions
(confirm/release/auto-release/refund move both rows), fee math and freezing, payout batch locking and
idempotency (`Http::fake`), failed transfer unlinks payments, 48 h hold skips the landlord.
Extend `escrow:verify` with the rent + deposit group.

Manual browser checklist for Axcee (sandbox, online switch on, fee 2% + ₱10):
1. Tenant at Signed sees both choices at 375 px; landlord with no GCash → only direct shows.
2. Landlord turns "Accept online payments" off → tenant sees only direct.
3. Pay move-in online: checkout shows rent, deposit, fee lines; agreement page shows held total.
4. File "I paid" while a checkout is open, and start a checkout while a claim is pending: both refused.
5. Confirm move-in: both rows Released; landlord sees pending payout of rent + deposit (no fee).
6. With a queue worker running, the payout appears right after move-in is confirmed, Processing → Sent
   (sandbox); landlord history updates. Stop the worker, confirm again, then `php artisan payouts:send`
   picks it up.
7. Change landlord GCash: password asked, email sent, payouts for that landlord wait 48 h.
8. Admin pauses a landlord: skipped; resume: sent.
9. Escalated move-in: admin refunds with and without the fee box (sandbox may not support refunds —
   check the API response; if refused, test with `Http::fake` only and note it).
10. Pay rent online: fee line shows; ledger month settles; a payout for that rent is sent right away.
11. Pay two months back to back: one transfer per payment, never a duplicate for the same rows.
12. Direct and cash payments: no fee anywhere, no payout.

Note: CLAUDE.md asks to save a copy into `plans/` on approval — save as
`plans/online-and-direct-payments.md` and delete `plans/online-payment-service-fee.md`.

## Phase 1 manual test guide (built Oct 31 2026; Axcee to run in the browser)
Setup: System Settings > Online payments **on**; PayMongo test keys in `.env`. Use a landlord with a
payment QR shared in the chat and a tenant with a signed agreement. Do 3 to 9 at 375px as well.
1. **No GCash, no choice.** Landlord with empty GCash fields: tenant's agreement page shows only "Pay your landlord to move in" (the QR and "I've paid"), no "How do you want to pay" cards.
2. **Add GCash.** Landlord saves both GCash fields in Account Settings > Payout details: the tenant's page now shows two cards, "Pay online" and "Pay {name} directly", neither selected.
3. **Pick online.** Tap "Pay online": the first month's amount and a "Pay ₱X online" button show, plus the note that the deposit is paid to the landlord separately. The button opens PayMongo's test checkout.
4. **Pick direct.** Tap the direct card: the QR and "I've paid" form show. Send a report.
5. **Both at once is refused.** With the report waiting, go back, pick online and press pay: you stay on the page with "You already told your landlord you paid this...". No PayMongo page opens.
6. **Withdraw, then online works.** Withdraw the report, then pay online again: checkout opens.
7. **Opt out.** Landlord un-ticks "Let tenants pay me online" and saves: the tenant's page shows direct only. Re-tick: the cards return.
8. **Rent page.** Move the tenant to Occupied: Rent & payments shows the same two cards for the earliest unpaid month; paying a month ahead shows direct only.
9. **Chat panel.** At Signed with a waiting report the tenant's chat card says "Waiting for your landlord to confirm"; without one it shows "Proceed to payment" (online available) or "I've paid" (direct only).
10. **Webhook, only after deploy with a public URL:** a sandbox payment settles through the webhook, not just the poll. Locally PayMongo cannot reach the app, so the poll is what settles it.

## Phases 2 to 4 manual test guide (built Oct 31 2026; Axcee to run)
Setup: online switch on, test keys, landlord with GCash and a QR shared, unit deposit 3,000, rent 5,000,
System Settings > Payments fee 2% + 10. Do the screens at 375px too. Run `php artisan migrate` first.

**Phase 2: deposit group and fee**
1. Save fee 2 and 10 in System Settings; a value over 20 is refused.
2. Tenant at Signed picks "Pay online": rent 5,000, deposit 3,000, fee 110, total 8,110.
3. PayMongo checkout lists three lines. After paying, the pending page shows the breakdown and "Total paid 8,110".
4. Agreement page says 8,000 is held, fee shown separately; each party gets one "payment received" notification.
5. Confirm move-in: both rows Released; the landlord's pending payout is 8,000, not 8,110.
6. Repeat, but an admin presses Release on the deposit row: both rows release.
7. Pay monthly rent online: rent + fee, no deposit line. Set both fees to 0: no fee line.

**Phase 3: refunds** (needs a Held online move-in; PayMongo test-mode refunds are unconfirmed)
8. Admin > Payments > Held: "Refund tenant" next to "Release". The modal shows the exact amount, reason required, the fee box changes the total.
9. Refund: rows become Refunded, reservation Cancelled, unit Available; chat message, both notifications and the Refunded tab show it. A fast double click gives a 409.
10. QR Ph payment: the tenant's reservations list shows "Claim your refund". A PayMongo error shows a plain message and changes nothing.
11. The admin conversation page's escrow card has a Refund button too.

**Phase 4: payouts** (a queue worker running; needs the four env vars)
12. With `PAYMONGO_GCASH_BIC` empty, release a payment: Admin > Payouts says automatic payouts are off, an admin is notified, the money stays owed.
13. Set the env vars, `php artisan config:clear`, release a payment: a Processing payout appears; PayMongo's answer or refusal shows in the table.
14. Landlord Payouts shows "Sending to your GCash" while Processing.
15. Change the GCash number: a password is asked (wrong one rejected inline), a hold notice and an email follow; money released during the hold waits and goes out on the first `php artisan payouts:send` after it ends.
16. Admin Pause a landlord: skipped; Resume: sent. A stuck Processing payout with no transfer id offers Retry only after a confirm that says to check PayMongo first.
17. Real webhook events (refunds, transfers, live signature) only work once deployed at a public URL.
