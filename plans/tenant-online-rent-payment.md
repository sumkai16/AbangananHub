# Tenant online rent payment (GCash via PayMongo)

## Context

The landlord-side rent ledger (`landlord.tenancies.show`) already shows Overdue/Due periods, but the only way to settle one is the landlord manually recording an offline payment (Cash/GCash/Bank/Maya/Check). Tenants have no way to pay through the app itself — the nightly reminder job just notifies them a debt exists. This plan adds a "Pay Now" path for platform tenants (walk-ins can't log in, so they're unaffected and keep being paid offline) using the PayMongo GCash checkout already wired up for the initial reservation payment.

Per your answers: rent paid online settles **directly to Paid** (no escrow — escrow only ever protected the initial handover), it only covers the tenant's **single earliest unsettled period** (no multi-period payoff), and the entry point is a **new tenant-side rent ledger page**, since tenants currently have no equivalent of the landlord's tenancy view at all.

## What already exists (reused, not rebuilt)

- `App\Services\RentLedger` — derives periods/summary/balances from `payments`. Reused as-is.
- `Tenant\PaymentController::createCheckoutSession()` / `success()` — the PayMongo checkout pattern (lock reservation → placeholder `Payment` row → PayMongo `checkout_sessions` API → redirect → success page reconciles if the webhook hasn't landed yet). The new rent flow copies this shape rather than branching the existing method, because the two differ in Gate, description, amount source, and — critically — the terminal status (`Held` vs `Paid`).
- `PayMongoWebhookController::handleCheckoutPaid()` — currently hardcodes `status → Held` and an escrow-specific system message for every payment. This must branch on `payment_type` so a Monthly payment settles to `Paid` with different copy, while Initial payments keep working exactly as today.
- `landlord.tenancies.show` blade + `landlord\TenancyController::show()` — layout/query pattern mirrored for the tenant's read-only version.

## Changes

**1. `app/Policies/ReservationPolicy.php`**
Add `payRent(User $user, Reservation $reservation)`: `$reservation->tenant_id === $user->user_id && $reservation->rental_status === 'Occupied'`. Reuse the same check for viewing the tenant's own ledger page (name it `viewOwnTenancy` for clarity, identical body).

**2. `app/Http/Controllers/Tenant/TenancyController.php`** (new)
`show(Reservation $reservation)` — `Gate::authorize('viewOwnTenancy', ...)`, eager-load like the landlord version, pass `reservation`, `periods`, `otherCharges`, `summary`, and the single payable period (`RentLedger::for($reservation)->unsettledPeriods()->first()`) to a new view.

**3. `app/Http/Controllers/Tenant/PaymentController.php`**
Add `createRentCheckoutSession(Reservation $reservation)`:
- `Gate::authorize('payRent', $reservation)`.
- Resolve the payable period server-side via `RentLedger::for($reservation)->unsettledPeriods()->first()` — **never** trust a client-supplied period or amount. 404/back-with-error if nothing is unsettled.
- Same lock-and-placeholder pattern as `createCheckoutSession()`, but: `payment_type = 'Monthly'`, `billing_period = <period start>`, `amount = period balance` (not full rent — respects any partial offline payment already recorded), pending-check scoped to `reservation_id + billing_period` instead of just `reservation_id` (a tenant could theoretically have a stale Initial-payment pending row that shouldn't block rent).
- PayMongo line item description: `"{unit_name} — Rent for {period label}"`; `success_url` → new `payments.rent.success` route; `cancel_url` → tenant tenancy show page.

Add `rentSuccess(Reservation $reservation)`: same reconciliation pattern as `success()` (poll PayMongo if webhook hasn't updated the placeholder yet), but on completion sets `status = 'Paid'` (not `Held`) and posts `"{tenant} paid rent for {period label} online."`. Redirect target after confirmation is the tenant tenancy page, not an interim agreement page — reuse the existing `payments.pending` view's polling behavior, parameterized with a "go to ledger" link instead of "go to agreement".

**4. `app/Http/Controllers/PayMongoWebhookController.php`**
In `handleCheckoutPaid()`, branch on `$payment->payment_type`:
- `'Initial'` → existing behavior unchanged (`status: Held`, escrow message).
- `'Monthly'` → `status: Paid`, message `"{tenant} paid rent for {period label} online."` (format `billing_period` for the label).

**5. `resources/views/tenant/tenancy/show.blade.php`** (new)
Adapted from `landlord.tenancies.show`: same header/tiles/ledger table/other-charges table, but:
- No "Record payment" button, no "End tenancy" card (tenant can't do either).
- The row matching the resolved payable period gets a "Pay ₱{balance} now" button — a form posting to `tenant.payments.rentCheckout` — instead of just a status pill. Every other row stays read-only.
- Side column keeps unit info; drop the End-of-tenancy card entirely for tenants.

**6. `routes/web.php`** (inside the existing `tenant` prefix group)
```php
Route::get('/tenancy/{reservation}', [TenancyController::class, 'show'])->name('tenancy.show');
Route::post('/tenancy/{reservation}/pay-rent', [PaymentController::class, 'createRentCheckoutSession'])->name('payments.rentCheckout');
Route::get('/tenancy/{reservation}/rent-success', [PaymentController::class, 'rentSuccess'])->name('payments.rent.success');
```

**7. `resources/views/tenant/reservations/index.blade.php`**
On an `Occupied` reservation card, add a "View rent / Pay rent" link to `tenant.tenancy.show` (currently there's no such link at all — an Occupied reservation is a dead end for the tenant today).

## Verification

- `php artisan route:list --name=tenant` to confirm the three new routes register correctly and don't collide with `reservations/{reservation}`-style wildcards.
- Manual pass (per your usual testing preference — I'll build the fixtures, you run it):
  1. Seed/produce an Occupied platform tenancy with an Overdue period (walk-in tenants are out of scope here since they can't log in).
  2. As that tenant, open the new rent ledger page, confirm only the earliest unsettled period shows a Pay button and the amount matches its balance (not the full rent, if partially paid).
  3. Complete PayMongo sandbox GCash checkout, confirm the period flips to Paid, a system message posts, and the landlord's own ledger view reflects it immediately.
  4. Cancel a checkout mid-flow, confirm the placeholder `Payment` row doesn't block a retry.
  5. Re-run an Initial-payment checkout on a fresh reservation to confirm the webhook branch didn't regress the existing escrow flow.
