# Add Google & Facebook Login/Register (web now, mobile-ready)

## Context
AbangananHub currently only supports email/password auth via a customized Laravel Breeze setup (session-based). To lower signup friction, we're adding "Continue with Google" / "Continue with Facebook" as alternative entry points into the same login/register flow — reusing the existing account model, role assignment, and post-login redirect logic rather than building a parallel system.

A native mobile app is planned for later. Mobile OAuth doesn't work like web (native apps can't do a browser-redirect callback the way a browser can) — the industry-standard pattern is: native Google/Facebook SDK signs the user in on-device → app sends the resulting token to the backend → backend verifies it and issues its own first-party token. This project already has **Laravel Sanctum** installed but unused, which is exactly the tool Laravel's own docs recommend for issuing tokens to a first-party mobile client (Passport would be the wrong tool — that's for when *other companies'* apps authenticate against your API, not your own app). So alongside the web flow, this plan also adds a stateless verification endpoint now, so the mobile app can plug in later without backend rework.

Confirmed decisions:
- **Auto-link by email**: if a Google/Facebook email matches an existing account, that provider gets attached and the user is logged in (no separate "account exists" block screen).
- **One provider per user**: `provider` + `provider_id` columns directly on `users` (no pivot table — simplest option, matches current scale).
- **No role on signup**: social signups behave exactly like normal self-registration — role-less until they use the existing "browse as landlord" flow.
- `contact_number` is already nullable in the `users` migration, so no schema change needed there.
- **OAuth architecture**: Laravel Socialite (official, first-party) for the web redirect flow; Socialite's `stateless()->userFromToken()` for verifying tokens a future mobile app forwards; Sanctum (already installed) issues the resulting first-party API token for mobile. No Firebase, no Passport.

## Build order (Migration → Model → Controller → Routes → Views)

### 1. Package install
`composer require laravel/socialite`

### 2. Config
`config/services.php` — add `google` and `facebook` blocks (`client_id`, `client_secret`, `redirect` from env).
`.env.example` — add `GOOGLE_CLIENT_ID/SECRET`, `GOOGLE_REDIRECT_URI`, `FACEBOOK_CLIENT_ID/SECRET`, `FACEBOOK_REDIRECT_URI` as placeholders.
Note for user: real OAuth app credentials need to be created in Google Cloud Console / Facebook Developer Console and pasted into `.env` — not something I can do.

### 3. Migration
New file `database/migrations/2026_07_25_000001_add_social_provider_fields_to_users_table.php`:
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('provider')->nullable()->after('password');
    $table->string('provider_id')->nullable()->after('provider');
    $table->string('avatar_url')->nullable()->after('provider_id');
    $table->unique(['provider', 'provider_id']);
});
```
MySQL's unique index allows multiple NULL pairs, matching the existing nullable-unique `email` precedent.

### 4. Model
`app/Models/User.php` — add `'provider', 'provider_id', 'avatar_url'` to `$fillable`.

### 5. Controller
New file `app/Http/Controllers/Auth/SocialiteController.php` with `redirect(string $provider)` and `callback(string $provider)`:
- `redirect()` → `Socialite::driver($provider)->redirect()`
- `callback()`:
  - Wrap `Socialite::driver($provider)->user()` in try/catch (`InvalidStateException`/`Exception`) → on failure, redirect to `login` with `session('error', ...)` (rendered by the existing `flash-modal.blade.php`, no new UI).
  - If no email returned → redirect to `login` with an explanatory flash error.
  - `User::where('email', ...)->first()`:
    - **Found** → `forceFill(['provider', 'provider_id', 'avatar_url'])->save()` (auto-link).
    - **Not found** → `User::create([...])` with explicit whitelist (never `$request->all()`): first/last name split from provider name (fallback to email local-part), email, `Hash::make(Str::random(40))` password (matches walk-in-tenant precedent for unknowable passwords), provider fields, `email_verified_at = now()`, `account_status = 'active'`. Fire `event(new Registered($user))`.
  - `Auth::login($user, remember: true)`, `session()->regenerate()`, redirect to `$user->homeRoute()` — reusing the same centralized redirect logic as the other two auth controllers, never hardcoded.

### 6. Mobile-ready token endpoint (Sanctum)
Extract the "find-or-create user by provider identity" logic from step 5 into a shared private method (e.g. `resolveSocialUser($provider, $socialUser)`) reused by both the web callback and a new stateless API endpoint, so there's one source of truth for account linking/creation.

New method on `SocialiteController` (or a separate `Api\Auth\SocialTokenController` if preferred — keeping it on the same controller is simpler given the shared logic): `verifyToken(Request $request, string $provider)`:
- Validates `$request->token` is present.
- `Socialite::driver($provider)->stateless()->userFromToken($request->token)` — verifies the token the mobile app got from the native Google/Facebook SDK.
- Wrap in try/catch → on failure, `response()->json(['message' => '...'], 401)`.
- Runs the same `resolveSocialUser()` logic as the web callback (auto-link by email / create role-less account).
- Issues a Sanctum token: `$user->createToken('mobile')->plainTextToken`, returns it as JSON alongside basic user info. No session/cookie involved — this is a pure API endpoint.

Route (new): `routes/api.php`
```php
Route::post('auth/{provider}/token', [SocialiteController::class, 'verifyToken'])
    ->whereIn('provider', ['google', 'facebook'])
    ->name('api.social.token');
```
This endpoint won't be exercised by anything until the mobile app exists, but it's built and testable now (e.g. via Postman with a real Google ID token) so no backend rework is needed when mobile development starts.

### 7. Routes
`routes/auth.php`, inside the existing `guest` middleware group:
```php
Route::get('auth/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->whereIn('provider', ['google', 'facebook'])
    ->name('social.redirect');
Route::get('auth/{provider}/callback', [SocialiteController::class, 'callback'])
    ->whereIn('provider', ['google', 'facebook'])
    ->name('social.callback');
```

### 8. Blade views (same button block, mirrored in 3 places)
Plain `<a href="{{ route('social.redirect', 'google') }}">` links (not AJAX — OAuth needs a real browser redirect), styled per DESIGN.md: neutral white/gray button shell (`hover:brightness-95`, `cursor-pointer`, `transition-all duration-200 ease-in-out`), with official Google/Facebook brand SVG glyphs — brand marks are the one accepted exception to the "no raw Tailwind colors" rule, since they must render in fixed brand colors to be recognizable.

Insert a divider ("or continue with") + 2-button row after the closing `</form>`, before the "Don't have an account?" link, in:
- `resources/views/layouts/app.blade.php` — inside `#login-form-view` (~line 574) and `#register-form-view` (~line 638), the canonical AJAX auth modal (do not recreate separate modal components — this is the single source per prior cleanup).
- `resources/views/auth/login.blade.php` (~line 146, full-page fallback).
- `resources/views/auth/register.blade.php` (~line 195, full-page fallback).

No JS changes needed — these are plain links, not routed through `handleAuthSubmit`.

## Verification
1. `php artisan migrate` — confirm new columns + unique index.
2. `php artisan tinker` — sanity-check `User::create([...])` with the new fields inserts cleanly (no NOT NULL violations).
3. Once real credentials are added to `.env`, manually click through: guest → "Continue with Google" → provider consent → callback → confirm landed on `properties.index` (role-less new user) via the modal AND the full-page login view.
4. Test auto-link: log in via Google with an email that already has a password account → confirm no duplicate user row, existing account gets `provider`/`provider_id` populated, redirects via `homeRoute()` to that account's correct destination (e.g. Landlord dashboard).
5. Test failure path: deny/cancel the OAuth consent screen → confirm flash-modal shows an error and lands back on login, no crash.
6. Test the mobile-ready endpoint directly (no mobile app needed yet): obtain a real Google ID token (e.g. via Google's OAuth Playground or a quick manual sign-in), POST it to `/api/auth/google/token`, confirm it returns a Sanctum `plainTextToken` and that a bad/expired token returns 401 instead of a 500.

## Status (as of 2026-07-25)
- Google login is implemented and confirmed working end-to-end locally (`.env` has real `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`, redirect URI `http://localhost:8000/auth/google/callback` registered in Google Cloud Console).
- The Google Cloud OAuth consent screen is still in **Testing** status, so only Google accounts added as test users can sign in — currently just `axceelfelis03@gmail.com`.
- **Before anyone else (analyst, PM, etc.) can test Google login**: Google Cloud Console → OAuth consent screen / Audience → Test users → add their Gmail addresses (up to 100 allowed, no verification needed, works immediately).
- Publishing the app to Production later removes the test-user cap; since only basic `email`/`profile`/`openid` scopes are requested, this likely won't require Google's full app verification review, but hasn't been done/tested yet.
- Facebook app (App ID/Secret) has not been set up yet — same "Invalid App ID" error as Google's initial state is expected until that's done.
- Added `Log::error()` calls in `SocialiteController@callback` and `@verifyToken` catch blocks so future OAuth failures show a real cause in `storage/logs/laravel.log` instead of only the generic flash-modal message.

## Critical files
- `database/migrations/2026_07_25_000001_add_social_provider_fields_to_users_table.php` (new)
- `app/Http/Controllers/Auth/SocialiteController.php` (new — web redirect/callback + mobile `verifyToken`)
- `app/Models/User.php`
- `routes/auth.php` (web routes)
- `routes/api.php` (mobile-ready token endpoint)
- `config/services.php`
- `.env.example`
- `resources/views/layouts/app.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
