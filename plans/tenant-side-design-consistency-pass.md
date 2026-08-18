# Tenant-side design consistency & robustness pass

## Context

This continues today's mobile-consistency work, but the user redirected scope: rather than chasing the "Become a Landlord" mobile-visibility report (which we can't currently verify live — nothing on this machine is listening for LAN connections on port 8000, so the partner's screenshot is almost certainly a stale cache, not live code), the user asked to instead sweep the tenant-facing account pages for design-consistency drift and latent bugs that could surface as errors later.

An audit of `context/DESIGN.md`, `context/RULES.md`, and the tenant-facing views/controllers against those documented conventions turned up one confirmed live bug and several confirmed, verifiable design/consistency gaps:

1. **Confirmed 500 error** — `app/Http/Controllers/Tenant/FavoriteController.php:33` filters saved listings by `whereHas('property', fn($q) => $q->where('availability_status', $availability))`, but `availability_status` is a column on `property_units`, not `properties` (confirmed via migrations — `properties` has no such column). Selecting "Available" or "Unavailable" from the always-visible filter dropdown at `resources/views/favorites/index.blade.php:37-39` throws `Unknown column 'availability_status'`. The rest of the app (`app/Http/Controllers/PropertyController.php:19-22`, and a dozen other controllers) consistently expresses "available" as `whereHas('units', fn($q) => $q->where('availability_status', 'Available'))` — the fix mirrors that established pattern.

2. **Card-spec drift** — DESIGN.md §6 locks the card recipe (`bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]`) behind the shared `<x-card>` component (`resources/views/components/card.blade.php`). Most tenant pages hand-roll a *different*, undocumented spec instead (`ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)]`) rather than reusing it — confirmed in `tenant/reservations/index.blade.php`, `tenant/profile/edit.blade.php`, `tenant/profile/show.blade.php`, `favorites/index.blade.php`, `notifications/index.blade.php`. Only `tenant/tenancy/show.blade.php` and `agreements/show.blade.php` use `<x-card>` correctly. This is the single biggest source of "looks inconsistent" across this part of the app — different border tint and shadow weight, page to page, for what's supposed to be one shared surface.

3. **Missing inline validation** — RULES.md requires validation errors to stay inline via `@error`. `tenant/profile/edit.blade.php` validates five fields server-side (`Tenant\ProfileController::update`) but only shows a single aggregated banner (lines 30-36) with no per-field `@error` block — unlike `agreements/show.blade.php`, which does this correctly. A failed photo upload or a rejected name currently gives no indication of *which* field was wrong.

4. **Known documented bug, still present** — DESIGN.md explicitly documents that modals living under `layouts.app` must be `z-[200]`, not `z-50`, because the sticky header is `z-[100]` and a `z-50` modal renders behind/clipped by it. `agreements/show.blade.php:382` (the "I haven't received the keys" dispute modal) is still at `z-50` — confirmed via direct read. `tenant/reservations/index.blade.php`'s modal was already correctly fixed to `z-[200]`, so this is the one remaining instance, not a new problem to hunt across the whole app.

5. **Shell selection consistency** — `app/Models/User.php:194-214` documents `usesLandlordShell()`/`shellContainerClass()` specifically so a user holding both Tenant and Landlord roles always lands in a consistent shell no matter which page they're on; `tenant/reservations/index.blade.php` and `tenant/tenancy/show.blade.php` already use it. `favorites/index.blade.php` and `notifications/index.blade.php` hardcode `@extends('layouts.app')` instead, even though both are reachable by any authenticated user (including dual-role landlords) via the unconditional avatar-menu links in `layouts/app.blade.php`. (Checked and *excluded* `tenant/profile/edit.blade.php`/`show.blade.php` from this — the avatar dropdown always routes Landlord-role users to `landlord.profile.me` instead, so those two pages are provably tenant-only in normal use and hardcoding `layouts.app` there is correct, not a bug.)

## Approach

Fix each verified item directly — no further exploration needed, everything above was confirmed by reading the actual files:

1. **`FavoriteController::index`** — rewrite the availability filter to match the app's established convention: `whereHas('property.units', ...)` with `availability_status = 'Available'` for "Available", and `whereDoesntHave` the same clause for "Unavailable".

2. **Card-spec drift** — in the five identified files, replace the hand-rolled `bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)]` div wrappers with `<x-card>` (using the `flush` prop where the card wraps a table/list that supplies its own padding, matching how `tenant/tenancy/show.blade.php` already uses it). Keep any wrapper-specific extra classes (margins, flex direction) as additional attributes on the component — `<x-card>` merges them via `$attributes->class()`.

3. **`tenant/profile/edit.blade.php`** — add `@error('field')` blocks under each of the five validated fields (first_name, last_name, contact_number, bio, profile_picture), matching the pattern already used in `agreements/show.blade.php:241-243` etc. Keep the top banner for anything not tied to a specific field.

4. **`agreements/show.blade.php:382`** — change `z-50` to `z-[200]` on the dispute modal, matching the fix already applied to the reservations modal and the rule documented in DESIGN.md.

5. **`favorites/index.blade.php` and `notifications/index.blade.php`** — switch `@extends('layouts.app', ...)` to the same runtime pattern used in `tenant/reservations/index.blade.php:1`: `@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', [...])`, and swap the hardcoded `max-w-[1400px]` container width to `{{ auth()->user()->shellContainerClass() }}`.

## Verification

- `php artisan view:cache` after each batch of edits to catch Blade syntax errors immediately (used successfully earlier today).
- For the FavoriteController fix: manually trace the query logic against `PropertyController::index`'s equivalent filter to confirm the `whereHas`/`whereDoesntHave` pair produces the intended "has an available unit" / "has no available unit" split, since there's no interactive way to exercise it against real data in this sandbox (no reachable dev server, per today's LAN finding).
- Visual verification is code-review-based, same constraint as earlier today (no browser automation available in this environment) — will fetch rendered HTML via `curl` against the local loopback server to confirm no Blade/PHP errors surface, and describe what changed for the user to spot-check with their partner once the dev server is reachable over LAN.
