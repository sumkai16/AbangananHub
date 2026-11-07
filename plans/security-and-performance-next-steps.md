# Security and performance: what is left (written Nov 3 2026)

Status: **not started.** The audit itself is done and committed (see `context/CHANGELOG.md`, Nov 3 2026). This file is the
list of what was found and not yet done, in the order agreed. When an item is finished **and Axcee has verified it in the
browser**, tick it here; when the whole file is done, delete it (git keeps it).

## Next up, in this order

### 1. Business-logic abuse tests (payments and agreements)
The fuzz and upload tests catch crashes and bad files. They do not catch an action that is *allowed but wrong*, which is the
real risk on a platform that holds escrow. Write feature tests that try each of these as the wrong person or in the wrong
order, and assert the answer is a refusal and the data did not change:
- [ ] Pay the same checkout twice (double submit, replayed PayMongo webhook): one payment, one release.
- [ ] Skip a status step: a tenant signs before the landlord sent the agreement; a unit moves to Occupied without the initial payment.
- [ ] A tenant approves their own agreement, confirms their own move-in as the landlord, or marks their own payment as received.
- [ ] A landlord confirms a payment claim that belongs to another landlord's reservation; voids or refunds another landlord's payment.
- [ ] Change an amount in the request (rent, deposit, `agreed_monthly_rent`, refund amount) and check the server ignores it or refuses it.
- [ ] Reserve a unit that is already reserved or occupied (race: two tenants at once).
- [ ] Act on a reservation in a terminal state (Cancelled, Completed): reopen, pay, sign.
- [ ] Admin-only actions (release, refund, payout approve, user status) refused for landlord and tenant.
- [ ] Webhook with a bad or missing signature is refused; a valid one for an unknown payment is ignored.
Read first: `context/RULES.md` → Money-Moving Code, Concurrency & State Transitions. Existing tests to extend: `tests/Feature/Payments/`.

### 2. The two N+1 fixes (from `composer perf`, dev database)
- [ ] `/landlord/dashboard` (59 queries) and `/landlord/payments` (38): `late_fee_waivers` and `lease_renewals` are loaded once per reservation (12 times each). Eager load them with the reservations. Expected: about 24 fewer queries per page.
- [ ] `/admin/dashboard` (27): `users` is loaded 4 times (`select * from users where user_id in (?)`).
- [ ] `/landlord/reservations`: the same `count(*)` runs 4 times.
Measure with `composer perf` before and after; compare query counts, not milliseconds.

### 3. Production hardening (do before going live)
- [ ] `APP_DEBUG=false` and `APP_ENV=production` on the server (debug pages leak internals).
- [ ] Check HTTPS, the cookie flags (`SESSION_SECURE_COOKIE=true`, `SameSite`), and `curl -I` for the headers `SecurityHeaders` sends.
- [ ] Content-Security-Policy: needs nonces first (inline Alpine and Blade `<script>`). Start in report-only mode.
- [ ] `php artisan config:cache route:cache view:cache event:cache` on deploy; `composer install --no-dev --optimize-autoloader`; restart PHP-FPM after each deploy if OPcache validates no timestamps. Runbook: `plans/hostinger-vps-deployment.md`.

### 4. Mobile app
- [ ] The API lists now return one page (20 by default) with a `meta` block. Update the app to read `meta` and request `?page=`, or users with more than 20 conversations, favorites, payments or units see only the first 20. See `plans/mobile-api-gaps.md`.

## Smaller, when there is time
- [ ] Search bars on Payments, Properties, Favorites and Units still use the old card style (Reservations and Tenants match Units now).
- [ ] Browse search uses `LIKE '%…%'`, which cannot use an index. Fine today; move to a `FULLTEXT` index when listings grow.
- [ ] A client file name such as `page.html` is accepted for an image and shown as the display name (the file itself is stored as `<random>.png` and served as `image/png`). Harmless, but the display name could be forced to the real extension.
- [ ] Pint reports old style issues in about 20 controllers that were left alone on purpose. One separate cleanup commit.
- [ ] Manual pass of the 8-step guide in the CHANGELOG (Nov 3 2026 entry): phone-width search bars, paging, the Lease policies modal, the landlord application with a real camera photo.

## What we can honestly tell a panel
Done and tested: SQL injection (none), stored XSS (none), about 40 crashes from hostile input fixed, uploads (30 disguised
files refused, ID photos now checked), security headers, access control on file routes, `composer audit` and `npm audit`
clean. **Not done:** an independent penetration test, business-logic abuse (item 1 above), CSP, the production checks (item 3).
