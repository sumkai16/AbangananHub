# RULES.md — Coding & Implementation Rules

## Core Principles
- **SOLID** — especially Single Responsibility. If a controller does two unrelated things, extract a Form Request, Policy, or separate controller.
- **DRY** — extract when logic repeats 4+ times, not before. Reusable JS goes to `public/js/`, reusable UI to Blade components.
- **KISS** — default to the boring, obvious Laravel solution. No repository/service layer indirection unless a controller is genuinely fat.

## Naming
- Variables/functions: camelCase (`$reservationStatus`, `getVerifiedLandlords()`)
- Files/classes: PascalCase (`PropertyController.php`, `MessageSent.php`)
- DB tables/columns: snake_case (`property_media`, `media_url`, `landlord_id`)
- Models: singular PascalCase (`Property`, `PropertyMedia`, `LandlordVerification`)
- Routes: named, dot-notation (`landlord.properties.index`, `admin.verifications.show`)
- Migrations: Laravel convention (`create_property_units_table`, `add_admin_notes_to_landlord_verifications`)

## Error Handling
- User-facing errors: flash messages via `session('status')` / `session('error')`, displayed near the problem in Blade
- Server errors: logged, never silently caught. Every catch block logs, rethrows, or handles meaningfully.
- 404 on missing resources: `abort(404)` or `findOrFail()`
- 409 for race conditions: e.g. reviewing an already-reviewed verification
- No `dd()`, `console.log`, or `var_dump` left in committed code

## Model Rules
- Every model with a custom PK: `protected $primaryKey = 'column_name';` — mandatory, no exceptions
- Every migration adding columns: immediate `$fillable` audit on the affected model
- `belongsToMany`: always pass all four arguments when custom PKs are involved
- `belongsTo`: explicit FK and local-key arguments — Laravel auto-inference breaks with custom PKs
- Decimal columns: Eloquent serializes as strings — `parseFloat()` client-side before passing to Leaflet or JS
- `Amenity` exposes a `name` accessor aliasing `amenity_name`; views use `$amenity->name`. When adding a column that views reference by a shorthand, add the accessor rather than letting it silently render null

## Laravel Conventions
- Eloquent ORM over raw queries, always
- Migrations for all schema changes — never manual SQL
- Middleware for role gating (`EnsureTenant`, `EnsureLandlord`, `EnsureAdmin`) — never if-else role checks in controllers
- Resource controllers where applicable
- Named routes throughout — no hardcoded URLs in Blade
- Form Requests for validation
- Policies for authorization (`Gate::authorize()`, not `$this->authorize()` — Laravel 12 base Controller doesn't include `AuthorizesRequests`)
- `withQueryString()` on all paginators when filters are active
- `route:clear` before trusting route behavior in debugging — never `route:cache` during dev

## Broadcasting
- `MessageSent` must include `broadcastAs()` or Laravel broadcasts under FQCN, mismatching Echo listeners
- `ShouldBroadcastNow` (synchronous) — no queue worker needed for capstone
- `toOthers()` requires `X-Socket-ID` header via `window.Echo.socketId()`
- Echo: `.listen('.EventName')` (leading dot) for bare event names

## Storage
- Filter `unit_media`/`property_media` to `media_type === 'Image'` before rendering in `<img>` — the table also holds Video rows, which render as broken images otherwise (applies to galleries, thumbnails, and JS payloads)
- `Storage::url()` for local files with relative paths only
- Full external URLs (Cloudinary, Unsplash) stored in `media_url` → output as-is: `src="{{ $media->media_url }}"`
- Cloudinary v3 SDK: `cloudinary()->uploadApi()->upload()` — not `cloudinary()->upload()` or static facade
- Government IDs → `local` disk (private, `storage/app/private/verifications/{user_id}/`)
- Public media → Cloudinary

## Testing
- Manual testing for capstone scope (no automated test suite)
- Critical path: `migrate:fresh --seed` + `route:list` is the standard checkpoint before any view work
- Verify tinker results before proceeding with controller logic
- PowerShell: compound tinker `--execute` with `$` variables unreliable — use interactive tinker or pipe workaround

## Git Discipline
- Commit message format: conventional commits (`feat:`, `fix:`, `chore:`, `docs:`)
- Separate commits per concern: backend fixes, feature additions, UI changes committed separately
- Never `git add .` across unrelated work
- Branch: `axci` (Axcee's working branch)
- Claude provides commit message text only — Axcee uses VSCode source control

## Build Order (Layer-by-Layer)
1. Migration (+ `$fillable` audit)
2. Model (+ `$primaryKey`, relationships)
3. Controller (resource methods)
4. Routes (named, middleware-gated)
5. Blade views
Confirm output at each step before proceeding to the next.

## Collaboration: Joseph's Files
Audit immediately on paste for:
- [ ] Palette drift (`#1A1A2E` instead of `#1F2937`, old Gold Black colors)
- [ ] Unresolved git merge conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`)
- [ ] `<x-app-layout>` instead of `@extends('layouts.app')` + `@section('content')`
- [ ] Inline `style` attributes instead of Tailwind utilities
- [ ] Backend scope creep without scaffolding from Axcee

## Modals & Overlays
- `backdrop-filter`, `filter`, and `transform` create CSS containing blocks — a `position: fixed` modal nested inside any glassmorphism card (`backdrop-blur-xl`) gets trapped and clipped to that card. Always `<template x-teleport="body">` modals that live inside cards (teleported content keeps its Alpine scope).
- Modal animation standard: two-flag pattern (`modal` = data, `show` = visibility) so leave transitions actually play (`x-if` alone doesn't animate). Enter: 300ms ease-out, `opacity-0 translate-y-4 scale-95` → full. Leave: 200ms ease-in reverse. Add `motion-reduce:transition-none`.
- Unit detail presentation is one shared pattern (photo top, status pill, teal rent, capacity/deposit tiles, amenity chips) across: unit create/edit Live Preview, occupancy modal, landlord units-page modal, tenant slideout. Reuse it for any new unit surface.

## UI/UX Pre-Delivery Checklist

**Visual**
- [ ] No emojis used as icons — Heroicons SVG only
- [ ] Hover states use `hover:brightness-95`, no layout shift
- [ ] Ocean Teal palette colors used correctly (fills only for `#2AA7A1` and `#69D2C6`)
- [ ] Glassmorphism spec applied: `bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg`

**Interaction**
- [ ] All clickable elements have `cursor-pointer`
- [ ] Transitions: `transition-all duration-200 ease-in-out`
- [ ] Focus states visible for keyboard nav
- [ ] Buttons disable during async operations
- [ ] Error feedback near the actual problem

**Layout**
- [ ] No content hidden behind fixed nav
- [ ] Responsive at 375 / 768 / 1024 / 1440px, no horizontal scroll
- [ ] `max-w-7xl` consistent across pages
- [ ] `[x-cloak] { display: none; }` in global CSS

**Accessibility**
- [ ] Images have alt text
- [ ] Form inputs have real `<label for>` elements
- [ ] 4.5:1 contrast minimum for text
- [ ] `prefers-reduced-motion` respected

**Code**
- [ ] No hardcoded secrets/credentials
- [ ] No leftover `dd()`, `console.log`, `var_dump`
- [ ] No inline `<style>` blocks
- [ ] `@continue` guard in loops where related model could be null (favorites, reviews)
- [ ] `favoritedIds` fetched via `pluck()` in controller, not N+1 in Blade loop
