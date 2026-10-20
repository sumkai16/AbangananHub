> **Status (Oct 15 2026): implemented and covered by tests (full suite green); pending manual verification in the browser.** Delete this file once the manual pass is done.

# Tenant side: security, payment dead-ends, navigation (post-`axci` pull)

## Context

User asked for a full tenant-side check: fix present and future problems, improve bad design. Three
parallel surveys ran on the freshly pulled `axci` code; every high-impact claim below was then verified by
reading the code. User chose these groups: **Security & privacy**, **Payment dead-ends**, **Navigation
dead-ends**. They chose **"count Initial as the first month's rent"** for the money model and **"block
cancel while money is held"**. *Mobile & design polish was not selected* (see "Not in this pass").

Verified and **dropped** (agents claimed, code says otherwise):
- Tenancy page lazy-load exception: Laravel only blocks lazy loading on models hydrated in groups of 2+; this page loads one reservation. Not a bug (just add `property.landlord`, `tenant` to the `load()` as tidy-up).
- Landlord-profile "Message" button 405: it GETs `/conversations`, which is the inbox, so it opens the inbox ignoring `landlord_id`. Harmless, left alone.

## A. Security & privacy

1. **Reflected XSS**, `resources/views/conversations/index.blade.php:316`: `const active = {{ request('active','null') }};` is unquoted JS, so `?active=alert(1)` runs. Replace with `@js(request()->integer('active') ?: null)`.
2. **PII leak**, `ReportController::create()` (`app/Http/Controllers/ReportController.php:51-54`) feeds `reports/create.blade.php:143-146`: every logged-in user sees every user's name + email. Restrict the "user" list to people the reporter shares a conversation with (`Conversation` `tenant_id` / `landlord_id`), show **names only, no emails**, and add a hint that profile "Report" buttons (`reports.create?user_id=`) report anyone else. `store()` validation unchanged.
3. **Account deletion wipes money**, `ProfileController::activeObligation()` (`:127-161`): only live reservations block it, and `payments.reservation_id` cascades, so a tenant with a Cancelled/Completed tenancy can delete their account and destroy Held payments and landlord payouts still `Pending Payout`. Add a check: any payment on their reservations with `status = 'Held'` OR `payout_status = 'Pending Payout'` blocks deletion, with a named message (contact support).

## B. Money integrity

**B1. Charge the rent the tenant signed.** Online tenants never get `agreed_monthly_rent` (only walk-ins do), so checkout and the ledger use the live `unit->rental_fee`, and a landlord edit after signing changes what the tenant pays.
- `Reservation::advanceToPendingAgreement()`: set `agreed_monthly_rent ??= unit->rental_fee` **before** `LeaseTerms::snapshot()`.
- Migration (data only): for reservations with a `lease_snapshot` and null `agreed_monthly_rent`, set it from the snapshot's `monthly_rent`.
- Use `$reservation->monthlyRent()` for the Initial amount and PayMongo line item in `Tenant\PaymentController::createCheckoutSession` (`:55,65`) and `Api\Tenant\PaymentController` (`:61,71`), and for the displayed amount in `agreements/show.blade.php:205`.

**B2. Initial payment counts as the move-in month's rent** (`app/Services/RentLedger.php`). Today `Initial` is in `NON_MONTHLY_TYPES`, so after moving in the first month reads Due/Overdue and "Pay now" is offered (double payment).
- Add `countsAsMonthly(Payment $p)`: `Monthly` OR (`Initial` and `! isManuallyRecorded()`), i.e. platform checkout only. Walk-in manual lumps stay excluded, preserving the Aug 29 fix (ARCHITECTURE.md key decisions).
- `monthlyPaymentsFor($period)`: a platform Initial belongs to the **first** period (`tenancyStartDate()` month). `monthlyTransactions()` includes it. `otherCharges()` excludes it so `summary()['collected']` does not double count.
- `Reservation::tenancyStartDate()`: `target_move_in_date ?? tenant_confirmed_move_in_at ?? reservation_date` (the move-in date is optional at inquiry, so billing used to start on the inquiry date).
- Copy only: where the UI says "Due at move-in" = rent + deposit (`conversations/partials/chat-panel.blade.php:108-114`, `properties/show.blade.php:~1565`), say "Paid online: first month's rent" and, if there is a deposit, "Security deposit ₱X, settled directly with your landlord". No change to what anyone is charged or to escrow.

**B3. Abandoned checkout must not lock the tenant out.** Nothing ever clears a `Pending` payment; the guard at `Tenant\PaymentController.php:41-47` (rent `:153-160`) refuses a new session forever, and `agreements/show.blade.php:12` hides Pay Now whenever a Pending row exists.
- `config/rentals.php`: `checkout_stale_minutes` (30). `Payment::isStalePending()` (Pending, older than that).
- New `App\Services\PaymongoCheckout` (`status($id)`, `expire($id)`; `Http::fake`-able). In `ReconcilesPaymongoCheckout` add `clearAbandonedCheckout(Payment): bool`: reconcile first (if actually paid, settle and report "already paid"); if the session is expired, or `expire()` succeeds, delete the unpaid placeholder and return true; if neither can be confirmed return false (fail safe: never open a second session while the first might still pay).
- Call it in both checkout actions (web + API twins, initial and rent) in place of the unconditional refusal, only for stale Pending rows; a placeholder with no session id older than 5 minutes (crash between create and update) is just deleted.
- `agreements/show.blade.php`: `$hasPayment` ignores stale Pending rows; show "Your last payment attempt wasn't completed. Try again." above Pay Now.
- Show `payment` validation errors on `agreements/show.blade.php` (today only `agree`, `accept_tc`, `reason` render, so "could not create session" shows nothing).
- Safety net: in `PayMongoWebhookController::handleCheckoutPaid` and the reconcile trait, if the reservation is already Cancelled/Rejected, still mark the money Held (it is real) but notify admins (reuse the admin notification pattern used for `move_in_disputed`) so it is never silently stranded.

**B4. Block cancel/reject while money is held** (`Reservation.php`).
- `Reservation::cancellationBlockedReason(): ?string`: Held payment, or a Pending checkout younger than the stale window (money may be in flight) returns a plain-language reason ("Your payment is held by AbangananHub, so this can't be cancelled here. Message your landlord or use Report a Problem and an admin will resolve it"); otherwise null.
- `cancel()` and `reject()` return false when it is non-null (defense in depth) and run inside `DB::transaction` + `lockForUpdate` re-check (RULES.md Concurrency). `Admin\ReservationController::forceCancel/forceReject` are unchanged: admin can still resolve.
- `Tenant\ReservationController::cancel`, `Landlord\ReservationController::cancel/reject`, `Api\Tenant\ReservationController`, `Api\Landlord\ReservationController`: call the reason method first so the user gets the specific message instead of "can no longer be cancelled".
- Views: hide Cancel and show the one-line reason in `tenant/reservations/index.blade.php` (rows at `:186-196`, `:345-355`) and `landlord/reservations/index.blade.php` (`:377` and reject).

## C. Navigation dead-ends

- **Reservations list** (`tenant/reservations/index.blade.php`, mobile cards ~`:150-250`, desktop table ~`:255+`, details modal `:503-505`): show `target_move_in_date` as "Move-in" (`Flexible` if none) and keep the inquiry date as "Inquired" (today it prints `reservation_date`). Add `Reservation::tenantNextStep(): ?array` (label + route) used by **both** layouts: Pending Rental Agreement is "Review & sign" (`agreements.show`), Rental Agreement Signed is "Pay now" or "Open agreement" (`agreements.show`), Occupied is "Rent" (`tenancy.show`) and its Chat goes to `tenancy.chat`, Completed is "View tenancy" (`tenancy.show`). Inquiry/Negotiation Cancel gets the same `data-confirm` the later stages already use (`:173-179`, `:332-338`).
- **Chat to tenancy**: for a tenant whose reservation is Occupied/Completed add a "Tenancy & rent" link (`tenancy.show`) in the chat header (`conversations/partials/tenancy-panel.blade.php` / `chat-panel.blade.php`).
- **My Reports** (`tenant.reports.index`) is linked from nowhere: link it from `reports/create.blade.php` and as a tenant-only row next to "Report a Problem" in the account menu (`layouts/app.blade.php:~387`).
- **Notifications page** (`notifications/index.blade.php`): the "generic" branch (`~:268-283`) never uses `$n->link`, so payment/agreement/viewing/concern notifications dead-end. Show an "Open" button when `link` exists (the dropdown already does this, `partials/dropdown.blade.php:43`). The **Reservations tab** filters `notifiable_type = Reservation`, which only reviews ever set, so it is always empty: filter by `type` using a new `Notification::RESERVATION_TYPES` (`reservation, agreement, payment, payment_released, move_in_confirmation_due, move_in_disputed, turnover_overdue, concern`) in `NotificationController:28-30` and `Api\NotificationController:36-38`.
- Rent reminder link (`RentReminderNotifier.php:~63,147`) goes to the reservations list: point it at `tenancy.show`.

## D. Tests (new `tests/Feature/TenantSafetyTest.php`, `TenantPaymentRecoveryTest.php`, `RentLedgerInitialTest.php`, fixtures from `CreatesMarketplaceFixtures`)
- `?active=alert(1)` renders a safe value; report page lists no emails and only conversation partners.
- Deleting an account with a Held / Pending-Payout payment on a Cancelled reservation is refused.
- Stale Pending payment + `Http::fake` (expired / expire ok / expire fails / actually paid) gives retry allowed, allowed, refused, settled.
- `cancel()` / `reject()` refused with a Held payment, allowed without; admin force path still works.
- Ledger: online Initial settles the first month, `collected` not double counted, walk-in manual Initial lump still does not; rent frozen after landlord price edit; `tenantNextStep()` per status; notifications tab returns reservation-type rows.

## Verification
`php -l`; `php artisan migrate`; `php artisan view:cache` then `view:clear`; `php artisan test` (new + browse/budget/property suites stay green); tinker render of `/tenant/reservations`, `/tenant/tenancy/{id}`, `/notifications?tab=reservation`, `/reports`, `/conversations?active=1` for a throwaway tenant, deleting all test rows afterward. **Cannot verify in a browser** (list buttons at 375px, notifications Open button, chat link): hand the user a short checklist.

## Docs
`context/CHANGELOG.md` entry; ARCHITECTURE.md decisions-log rows (frozen rent, Initial = first month, cancel guard, stale-checkout recovery) and update the "No refund path" tradeoff note; RULES.md: never echo request input into `<script>` without `@js`; copy this plan to `plans/tenant-side-hardening.md`.

## Not in this pass (found and verified, deferred by scope choice)
Mobile and design polish: tenancy header buttons overflow at 375px (`tenant/tenancy/show.blade.php:98`), chat composer 13px zooms on iOS, reports page is a plain desktop table with off-palette colours, favorites cards and stale count, inconsistent status colours (Completed red), thin `font-normal` titles, invalid `w-4.5` classes, chat error swallowed on send, dark-mode gaps. Also: viewing slot double-booking (`ViewingRequest::slotProblem`), review/inquiry double-submit races, missing notifications (rent paid online, landlord-recorded payment, lease upload), no refund workflow (still a known tradeoff).
