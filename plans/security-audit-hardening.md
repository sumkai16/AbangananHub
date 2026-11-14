# Security & Audit Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Phase 1 (Tasks 1-5) built, verified in the browser and committed Nov 14 2026 (`29637de`). Phases 2-4 not started. D1 (block admin API tokens) is already in.

**Goal:** Record sign-ins, failures and access denials; protect accounts (2FA for admins, step-up password, new-sign-in alerts, session control, leaked-password block); and make the admin audit log tamper-evident, filterable and exportable.

**Architecture:** A new append-only `auth_events` table (separate from `audit_logs`, so attacker-generated noise never buries the admin trail) fed by Laravel auth event listeners plus a few explicit calls. One `AuthEventRecorder` service owns every write. Admin 2FA and step-up confirmation are middleware on the admin route group, so every login path (password, Google/Facebook, webview) is covered without touching each one. `audit_logs` gains an HMAC hash chain checked by an artisan command and a badge on the log page.

**Tech Stack:** Laravel 12, MariaDB, Alpine, Tailwind (token hexes only), `pragmarx/google2fa` (new, TOTP), `bacon/bacon-qr-code` (already installed, QR SVG), existing `public/js/modal-confirm.js`.

**Spec:** This conversation (Nov 14 2026): the six "security rules" + "User / Admin activity logs" recommendation Axcee shared, and suggestions #1–#9 that followed. Source of truth for each item is the task that implements it (coverage table at the bottom).

## Decisions to confirm (before Task 1)

| # | Decision | Recommended |
|---|---|---|
| D1 | Admin accounts cannot sign in through the mobile API (`Api\AuthController`), because a Sanctum token skips the web 2FA check. | Yes, block, with message "Admins sign in on the web." |
| D2 | 2FA is **required** for every Admin (forced setup on next admin page load). Landlords/tenants: not in this plan. | Required for Admin |
| D3 | New-sign-in alert email goes to **Admin and Landlord** accounts only (they hold money/listings). | Admin + Landlord |
| D4 | `audit_logs` is never pruned (pruning would break the hash chain). `auth_events` is kept **90 days**. | As stated |
| D5 | Step-up password confirmation lasts **15 minutes**. | 15 min |
| D6 | Hash chain uses a dedicated `AUDIT_CHAIN_KEY` env value (falls back to `APP_KEY` if missing) so rotating `APP_KEY` never breaks the chain. | Dedicated key |
| D7 | No IP geolocation (would need a GeoIP database). Alerts and history show browser, OS and IP only. | No geolocation |

## Global Constraints

- Every `audit_logs` write goes through `AuditLog::record()` inside the caller's `DB::transaction()` (RULES.md, Concurrency).
- Every `auth_events` write goes through `AuthEventRecorder`. A failed write is caught, `Log::warning`'d and swallowed: logging must never block or break a login (same rule as `property_activities`, ARCHITECTURE.md).
- Never store a password, reset token, 2FA secret, recovery code or session ID in `auth_events.metadata` or `audit_logs.metadata`.
- No raw Tailwind colour utilities; token hexes only (`#060D26`, `#5B6A8E`, `#E2E4EC`, `#F7F8FC`, `#FF8A66` CTA with navy text, hover `#E96F4F`, coral text `#B35A3D`). DESIGN.md §11b.
- Tenant/landlord screens (Task 8 settings section) are mobile-first at 375px. Admin screens stay desktop-oriented.
- Copy: plain words, active voice. Buttons say exactly what happens.
- Migrations that add columns get an immediate `$fillable` audit on the model (RULES.md).
- Performance: the login request gains at most 2 queries (one insert, one "known device" lookup). Emails are sent with `defer()`, never inline on the request.
- Each phase ends with Axcee's manual browser pass before commit (`test-before-commit`). Never commit without it.

## Review Focus

1. **`Failed` event carries the typed password** in `$event->credentials`. The recorder must read only `email`. → Test in Task 2: `test_failed_login_never_stores_password`.
2. **MariaDB `JSON` columns may reorder object keys**, so a hash computed over `json_encode($metadata)` can fail verification on read-back. Canonicalise (recursive `ksort`) before hashing on both write and verify. → Test in Task 12: `test_chain_verifies_when_metadata_keys_are_unordered`.
3. **Social and webview logins skip the password screen.** An admin with 2FA must still hit the challenge. → Test in Task 11: `test_social_login_admin_is_sent_to_two_factor_challenge`.
4. **A bot hammering one IP fills `auth_events`.** Cap writes per IP. → Test in Task 2: `test_failed_logins_are_capped_per_ip`.
5. **CSV injection:** a user named `=HYPERLINK(...)` opened in Excel. Prefix cells starting with `=`, `+`, `-`, `@`, tab or CR with `'`. → Test in Task 13: `test_export_escapes_formula_cells`.

---

## Phase 1 — Account & security events (foundation)

### Task 1: `auth_events` table, model, recorder

**Files:**
- Create: `database/migrations/2026_11_15_000100_create_auth_events_table.php`
- Create: `app/Models/AuthEvent.php`
- Create: `app/Services/Security/AuthEventRecorder.php`
- Create: `app/Support/UserAgentSummary.php`
- Modify: `config/security.php` (add keys below)
- Test: `tests/Feature/Security/AuthEventRecorderTest.php`

**Interfaces:**
- Produces:
  - Table `auth_events`: `event_id` PK; `user_id` nullable FK → `users.user_id` ON DELETE SET NULL; `email` string(255) nullable (the address typed, kept even when no user matched); `event` string(40); `channel` string(20) (`web`, `google`, `facebook`, `webview`, `api`); `ip_address` string(45) nullable; `user_agent` string(255) nullable (truncated); `device_id` char(36) nullable; `metadata` json nullable; `created_at` timestamp. No `updated_at`. Indexes: `(user_id, created_at)`, `(event, created_at)`, `(ip_address, created_at)`, `created_at`.
  - `AuthEvent::EVENTS` label map: `login`, `login_failed`, `lockout`, `logout`, `password_reset`, `password_changed`, `email_changed`, `social_linked`, `social_unlinked`, `two_factor_enabled`, `two_factor_disabled`, `two_factor_failed`, `recovery_code_used`, `access_denied`, `sessions_revoked`, `password_confirm_failed`.
  - `AuthEvent::WARNING_EVENTS` = `login_failed`, `lockout`, `two_factor_failed`, `access_denied`, `password_confirm_failed`.
  - `AuthEvent` uses `MassPrunable`; `prunable()` = older than `config('security.auth_events_retention_days')`.
  - `AuthEventRecorder::record(string $event, ?User $user = null, ?string $email = null, array $metadata = [], ?string $channel = null): ?AuthEvent`. Fills IP, UA, `device_id` from the current request. Channel defaults to `web`. Returns null when capped or on failure.
  - `UserAgentSummary::from(?string $ua): string` returns e.g. `"Chrome on Android"`. A small regex table, no package.
  - Config: `security.auth_events_retention_days` = 90, `security.auth_events_ip_cap_per_minute` = 30, `security.device_cookie` = `ah_device`.

- [ ] **Step 1: Write failing tests** `test_records_event_with_request_context` (asserts `ip_address`, `user_agent`, `channel = 'web'`); `test_write_failure_is_swallowed` (drop the table, call record, expect null and no exception); `test_prunes_rows_older_than_retention` (91-day-old row deleted by `model:prune`, 89-day-old row kept); `test_user_agent_summary` (3 UA strings → expected labels).
- [ ] **Step 2: Run** `php artisan test --filter=AuthEventRecorderTest`. Expected: FAIL (class not found).
- [ ] **Step 3: Implement** the migration, model (`$fillable`, `metadata` array cast, `UPDATED_AT = null`, `user()` BelongsTo), recorder (try/catch around the insert, `Log::warning` on failure), UA summary, and config keys. Add `AuthEvent::class` to the `model:prune` schedule in `routes/console.php` next to `AiSearchLog`.
- [ ] **Step 4: Run** the same command. Expected: PASS.

### Task 2: Listeners and explicit recording points

**Files:**
- Create: `app/Listeners/Security/RecordAuthEvents.php` (one subscriber class)
- Create: `app/Http/Middleware/EnsureDeviceCookie.php`
- Modify: `bootstrap/app.php` (append `EnsureDeviceCookie` to the `web` group; register subscriber if not auto-discovered)
- Modify: `app/Http/Controllers/Auth/PasswordController.php` (`password_changed`)
- Modify: `app/Http/Controllers/ProfileController.php:59` (`email_changed`, metadata `{from, to}`)
- Modify: `app/Http/Controllers/Auth/SocialiteController.php:161,189,209` (`social_linked` / `social_unlinked`, metadata `{provider}`)
- Modify: `app/Http/Controllers/Api/AuthController.php:33,53,87` (`login` / `login_failed` with channel `api`; D1 admin block)
- Test: `tests/Feature/Security/AuthEventListenersTest.php`

**Interfaces:**
- Consumes: `AuthEventRecorder::record()` (Task 1).
- Produces: `EnsureDeviceCookie` sets a random UUID cookie named `config('security.device_cookie')` (1 year, httpOnly, secure in production, sameSite lax) when missing. `AuthEventRecorder` reads it as `device_id`.
- Subscriber maps: `Login` → `login`; `Failed` → `login_failed` (email only, `user_id` when the email matched); `Lockout` → `lockout`; `Logout` → `logout`; `PasswordReset` → `password_reset`. Channel from the matched route: the `auth/{provider}/callback` route (`SocialiteController@callback`) → its `{provider}` parameter, `auth.webviewLogin` → `webview`, else `web`.
- IP cap: `RateLimiter` key `auth-events:{ip}`, max `security.auth_events_ip_cap_per_minute` **for `WARNING_EVENTS` only**. Successful logins are never capped.

- [ ] **Step 1: Write failing tests**
  - `test_password_login_records_login_event`: post `/login` with valid creds; assert 1 `login` row for the user, channel `web`.
  - `test_failed_login_never_stores_password`: post `/login` with password `S3cret-Typed!`; assert a `login_failed` row exists and `AuthEvent::where('metadata','like','%S3cret%')->orWhere('email','like','%S3cret%')->count() === 0`.
  - `test_failed_logins_are_capped_per_ip`: 40 failed posts from one IP; assert `login_failed` count ≤ 30.
  - `test_lockout_is_recorded`: 6 failed posts; assert one `lockout` row.
  - `test_password_change_and_email_change_are_recorded`.
  - `test_api_login_is_recorded_with_api_channel`.
  - `test_admin_cannot_get_api_token` (D1): expect 403 JSON `{"message":"Admins sign in on the web."}`.
- [ ] **Step 2: Run** `php artisan test --filter=AuthEventListenersTest`. Expected: FAIL.
- [ ] **Step 3: Implement** the subscriber, device-cookie middleware and the explicit calls above.
- [ ] **Step 4: Run** the same command plus `--filter=Auth` (existing auth tests). Expected: all PASS.

### Task 3: Record access denials

**Files:**
- Modify: `bootstrap/app.php` (`withExceptions`: a `report` callback for `AccessDeniedHttpException` and `AuthorizationException`)
- Test: `tests/Feature/Security/AccessDeniedLoggingTest.php`

**Interfaces:**
- Consumes: `AuthEventRecorder::record('access_denied', $user, metadata: ['path' => $request->path(), 'method' => ...])`.
- Rule: record only when the request path starts with `admin/` **or** the user is signed in. Anonymous 403s on public pages are not recorded (noise). The existing 403 response is unchanged.

- [ ] **Step 1: Write failing tests** `test_tenant_hitting_admin_route_is_recorded` (tenant GETs `/admin/users`, 403, one `access_denied` row with `path = admin/users`); `test_anonymous_public_403_is_not_recorded`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.

### Task 4: Activity area with two tabs and shared filters

**Files:**
- Create: `app/Http/Controllers/Admin/SecurityEventController.php` (`index`)
- Create: `resources/views/admin/activity/_tabs.blade.php`
- Create: `resources/views/admin/security-events/index.blade.php`
- Modify: `app/Http/Controllers/Admin/AuditLogController.php` (add `role` filter on the actor)
- Modify: `resources/views/admin/audit-logs/index.blade.php` (include tabs, role filter)
- Modify: `resources/views/layouts/admin.blade.php:184` (sidebar label "Audit Logs" → "Activity Logs", active for both routes)
- Modify: `routes/web.php` (`GET /admin/security-events` → `admin.security-events.index`)
- Test: `tests/Feature/Security/SecurityEventsPageTest.php`

**Interfaces:**
- Tabs: **Admin actions** (`admin.audit-logs.index`) and **Account & security** (`admin.security-events.index`), links styled like the property page tabs.
- Security-events filters (query string): `search` (name or email, matches `auth_events.email` or the joined user), `role` (`Admin|Landlord|Tenant`, via `whereHas('user.roles')`), `event` (key of `AuthEvent::EVENTS`), `from`, `to`. 25 per page, `withQueryString()`. Eager-load `user.roles`.
- Stat cards (same layout as audit page): Sign-ins today, Failed today, Lockouts (range), Access denied (range).
- Rows: time, person (link to `admin.users.show` when `user_id` exists, else the typed email), event chip via `<x-admin.status tone=...>` (amber for `WARNING_EVENTS`, green for `login`, neutral otherwise), `UserAgentSummary`, IP.

- [ ] **Step 1: Write failing tests** `test_admin_sees_security_events`; `test_filters_by_role_and_event` (seed a tenant `login` and a landlord `login_failed`; `?role=Landlord&event=login_failed` shows only the landlord row); `test_non_admin_gets_403`; `test_audit_log_role_filter`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.

### Task 5: Sign-in history on the admin user page

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php` (`show`: `$recentAuthEvents` = last 8 `auth_events` for the user)
- Modify: `resources/views/admin/users/show.blade.php` (Security card: "Recent sign-ins" list under the reset-link button, plus a "View all" link to `admin.security-events.index?search={email}`)
- Test: extend `tests/Feature/Security/SecurityEventsPageTest.php` with `test_user_page_shows_recent_sign_ins`.

- [ ] **Step 1: Write the failing test.**
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Phase 1 manual check by Axcee, then commit** (see Manual checklist, Phase 1).

---

## Phase 2 — Account protection

### Task 6: Block known-leaked passwords

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (`Password::defaults()`)
- Modify: `app/Http/Controllers/Admin/UserController.php` (`store` uses `Rules\Password::defaults()` instead of `'min:8'`)
- Test: `tests/Feature/Security/LeakedPasswordTest.php`

**Interfaces:**
- `Password::defaults(fn () => app()->runningUnitTests() ? Password::min(8) : Password::min(8)->uncompromised())`. Unit tests skip the network call. The leaked-password test opts in by binding the rule explicitly with `Http::fake()`.
- `uncompromised()` sends only the first 5 characters of the SHA-1 hash (k-anonymity) and passes when the API is down, so registration never breaks on an outage.

- [ ] **Step 1: Write the failing test** `test_breached_password_is_rejected_on_reset` (fake the `api.pwnedpasswords.com/range/*` response containing the suffix; expect a validation error on `password`).
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.

### Task 7: New sign-in alert email

**Files:**
- Create: `app/Mail/NewSignInMail.php`, `resources/views/emails/new-sign-in.blade.php`
- Create: `app/Services/Security/NewDeviceDetector.php`
- Modify: `app/Listeners/Security/RecordAuthEvents.php` (on `Login`, after recording)
- Test: `tests/Feature/Security/NewSignInAlertTest.php`

**Interfaces:**
- `NewDeviceDetector::isNew(User $user, ?string $deviceId): bool`. True when the user has at least one earlier `login` event (so not first-ever sign-in) **and** none with this `device_id` in the last 90 days.
- Sent only to D3 roles. Sent with `defer(fn () => Mail::to($user->email)->send(...))`.
- Email copy. Subject: "New sign-in to your AbangananHub account". Body: time (`rentals.timezone`), `UserAgentSummary`, IP, the line "If this was you, you can ignore this email.", and a button **Reset my password** linking to `route('password.request')`.

- [ ] **Step 1: Write failing tests** with `Mail::fake()`: `test_first_ever_login_sends_nothing`; `test_known_device_sends_nothing`; `test_new_device_landlord_gets_alert`; `test_tenant_gets_no_alert` (D3).
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.

### Task 8: Where you're signed in, and signing out other devices

**Files:**
- Create: `app/Services/Security/SessionManager.php`
- Create: `app/Http/Controllers/Account/SessionController.php` (`destroy`, `destroyOthers`)
- Create: `resources/views/profile/partials/sessions.blade.php` (mobile-first)
- Modify: `resources/views/profile/edit.blade.php` (include partial)
- Modify: `resources/views/admin/profile/edit.blade.php` (include the same partial)
- Modify: `app/Http/Controllers/Admin/UserController.php` + `resources/views/admin/users/show.blade.php` (admin can **Sign out everywhere** for a user)
- Modify: `routes/web.php`: `DELETE /account/sessions/{id}` → `account.sessions.destroy`; `DELETE /account/sessions` → `account.sessions.destroyOthers`; `POST /admin/users/{user}/sign-out-everywhere` → `admin.users.signOutEverywhere`
- Test: `tests/Feature/Security/SessionManagementTest.php`

**Interfaces:**
- `SessionManager::forUser(User $user, ?string $currentId): Collection` returns `{id, device (UserAgentSummary), ip, last_active (Carbon), is_current}` from the `sessions` table, plus Sanctum tokens as `{id: 'token:'.$id, device: 'Mobile app · '.$name, ...}`.
- `SessionManager::revokeOthers(User $user, string $keepId): int` deletes the user's other `sessions` rows, deletes all their Sanctum tokens, and cycles `remember_token` so remember-me cookies on other devices stop working. Records `sessions_revoked`.
- `SessionManager::revokeAll(User $user): int` is used by the admin action. Writes `AuditLog::record('user.sign_out_everywhere', ...)` inside a transaction and adds the label to `AuditLog::ACTION_LABELS`.
- `destroy` and `destroyOthers` require the current password in the request (field `password`), validated with `current_password`.

- [ ] **Step 1: Write failing tests** `test_lists_current_and_other_sessions`; `test_sign_out_others_keeps_current_session`; `test_cannot_delete_someone_elses_session` (404); `test_admin_sign_out_everywhere_is_audited`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.

### Task 9: Suspicious-activity alerts to admins

**Files:**
- Create: `app/Services/Security/SuspiciousActivityMonitor.php`
- Modify: `app/Services/Security/AuthEventRecorder.php` (call the monitor after a `WARNING_EVENTS` write)
- Test: `tests/Feature/Security/SuspiciousActivityTest.php`

**Interfaces:**
- `SuspiciousActivityMonitor::inspect(AuthEvent $event): void`. Rules:
  - ≥ 5 `login_failed` for the same `email` in 15 min.
  - Any `lockout` on an account with the Admin role.
  - ≥ 3 `access_denied` by the same `user_id` in 10 min.
  - Any `two_factor_failed` ×3 in 10 min.
- Each rule notifies every Admin through `Notification::notify($adminId, 'account', 'Security alert', <sentence>, route('admin.security-events.index', [...filters]))`. Dedupe with `Cache::add("sec-alert:{rule}:{subject}", 1, now()->addHour())`.
- Example sentence: "5 failed sign-ins for maria@example.com in 15 minutes."

- [ ] **Step 1: Write failing tests** `test_five_failures_alert_admins_once` (7 failures → 1 notification per admin); `test_admin_lockout_alerts`; `test_below_threshold_is_quiet`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.
- [ ] **Step 5: Phase 2 manual check by Axcee, then commit.**

---

## Phase 3 — Admin hardening

### Task 10: Re-enter password before high-risk admin actions

**Files:**
- Create: `app/Http/Middleware/RequireRecentPassword.php` (alias `recent.password`)
- Create: `app/Http/Controllers/Admin/StepUpController.php` (`status`, `confirm`)
- Modify: `public/js/modal-confirm.js` (support `data-requires-password`)
- Modify: `resources/views/components/confirm-modal.blade.php` (optional password field and inline error)
- Modify: `routes/web.php` (apply the middleware; add `GET /admin/step-up` → `admin.stepUp.status`, `POST /admin/step-up` → `admin.stepUp.confirm` with `throttle:5,1`)
- Modify: the forms behind the guarded routes (add `data-requires-password`): `admin/payments/partials/_actions`, `admin/payouts/partials/*`, deposit-charge void modal, `admin/users/show` (delete, status), `admin/users/_form`, settings page
- Test: `tests/Feature/Security/StepUpTest.php`

**Interfaces:**
- Guarded routes: `admin.payments.release`, `admin.payments.refund`, `admin.payouts.markPaidOut`, `.retry`, `.approveAccount`, `.pause`, `.resume`, `admin.deposit-charges.void`, `admin.users.store`, `.update`, `.updateStatus`, `.destroy`, `.signOutEverywhere`, `admin.settings.update`.
- Session key `auth.step_up_at` (int timestamp). Fresh when `now - step_up_at < config('security.step_up_seconds')` (900, D5).
- Middleware when stale: JSON → 423 `{"needs_password": true}`. Otherwise `back()->with('error', 'Confirm your password, then try again.')`. The server is the real gate; the JS only makes it smooth.
- `StepUpController::status` → `{"fresh": bool}`. `confirm` accepts `password` **or**, when 2FA is enabled, `code` (TOTP via Task 11's `TwoFactor::verify`). On failure it records `password_confirm_failed` and returns 422.
- JS: on submit of a form with `data-requires-password`, fetch status. If fresh, use the normal confirm flow. If stale, show the modal with a password field; POST confirm; on 200, `form.submit()`.

- [ ] **Step 1: Write failing tests** `test_stale_release_is_blocked` (no step-up, POST release → redirect with error, payment unchanged); `test_release_works_after_step_up`; `test_step_up_expires_after_15_minutes` (`travel(16)->minutes()`); `test_wrong_password_records_event`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** plus the existing admin payment/payout tests (they must set `auth.step_up_at` in setup; add a `withStepUp()` helper to `tests/TestCase.php`). Expected: all PASS.

### Task 11: Two-factor sign-in for admins

**Files:**
- Run: `composer require pragmarx/google2fa`
- Create: migration `2026_11_15_000200_add_two_factor_to_users.php`: `two_factor_secret` text nullable (encrypted cast), `two_factor_recovery_codes` text nullable (encrypted JSON array of bcrypt hashes), `two_factor_confirmed_at` timestamp nullable, `two_factor_last_step` unsigned bigint nullable (replay guard)
- Modify: `app/Models/User.php` (`$fillable`/`$hidden`/casts, `hasTwoFactor(): bool`)
- Create: `app/Services/Security/TwoFactor.php`
- Create: `app/Http/Controllers/Auth/TwoFactorChallengeController.php` (`show`, `store`)
- Create: `app/Http/Controllers/Admin/TwoFactorSetupController.php` (`show`, `store` confirm, `regenerateCodes`, `destroy`)
- Create: `app/Http/Middleware/EnsureTwoFactor.php` (alias `two-factor`, on the admin group after `admin`)
- Create views: `auth/two-factor-challenge.blade.php`, `admin/security/two-factor.blade.php`
- Modify: `routes/web.php`, `routes/auth.php`
- Test: `tests/Feature/Security/TwoFactorTest.php`

**Interfaces:**
- `TwoFactor::newSecret(): string`; `qrSvg(User $u, string $secret): string` (bacon, issuer `AbangananHub`); `verify(User $u, string $code): bool` (window ±1 step, rejects a step ≤ `two_factor_last_step` and stores the used step); `generateRecoveryCodes(User $u): array` (8 plain codes `XXXX-XXXX`, stores hashes, returns plain once); `useRecoveryCode(User $u, string $code): bool` (removes the hash on success, records `recovery_code_used`).
- Session flag `auth.two_factor_passed` = user_id. Set on a successful challenge, cleared on logout (session invalidate already does it).
- `EnsureTwoFactor`:
  - Admin with `hasTwoFactor()` and no flag → redirect `two-factor.challenge`.
  - Admin without 2FA and `config('security.admin_two_factor') === 'required'` (D2) → redirect `admin.security.two-factor` (setup page is exempt).
  - Covers password, social and webview logins in one place.
- Challenge: `throttle:5,1`. A wrong code records `two_factor_failed`. Accepts a TOTP or a recovery code ("Use a recovery code instead" toggle).
- Setup page: QR plus a manual key, a "Enter the 6-digit code" confirm field, then show the recovery codes once with a Copy button. Disabling 2FA requires a step-up (Task 10) and is blocked while `required`. Enable and disable write `two_factor_enabled` / `two_factor_disabled` auth events **and** an `AuditLog` row.

- [ ] **Step 1: Write failing tests**
  - `test_admin_without_2fa_is_forced_to_setup`.
  - `test_admin_with_2fa_must_pass_challenge`.
  - `test_social_login_admin_is_sent_to_two_factor_challenge` (`Auth::login($admin)` then GET `admin.dashboard` → redirect to challenge).
  - `test_code_cannot_be_replayed`.
  - `test_recovery_code_works_once`.
  - `test_challenge_is_throttled`.
  - `test_tenant_is_unaffected`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.** The seeded local admin will be forced into setup; document this in the manual checklist.
- [ ] **Step 4: Run** plus the whole suite (`php artisan test`). Admin feature tests need a `actingAsAdmin()` helper in `tests/TestCase.php` that also sets the 2FA session flag. Expected: all PASS.
- [ ] **Step 5: Phase 3 manual check by Axcee, then commit.**

---

## Phase 4 — Audit integrity & operations

### Task 12: Tamper-evident audit log (hash chain)

**Files:**
- Create: migration `2026_11_15_000300_add_hash_chain_to_audit_logs.php`: `prev_hash` char(64) nullable, `row_hash` char(64) nullable, index on `row_hash`. Backfill existing rows in `log_id` order inside the migration.
- Create: `app/Services/Security/AuditChain.php`
- Create: `app/Console/Commands/VerifyAuditChain.php` (`audit:verify`)
- Modify: `app/Models/AuditLog.php` (`record()` computes the hash; add `prev_hash`, `row_hash` to `$fillable`)
- Modify: `config/security.php` (`audit_chain_key` = `env('AUDIT_CHAIN_KEY', env('APP_KEY'))`), `.env.example`
- Modify: `resources/views/admin/audit-logs/index.blade.php` (badge "Log intact · checked {time}" or "Log altered at entry #N" from cache)
- Modify: `routes/console.php` (`audit:verify` daily at 03:30; on failure, notify admins via `Notification::notify`)
- Test: `tests/Feature/Security/AuditChainTest.php`

**Interfaces:**
- `AuditChain::payload(array $row): string` returns the canonical JSON of `prev_hash, actor_id, actor_name, actor_email, action, auditable_type, auditable_id, summary, reason, metadata (recursive ksort), ip_address, created_at (Y-m-d H:i:s)`.
- `AuditChain::hash(array $row): string` = `hash_hmac('sha256', payload, key)`.
- `AuditChain::verify(): array{ok: bool, checked: int, broken_at: ?int}` walks rows with `chunkById(1000)`.
- `record()`: inside `DB::transaction` (nested is fine), read `AuditLog::lockForUpdate()->orderByDesc('log_id')->value('row_hash')`, set `created_at` explicitly, compute, insert. The lock serialises concurrent writers.
- The verify result is cached as `audit-chain:last` for the page badge.

- [ ] **Step 1: Write failing tests**
  - `test_new_rows_chain_to_previous`.
  - `test_verify_passes_on_untouched_log`.
  - `test_verify_detects_edited_summary` (raw `DB::table` update → `broken_at` = that id).
  - `test_verify_detects_deleted_row`.
  - `test_chain_verifies_when_metadata_keys_are_unordered` (Review Focus 2).
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** plus `php artisan audit:verify` on the local DB. Expected: PASS; the command prints "Audit log intact: N entries checked."

### Task 13: CSV export and retention

**Files:**
- Create: `app/Support/CsvExport.php`
- Modify: `AuditLogController` (`export`), `SecurityEventController` (`export`)
- Modify: both index views ("Export CSV" button carrying the current filters)
- Modify: `routes/web.php` (`admin.audit-logs.export`, `admin.security-events.export`, both `throttle:5,1`)
- Test: `tests/Feature/Security/ActivityExportTest.php`

**Interfaces:**
- `CsvExport::stream(string $filename, array $headers, iterable $rows): StreamedResponse` with a UTF-8 BOM (so Excel shows ₱ and ñ correctly). Every cell goes through `CsvExport::safe(string): string`: when it starts with `=`, `+`, `-`, `@`, `\t` or `\r`, prefix `'`.
- The exports reuse each controller's filter query (extract a private `filtered(Request)` builder), use `lazyById(1000)`, and cap at 50,000 rows.
- Each export writes `AuditLog::record('activity.export', "Exported {n} rows of {log}.", metadata: filters)` and adds the label.
- Retention: `auth_events` prune is already scheduled (Task 1). `audit_logs` is not pruned (D4).

- [ ] **Step 1: Write failing tests** `test_export_respects_filters`; `test_export_escapes_formula_cells` (actor named `=HYPERLINK("x")` → cell `'=HYPERLINK("x")`); `test_export_is_audited`.
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Expected: PASS.

### Task 14: Switch CSP to enforce in production

**Files:**
- Modify: `plans/hostinger-vps-deployment.md:306` (make the steps concrete: run with `report-only` for 7 days, `grep "CSP violation" storage/logs/laravel.log`, fix each source, then set `CSP_MODE=enforce`)
- No code change unless reports show violations.

- [ ] **Step 1:** Locally, set `CSP_MODE=report-only`. Load the admin dashboard, the browse map, the lease editor, chat (Reverb) and PayMongo checkout. Confirm reports reach `laravel.log`: inject one test inline script, see the "CSP violation" line, remove the script. Today's log has **zero** CSP lines, so first prove the reporting path works.
- [ ] **Step 2:** Fix any real violation found (add the source to `SecurityHeaders` or move the inline script).
- [ ] **Step 3:** Set `CSP_MODE=enforce` locally, repeat the page tour, and confirm nothing breaks.

### Task 15: Docs

**Files:** `context/SCHEMA.md` (`auth_events`, the users 2FA columns, the `audit_logs` chain columns), `context/ARCHITECTURE.md` (security events vs audit log; 2FA and step-up middleware order; hash chain), `context/RULES.md` (new rule: "Account/security events go through `AuthEventRecorder`; never log secrets"), `context/CHANGELOG.md` (dated entry), `.env.example` (`AUDIT_CHAIN_KEY`, `ADMIN_TWO_FACTOR=required`).

- [ ] **Step 1:** Write the doc updates.
- [ ] **Step 2: Phase 4 manual check by Axcee, then commit.**

---

## Manual checklist (Axcee, in browser)

Fixtures: one admin (`admin@abangananhub.com`), one landlord (`landlord@abangananhub.com`), one tenant (`reviewer4@abangananhub.com`), all with known passwords. For Task 11, install Google Authenticator on your phone.

**Phase 1**
1. Sign in as the tenant with a wrong password 3 times, then correctly. Admin → Activity Logs → **Account & security** shows 3 "Failed sign-in" rows (amber) and 1 "Signed in".
2. Filter by Role = Tenant and Event = Failed sign-in. Only those rows show.
3. As the tenant, open `/admin/users`. You get 403, and an "Access denied" row appears for the tenant.
4. Open the tenant's user page. The Security card lists the recent sign-ins.

**Phase 2**
5. Set a new password to `password123`. It is rejected as a known leaked password.
6. Sign in as the landlord in a private window. The landlord inbox (or `laravel.log`) gets "New sign-in to your AbangananHub account". Sign in again in the same window: no second email.
7. Profile settings at 375px: "Where you're signed in" lists both browsers. Tap **Sign out other devices** and enter your password. The private window is signed out on its next click.
8. Fail the landlord's password 6 times. The admin bell shows one "Security alert".

**Phase 3**
9. Sign in as admin. You're sent to set up 2FA. Scan the QR, enter the code and save the recovery codes.
10. Sign out and in again. The 6-digit code is asked for. Try a recovery code once. It works; the same code a second time fails.
11. Sign in as admin with Google (if linked). The code is still asked for.
12. Release a payment. The confirm dialog asks for your password. Release another within 15 minutes: no password asked.

**Phase 4**
13. Activity Logs → Admin actions shows "Log intact". In phpMyAdmin, change one `audit_logs.summary`, run `php artisan audit:verify`: it reports "altered at entry #N". Revert the change.
14. Export CSV with a date filter. It opens in Excel with ₱ and ñ intact and only the filtered rows.

## Coverage (spec → task)

| Requirement | Task |
|---|---|
| Rule 1–2: audit log read-only, no edit/delete | Already true; made tamper-evident in 12 |
| Rule 3: record admin actions incl. the admin's own | Existing `audit_logs` + admin sign-ins in 2 |
| Rule 4: failed logins, unauthorized access | 2, 3 |
| Rule 5: never record secrets | Global Constraints + Review Focus 1 |
| Rule 6: restrict log access | `admin` middleware on 4, 13 |
| User + Admin activity sections with user/role/type/date filters | 4 |
| #1 Admin 2FA | 11 |
| #2 Step-up password | 10 |
| #3 New sign-in alert | 7 |
| #4 Sessions list + sign out others | 8 |
| #5 Tamper-evident log | 12 |
| #6 Suspicious-activity alerts | 9 |
| #7 Leaked passwords | 6 |
| #8 Retention + CSV export | 1, 13 |
| #9 CSP enforce | 14 |
