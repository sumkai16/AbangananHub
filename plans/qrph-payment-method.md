# Add QRPh as a payment method alongside GCash

## Context
Tenants currently only see GCash on the PayMongo checkout page for both the initial (escrowed) payment and monthly rent payments. QRPh (the Bangko Sentral interbank QR standard — works with any participating bank or e-wallet app, not just GCash) is supported by PayMongo's Checkout Sessions API as just another `payment_method_types` entry, so this is additive, not a new integration: no new API client, no new webhook event type, no new escrow logic. The webhook handler and `PaymentObserver` already key everything off `paymongo_checkout_session_id` and are payment-method-agnostic — they don't care which method the tenant picked on PayMongo's hosted page.

The one real wrinkle: `Payment.payment_method` is a hardcoded `'GCash'` written at placeholder-creation time, *before* the tenant has chosen a method on PayMongo's page. Once QRPh is offered as a second option, that value can be wrong. It needs to be corrected to whatever PayMongo actually settled with, once we know.

## Changes

**1. Migration — widen the `payment_method` enum**
New migration (pattern: `database/migrations/2026_07_24_000003_add_manual_recording_to_payments_table.php`) that runs `ALTER TABLE payments MODIFY payment_method ENUM('GCash','QRPh','Cash','Bank Transfer','Maya','Check','Other') NOT NULL`, with a `down()` reverting to the current list.

**2. `app/Http/Controllers/Tenant/PaymentController.php`**
In both `createCheckoutSession` and `createRentCheckoutSession`:
- `'payment_method_types' => ['gcash']` → `['gcash', 'qrph']` — PayMongo's hosted checkout then shows both as selectable tabs.
- Keep the placeholder's `'payment_method' => 'GCash'` as the initial guess (needed since the column is `NOT NULL` and we don't know the choice yet), but stop treating it as final.
- In `success()` and `rentSuccess()`, when the session-status poll confirms payment, also read the actual method the tenant used and update `payment_method` on the same `update()` call. PayMongo's checkout session response includes `data.attributes.payment_method_used` (their field naming — confirm exact key when implementing against a live sandbox response since PayMongo's docs are the source of truth here, not memory) which maps `gcash` → `'GCash'`, `qrph` → `'QRPh'`.

**3. `app/Http/Controllers/PayMongoWebhookController.php`**
`handleCheckoutPaid()` currently only reads `payment_intent.id` and `payments[0].id` off the webhook's embedded checkout-session resource. Add reading the same method field from `$resource['attributes']['payment_method_used']` (or equivalent) and include it in the `$payment->update([...])` call alongside `status`. This is the primary path (webhook fires before the tenant's browser redirect in most cases), so it's the one that must get this right; the `success()`/`rentSuccess()` poll-fallback in the controller is best-effort for the case where the webhook hasn't landed yet.

**4. `resources/views/payments/pending.blade.php`**
Line 14 hardcodes "Your GCash payment has been submitted." — change to method-neutral copy ("Your payment has been submitted.") since it's shown for both methods now.

**5. `context/PRD.md` line 33, 55**
Update "PayMongo GCash checkout" → "PayMongo GCash/QRPh checkout" and the sandbox note on line 55, per this repo's convention of keeping PRD.md in sync with shipped features.

## Out of scope / open question to confirm before or during implementation
- Whether the PayMongo sandbox/live account already has QRPh enabled — this is sometimes a separate toggle in PayMongo's merchant dashboard since QRPh settlement runs over a different rail than e-wallets. Worth checking `config/services.php` / the PayMongo dashboard before assuming the API call will just work.
- No UI is needed beyond what PayMongo's hosted checkout page renders — AbangananHub doesn't build its own payment-method picker.

## Verification
- Since this touches real money-adjacent flow, this needs manual testing in PayMongo's sandbox rather than automated tests: trigger both `createCheckoutSession` (initial payment) and `createRentCheckoutSession` (rent) as a test tenant, pick QRPh on PayMongo's hosted page, and confirm:
  - The webhook fires and updates `payment_method` to `'QRPh'` and status to `Held`/`Paid` correctly.
  - The ledger and landlord dashboard render the QRPh payment correctly wherever `payment_method` is displayed (grep for existing `payment_method` display usages before calling this done).
  - Falling back to GCash still works unchanged (regression check).
