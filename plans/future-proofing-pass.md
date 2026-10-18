> **Status (Oct 19 2026): implemented; full suite green (642 passed, 1 skipped); pending manual verification in the browser** (tenant-ID Show button, the unit-conflict message on Send agreement, admin chat bubbles, AI-search note, payout modal). Delete this file once that pass is done.

# Future-proofing pass after pulling `main` (Oct 19 2026)

## Context

User asked: check the freshly pulled `main` for changes that could cause problems later, and fix them, using
`context/CHANGELOG.md` as a guide. The changelog has a standing "found, not fixed" list (webhook lock, social-login
linking, admin chat bubble, status lists, Cloudinary orphans, missing notifications, double-submit races). Three
read-only audits then covered security/concurrency, schema/config/deploy, and behaviour/contracts. **Every item
below was re-checked in the current code before it went in.** User chose **all four groups** and, for the unit
conflict, **"block a second agreement"**.

Verified and **not** being changed (so they don't get re-raised):
- Account deletion vs `payments.payout_id RESTRICT`: a landlord can't be deleted while they own properties, which comes first. Not reachable.
- Landlord payments page loads all of one landlord's tenancies: bounded per landlord, left alone (noted in Known Tradeoffs).
- Landlord text reaching the AI rank prompt, `Admin\PaymentController` vs `confirmMoveIn` lock order: low risk, no data corruption.
- Money-path locks in `Admin\PaymentController::release`, `confirmMoveIn`, landlord payment `store`/`void`; AI-search XSS/SQL/rate-limit; public-identity resources: all checked and fine.

## Environment items no code change can fix (report to user, document in CHANGELOG/RULES)
1. **PHP 8.4 required.** `composer.json` says `^8.4` and the pulled `composer.lock` pins Symfony 8.1.x (`symfony/yaml 8.1.9` etc. need `>=8.4.1`). This machine runs **PHP 8.2.12** and its `vendor/` is the old Symfony 7.4. `composer install` / `update` here will fail, and CI (8.4) is the only place that matches. User must upgrade XAMPP's PHP to 8.4 before running composer. Do **not** run composer here.
2. `main` has no branch protection yet (GitHub setting; steps already in RULES.md).
3. MariaDB can freeze on the `cache` table (restart `mysqld`; implementation starts by starting it).
4. Mobile repo to do: tenant-detail fields on sign, nested `landlord.business_name`, drop removed unit fields, `kind` on messages (list already in CHANGELOG).

## How to execute
Four work packages with **disjoint files**; each may run as a parallel agent. Agents write code + tests and run `php -l`
only: **they must not run `php artisan test`** (the suite shares one MySQL test DB and `RefreshDatabase` tests would
drop each other's tables). The main session then starts MySQL, runs `view:cache`, the full suite and `npm run build`,
fixes fallout, applies the cross-cutting date-validation change, and writes the docs.

### Package A: money, reservations, unit exclusivity
Owns: `Tenant/PaymentController`, `Api/Tenant/PaymentController`, `Concerns/ReconcilesPaymongoCheckout`, `PayMongoWebhookController`, `Models/Payment`, `Models/Reservation`, `Landlord/ReservationController`, `Api/Landlord/ReservationController`, `Admin/PayoutController` + `admin/payouts/index.blade.php`, `Services/RentLedger`, `Services/RentReminderNotifier`.
| # | Problem (verified) | Fix |
|---|---|---|
| A1 | Checkout lock ignores a placeholder with no session id yet (`whereNotNull('paymongo_checkout_session_id')` inside the lock), so a double-click opens two PayMongo sessions; status/settled checks sit before the lock | Inside the lock: block on ANY Pending row (stale ones were already cleared by `clearStaleCheckouts`), re-check `rental_status` and `hasSettledInitialPayment()`. Same in the rent twins and the API |
| A2 | Webhook and poll fallback both read a stale `Pending` and `update()`, no lock: duplicate system messages/notifications, a replay could rewrite `Released` to `Held` | One `Payment::settlePending(array $attrs): ?Payment`: `DB::transaction` + `lockForUpdate` + re-check `Pending`, returns null if already settled. Both paths use it and post messages only when they settled it (observer is already `afterCommit`) |
| A3 | **Unit exclusivity.** Nothing stops two tenants getting agreements for one unit; both can sign, pay and be set Occupied | New `Reservation::unitHeldByAnother()` (another reservation on the unit at Pending Agreement, Signed, Reserved or Occupied). `advanceToPendingAgreement()` runs in a transaction, locks the `PropertyUnit` row, refuses if held, and reports why via `agreementBlockedReason()`; web + API landlord controllers show it. Checkout re-checks under the lock (before any money moves). `confirmMoveIn` / `markOccupied` / `ProcessMoveInDeadlines` refuse to set a unit Occupied if another reservation already is |
| A4 | My `cancel()`/`reject()` read the status before the lock and discard the locked row; `advanceToNegotiation` has no lock (double-click posts the message twice) | `refresh()` after the lock then re-check; wrap `advanceToNegotiation` in the same transaction pattern |
| A5 | Admin payout total is recomputed at click time; a payment released while the admin sends GCash is marked paid out but never sent | Form posts the total shown; under the existing lock compare, 409 with "amounts changed, review again" on mismatch |
| A6 | Rent reminders say "1 days overdue" for due tomorrow (Carbon float vs `=== 1`); ledger `$paid >= $expected` on float sums marks a fully paid month partial | `(int) round(...)` in `RentReminderNotifier::timing()`; compare `round(...,2)` in `RentLedger::periodStatus()` |
| A7 | Landlord/API reservation status lists omit `Reserved`/`Completed` (counts don't add up) | Build tabs/counts/filters from `Reservation::STATUSES` in these two controllers and their views |

### Package B: security and property lifecycle
Owns: `Auth/SocialiteController`, `PropertyController`, `Api/Landlord/PropertyWriteController`, `Models/Property`, `Models/UnitMedia`, `Landlord/ProfileController`, `Api/Landlord/ProfileController`, `Api/PropertyController`, `Landlord/TenancyController`, `landlord/tenancies/_tenant-details.blade.php`, `Models/TenantDetail`, `bootstrap/app.php`, `routes/web.php`, `VerificationController`.
| # | Problem (verified) | Fix |
|---|---|---|
| B1 | Social login links to any account with the same email, never checking it is verified (`SocialiteController.php:117`); a pre-registered unverified account is hijackable, and walk-in placeholders (`is_walk_in` / inactive) are linkable | Auto-link only when `email_verified_at` is set; refuse walk-in/inactive; otherwise redirect to login with "log in with your password and verify your email". Web callback and mobile `verifyToken`. The misleading "verified email" comment is corrected |
| B2 | Deleting a property (web `PropertyController::destroy:406`, API twin) has no guard; FKs cascade to reservations and payments, so live tenancies and Held escrow vanish. The unit delete already has the guard | `Property::deletionBlockedReason()`: any non-terminal reservation, any Held payment, any `pendingPayout()` payment. Both controllers refuse with the reason. On delete also purge unit images/videos (`UnitMedia::destroyFromCloudinary()`), property documents and lease files via a shared `Property::purgeFiles()`; Cloudinary failures logged, not fatal |
| B3 | Business page and public API show admin-hidden reviews and unpublished properties (`approvedProperties()` checks only `verification_status`) | `where('is_hidden', false)` on both review queries and the API average; `approvedProperties()` uses `Property::scopeLive()` |
| B4 | Landlord page embeds the tenant's full ID number in HTML (`@js($detail->id_number)`); a validation failure can flash it into the session; a changed `APP_KEY` makes the encrypted column throw on signing and several pages | Page carries only the masked value; "Show" fetches a new authorized JSON route (policy `viewTenantDetails`, `AuditLog` entry). `Exceptions::dontFlash` for `id_number` and the emergency-contact fields. `TenantDetail` decrypt failure returns null (treated as "not on file", tenant is asked again) |
| B5 | `ocrCheck` (route `web.php:78`) has no throttle or size cap, so any user can burn Vision quota | `throttle:10,1` and `max:5120` on `id_image` |
| B6 | Home `areaTiles()` fetches all images for 20 cities uncached | `Cache::remember` 10 min (same file, so lives here) |

### Package C: reliability and config
Owns: `Observers/PaymentObserver`, `MessageController`, `Api/MessageController`, `Services/OcrService`, `config/services.php`, `.env.example`, `config/cloudinary.php`, `config/ai_search.php`, `config/rentals.php`, `Services/AiSearch/AiSearchService`, `resources/js/ai-search.js`, `routes/console.php`, migration `2026_10_13_000100`, new `Support/BusinessTime`.
| # | Problem (verified) | Fix |
|---|---|---|
| C1 | `PaymentObserver:49` dispatches `PaymentStatusUpdated` unguarded: Reverb down turns the PayMongo webhook into a 500 and the "payment received" notification is never sent. `MessageController:27` and the API twin broadcast unguarded: a 500 after the row is saved makes the user retry and duplicate the message | Wrap in `try/catch (BroadcastException)` + `Log::warning`, the pattern `Conversation::broadcastQuietly` already uses (message sends reuse it, keeping `toOthers()` behaviour). Also add the missing "rent paid online" notification here (Pending to Paid on `Monthly`) |
| C2 | `OcrService:14` calls `env()` outside config: OCR silently dies after `config:cache` | `services.google_vision.key` in `config/services.php`, read with `config()` |
| C3 | `.env.example` documents `CLOUDINARY_API_KEY/SECRET` nothing reads and an empty `CLOUDINARY_URL=`; with an empty URL the config fallback is skipped and every upload fails. AI keys `AI_SEARCH_DAILY_CAP/TIMEOUT` missing; cap default is 8 in config but 600 in `AiSearchService:273`; `APP_ENV=local`/`APP_DEBUG=true` defaults unwarned | `config/cloudinary.php` uses `env('CLOUDINARY_URL') ?: ...`; `.env.example` lists the variables actually read, the AI keys with the free-tier note, and a production warning; one default for the cap |
| C4 | Migration `2026_10_13_000100`: `up()` unguarded (fails on a duplicate `(user_id, role)` or a re-run); `down()` drops the unique index the `user_id` FK relies on (error 1553, SCHEMA.md trap) | `hasIndex` guards; de-duplicate first; `down()` adds the plain `user_id` index before `dropUnique`. Safe: the migration has already run everywhere, so the edit only affects fresh DBs and rollbacks |
| C5 | Everything is UTC: the 08:00 reminders run at 16:00 Manila, escrow at 07:00; "today" validation rejects a payment dated today (Manila) before 08:00. Moving `app.timezone` would shift every stored timestamp | **Keep `app.timezone = UTC`.** `rentals.timezone = 'Asia/Manila'`; `->timezone()` on the four scheduled commands; `BusinessTime::today()`. Main session replaces the 9 `before_or_equal:today` / `after_or_equal:today` sites (`Api/Landlord/PaymentController`, `Api/Landlord/TenancyController`, `Api/Tenant/ReservationController`, `Landlord/DepositChargeController`, `Landlord/PaymentController`, `Landlord/TenancyController`, `Landlord/ViewingController`, `StoreWalkInTenantRequest`, `StoreReservationRequest`) at integration so no package edits another's file |
| C6 | AI search: `ai-search.js:156` lets a failing slow AI call replace good keyword results with an error page; the daily cap is spent before the call and never refunded; default timeout 40 s exceeds PHP's 30 s limit, so a provider outage becomes a fatal 500 and leaks the concurrency slot | JS keeps the shown results when `await smart` fails (shows a small "smarter reading unavailable" note); decrement the cap on driver failure; default timeout 20 s |

### Package D: consistency, notifications, cleanup
Owns: `Admin/ReservationController` + admin reservation views, `Tenant/ReservationController` + `tenant/reservations/index`, `admin/conversations/show`, `Landlord/PaymentController`, `Api/Landlord/PaymentController`, `Services/RentPaymentRecorder`, `Landlord/LeaseController`, `TenantRatingController` (web + API), `Http/Resources/PaymentResource`, `Api/Tenant/AgreementController`, `Api/Tenant/ReservationController`, `ConversationController`, `Api/ConversationController`, `Admin/UserController`, `ProfileController`, `Console/Commands/ProcessRentReminders`, `AiSearch/CandidateFinder`, `AppServiceProvider`, `.github/dependabot.yml`, `package.json`.
| # | Problem (verified) | Fix |
|---|---|---|
| D1 | Admin and tenant lists hide `Reserved`/`Completed` (filter silently ignored, totals mismatch); API `Tenant/AgreementController` 404s a past tenant that web allows | Build from `Reservation::STATUSES`; allow Completed on the API agreement view |
| D2 | Admin chat view renders system lines as an "Unknown" landlord bubble, empty inquiry summaries and concern cards as blank/plain bubbles | Branch on `kind` (system divider already exists in `tenancy-panel`) |
| D3 | Silent actions: landlord-recorded payment, lease upload, landlord rating a tenant | `Notification::notify` to the tenant, link to `tenancy.show` (rent paid online is in C1) |
| D4 | `PaymentResource` sends tenants `payout_reference`, `released_by`, `paymongo_*`; API agreement sign returns a bare 422 when tenant details are missing; API inquiry 500s for a tenant re-inquiring after a cancel (web already reopens); `ConversationController::store` `firstOrCreate` includes `unit_id` (unique-key 500) and the API lets a landlord pass any `tenant_id` and read that user's name and photo | Hide those fields unless viewer is landlord/admin; `needs_tenant_details` on agreement show/sign responses; copy the web reopen logic; match on the unique tuple; restrict `tenant_id` to users with a reservation or conversation with that landlord. **Re-verify each on implementation and skip any that does not reproduce** |
| D5 | Deleting a user leaves ID/selfie images on the private disk, the profile picture and business logo on Cloudinary | Purge in a shared helper on both delete paths (best effort, logged) |
| D6 | `ProcessRentReminders` loads every Occupied tenancy with `get()`; business-name index (`NameMatcher`) is never refreshed on rename/approve; AI candidates are an arbitrary 60 | `chunkById`; call `NameMatcher::flush()` from business/property saved+deleted observers; order candidates by rating then id |
| D7 | Repo drift: `@tailwindcss/vite` v4 installed while the build runs Tailwind 3 and Dependabot will propose the Tailwind 4 major | `npm uninstall @tailwindcss/vite`; `ignore` Tailwind majors in `.github/dependabot.yml`. **Not touched:** `composer.json`/lock (PHP 8.2 here, and editing would desync the lock), and teammates' tracked files (a 1.7 MB .docx, a 743 KB CSV, `TODO.md`, `update-coords.cjs`): list them for the team instead of deleting |

## Tests (each package writes its own; main session runs all)
`Payments/CheckoutDoubleClickTest` (placeholder without a session id blocks), `Payments/SettleOnceTest` (settling twice posts one message, a replay does not rewrite `Released`), `UnitExclusivityTest` (second agreement refused, checkout refused, no double Occupied), `PayoutPinTest`, `ReminderWordingTest`, `SocialLoginLinkTest` (Socialite faked: unverified and walk-in not linked, verified linked), `PropertyDeletionTest` (blocked with live reservation / Held / pending payout, allowed when clean, files purged), `PublicBusinessPageTest` (hidden review and unpublished property absent), `TenantIdRevealTest` (HTML has no full ID, route authorized + audited, undecryptable value tolerated), `BroadcastFailureTest` (broadcaster throws: webhook still 200, message saved once), `BusinessTimeTest`, status-list and resource tests. Existing suites (430 passing) must stay green.

## Verification
Start MySQL; `php -l` everything; `php artisan view:cache` then `view:clear`; `php artisan migrate` (no new schema, but C4 edits an old migration: also run `migrate:fresh` on the test DB path via the suite); full `php artisan test`; `npm run build`. **Cannot verify in a browser:** the Show-ID reveal, the unit-conflict message on the Send-agreement form, the admin chat bubbles, the AI "unavailable" note. Hand the user a short checklist. Do not run `composer`.

## Docs
`context/CHANGELOG.md` entry (what, why, the environment list above, "not verified"); ARCHITECTURE.md decisions rows (unit exclusivity, locked settle, business timezone kept UTC, ID never in HTML) and Known Tradeoffs update (PHP 8.4, no branch protection, payments page bound); RULES.md: `env()` only in `config/`, never embed secrets in HTML, one unit holder from agreement to move-in, scheduled times need `->timezone()`; copy this plan to `plans/future-proofing-pass.md` marked pending manual verification.
