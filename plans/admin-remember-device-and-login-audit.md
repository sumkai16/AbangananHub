# Admin: remember this browser for 2FA, and login/logout in Admin actions

Status: **building Nov 16 2026, pending Axcee's manual verification.** Decisions by Axcee: roles = Admin and Landlord; trust length = 30 days.

## 1. Remember this browser (admin 2FA)
- The code prompt gets a checkbox "Don't ask again on this browser for 30 days".
- Ticked: a row in new table `trusted_devices` (`user_id`, `token_hash` = sha256 of a random token, `user_agent`, `ip_address`, `last_used_at`, `expires_at`) and an `ah_2fa_trusted` cookie (httpOnly, secure when https, sameSite lax, 30 days).
- `EnsureTwoFactor`: no session flag, but a valid cookie for this user -> flag set, no prompt. Logged as `auth.two_factor_passed` ("remembered browser").
- Only the sign-in code is skipped. The step-up password for risky actions is unchanged.
- Trust is wiped when: Sign out everywhere (admin), "sign out other devices", password changed or reset, 2FA turned off or enabled, recovery codes regenerated.
- The 2FA settings page lists remembered browsers with Revoke and Revoke all.
- Config: `security.trusted_device_cookie`, `security.trusted_device_days` (30).

## 2. Login/logout in Admin actions (audit_logs)
- New actions: `auth.login` (Signed in), `auth.logout` (Signed out), `auth.two_factor_passed` (Passed two-factor).
- Written only for users with the Admin or Landlord role, successful events only. Failures stay in Account & security.
- `AuditLog::record()` gets an optional `$actor` so a logout row can name the user even when the guard is already empty.
- A write failure is logged and swallowed; it never blocks a sign-in.

## Files
`database/migrations/2026_11_16_000100_create_trusted_devices_table.php`, `app/Models/TrustedDevice.php`, `app/Services/Security/TrustedDevices.php`, `EnsureTwoFactor`, `TwoFactorChallengeController`, `TwoFactorSetupController`, `SessionManager`, `TwoFactor`, `PasswordController`, `RecordAuthEvents`, `AuditLog`, `auth/two-factor-challenge`, `admin/security/two-factor`, `routes/web.php`, `config/security.php`.

## Manual checklist (run `php artisan migrate` first)
1. Sign in as admin, enter the code with the box ticked. Sign out, sign in again: no code prompt.
2. Same with the box unticked: the prompt comes back every sign-in.
3. Settings > Two-factor lists this browser. Revoke it: the next sign-in asks for a code.
4. Tick the box again, then change the password (or Sign out everywhere): the prompt returns.
5. A private window (no cookie) still gets the prompt.
6. Activity Logs > Admin actions: dropdown has Signed in, Signed out, Passed two-factor. Sign in and out as an admin and as a landlord: rows appear. A tenant sign-in adds none.
7. A risky admin action (e.g. a payout) still asks for the password on a remembered browser.
