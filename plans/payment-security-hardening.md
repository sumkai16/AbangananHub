# Payment security hardening

Status: code changes 1 and 2 implemented, pending manual verification. Config (§2) and the live payout test (item 14) still to do.

Source: payment-flow review. The money core (webhook signature + 5-min replay window, amount match,
row locks, escrow, step-up on release/refund/payout) is sound. This plan closes the small gaps.

## 1. Code changes (2 small ones)

| # | Change | File | Why |
|---|---|---|---|
| 1 | `paidAmountMatches()` fails closed when the payload has no amount: row stays `Pending`, admins are alerted. Today it logs a warning and settles. | `app/Services/PaymongoCheckout.php` | Never settle money we could not check. |
| 2 | Throttle `/webhooks/paymongo` (e.g. `throttle:120,1`). | `routes/web.php:498` | Bad-signature calls still cost HMAC work. |

Optional, later: store PayMongo event IDs (new table) to dedupe and audit. Today safety rests on the
`status !== 'Pending'` check plus row locks, which already works.

### Risk to check before change 1
If PayMongo test-mode payloads really omit `payments[].attributes.amount`, failing closed would stop
every payment settling. Step 1: send one test-mode checkout and inspect the logged payload. Only then
flip it. `alertAdminsAmountMismatch()` takes an int, so the missing-amount case needs its own alert text.

## 2. Production config (no code)

| Key | Set to |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `SESSION_ENCRYPT` | `true` |
| `CSP_MODE` | `enforce` (run report-only first and read the reports) |
| `PAYMONGO_WEBHOOK_SECRET` | the live secret, not the test one |
| `ADMIN_TWO_FACTOR` | `required` |

## 3. Manual test checklist (Axcee runs these)

Webhook:
1. POST to `/webhooks/paymongo` with no `Paymongo-Signature` header: expect 403.
2. POST with a made-up signature: expect 403.
3. Replay a real captured event more than 5 minutes later: expect 403.
4. Pay a test checkout, then resend the same event: payment settles once, no duplicate message or notification.
5. Hit the endpoint more than the throttle limit in a minute: expect 429 (after change 2).

Amount:
6. Pay a test checkout, then edit that `payments` row's amount in the DB before the webhook arrives: stays `Pending`, admins get an alert.
7. After change 1: a payload with no amount stays `Pending` and alerts admins.

Escrow and admin:
8. Move-in rent and deposit show `Held` after payment, and the landlord cannot release them.
9. Admin Release, Refund and Payout actions ask for the password again after 15 minutes idle.
10. Double-click Release: second click gets a 409, nothing is released twice.
11. A landlord cannot void a platform payment or one already paid out.

Config:
12. On the server, open a URL that throws an error: no stack trace shown.
13. Response headers show `Strict-Transport-Security` and an enforcing `Content-Security-Policy`.

Payout (needs real money, small amount):
14. Run `php artisan payouts:find-gcash-bic`, set `PAYMONGO_GCASH_BIC`, send one small payout, confirm it reaches GCash and the row ends `Sent`.

## Files expected to change
- `app/Services/PaymongoCheckout.php`
- `routes/web.php`
- `context/CHANGELOG.md`, `context/ARCHITECTURE.md` (note the fail-closed rule)
