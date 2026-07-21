# RULES.md — Coding & Implementation Rules

## Core Principles
- **SOLID** — especially Single Responsibility. If a controller does two unrelated things, extract a Form Request, Policy, or separate controller.
- **DRY** — extract when logic repeats 4+ times, not before. Reusable JS goes to `public/js/`, reusable UI to Blade components.
- **KISS** — default to the boring, obvious Laravel solution. No repository/service layer indirection unless a controller is genuinely fat.

## Plan Before Implementing
**No feature or non-trivial modification starts with an edit.** Investigate, plan, agree the design, then write code. The goal is that the thing built is the thing wanted — mismatches are cheap to fix in a plan and expensive to fix in a diff.

1. **Investigate first.** Read the existing code, routes, and conventions before proposing anything. Half the "new feature" work in this codebase turns out to be finishing or repairing something already scaffolded — the forgot-password flow was 80% present via Breeze, and four auth views were silently 500'ing on a missing component nobody had noticed.
2. **Present the plan.** State the approach, the files it will touch, and the design decisions being made.
3. **Ask about real forks only.** Surface the choices that change the outcome (UX pattern, data model, scope) with a recommended option. Don't ask about anything with an obvious default — pick it, say so, move on.
4. **Then implement**, layer by layer per Build Order below.

Use plan mode for anything multi-file or design-bearing. Skip the ceremony for genuinely trivial, fully-specified changes — a one-line fix, a rename, a typo.

**Re-confirm when investigation contradicts the plan.** If a pre-existing bug, a false positive, or a wrong assumption turns up mid-task, stop and surface it rather than deciding unilaterally and continuing. Tooling output is a lead, not a finding: verify it against the source before acting on it (see the a11y scanner caveat under Testing).

## Naming
- Variables/functions: camelCase (`$reservationStatus`, `getVerifiedLandlords()`)
- Files/classes: PascalCase (`PropertyController.php`, `MessageSent.php`)
- DB tables/columns: snake_case (`property_media`, `media_url`, `landlord_id`)
- Models: singular PascalCase (`Property`, `PropertyMedia`, `LandlordVerification`)
- Routes: named, dot-notation (`landlord.properties.index`, `admin.verifications.show`)
- Migrations: Laravel convention (`create_property_units_table`, `add_admin_notes_to_landlord_verifications`)

## Error Handling
- **Flash notifications render through the global modal — never as inline banners.** Controllers flash `success` / `warning` / `error` (legacy `status` is treated as an alias for `success`); `partials/flash-modal.blade.php`, included once per layout, reads the session and dispatches `show-modal`. Do not add a `@if(session('success'))` banner to a view — it will double up with the modal, which is exactly the bug that existed across 11 views before July 2026.
- Flash a complete human sentence, never a status slug — the string is rendered verbatim in the modal. `'Your password has been updated.'`, not `'password-updated'`.
- **Validation errors stay inline** (`@error`, `$errors->first()`) beside the field they belong to. That's in-context feedback, deliberately not routed through the flash modal.
- Server errors: logged, never silently caught. Every catch block logs, rethrows, or handles meaningfully.
- 404 on missing resources: `abort(404)` or `findOrFail()`
- 409 for race conditions: e.g. reviewing an already-reviewed verification
- No `dd()`, `console.log`, or `var_dump` left in committed code

## Concurrency & State Transitions (established July 21, 2026)
Admin moderation actions (verify/approve/reject/release/suspend) follow a check-then-write pattern — `abort_if($record->status !== 'Pending', 409, ...)` then `->update(...)` — which is not atomic on its own. A double-click or a retried request can pass the check twice before either write commits, double-firing whatever the transition triggers (role grants, `RentalBusiness::firstOrCreate`, a "payment released" system message, a second state flip on something already resolved).

**Any controller action that flips a record's status and does something consequential on that transition (grants a role, creates a related row, moves money, sends a message) must wrap the check + write in `DB::transaction()` with `lockForUpdate()`:**
```php
DB::transaction(function () use ($record) {
    $locked = Model::whereKey($record->getKey())->lockForUpdate()->firstOrFail();
    abort_if($locked->status !== 'Pending', 409, 'Already reviewed.');
    $locked->update([...]);
    // role grants / related-row creation / side effects go here, inside the lock
});
```
Reference implementations: `Admin\VerificationController::approve/reject`, `Admin\PaymentController::release` (money — highest priority), `Admin\ListingController::approve/reject`, `Admin\PropertyUnitController::approve/reject`, `Tenant\AgreementController::sign/confirmMoveIn` (July 2026 — `confirmMoveIn` releases escrow to the landlord and had no lock at all; it was also guarded only by a native `onclick="return confirm()"`, which this codebase does not use). `ListingController` previously had no idempotency guard at all before this pass — a bare status check is the floor, not the fix; the lock is what actually closes the race.

**Self-action guards on admin-management screens.** An admin editing their own account through the same form used to manage other admins can strip their own Admin role or suspend themselves with no recovery path. `UserController::update`/`updateStatus` now reject role/status changes where the target is `auth()->id()`. Any future "admin manages other admin-like records" screen needs the same self-target check.

**Hard deletes on `User` cascade** — every FK from `properties`, `reservations`, `payments`, `reviews`, `conversations`/`messages`, `reports`, `favorites`, `notifications`, and `tenant_ratings` back to `users.user_id` is `onDelete('cascade')` (see SCHEMA.md). `UserController::destroy` blocks the hard delete once a user has properties, reservations, or reviews on record, and directs the admin to suspend instead — suspension is reversible, a cascading delete is not. Don't reintroduce an unconditional `$user->delete()`.

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
- Post-authentication redirects go through `$user->homeRoute()` — never hardcode a destination inside an auth controller. Admin → `admin.dashboard`, Landlord → `landlord.dashboard`, everyone else (tenants and brand-new role-less accounts) → `properties.index`. There is no bare `dashboard` route and no tenant dashboard. Adding a role means adding one arm to `homeRoute()`; all 8 auth entry points then follow automatically.
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
- **Off-the-shelf HTML/a11y scanners mis-parse Blade — verify every finding against the source before fixing.** Two failure modes bit us in July 2026: (1) `->` inside `{{ }}` contains a `>` that naive tag regexes treat as the tag terminator, so tags get truncated and attributes like `alt=` are reported missing when they're present — a scan claimed 48 missing-alt images, only 3 were real; (2) scanners read each Blade file in isolation, so every partial gets flagged for a missing `<main>`/`<nav>` landmark that actually lives in the layout.
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
- `backdrop-filter`, `filter`, and `transform` create CSS containing blocks — a `position: fixed` modal nested inside an element carrying any of them gets trapped and clipped to it. Cards no longer use `backdrop-blur` (retired July 2026), so this specific trap is rarer, but `transform` still triggers it and `overflow`/stacking contexts clip fixed children anyway. Always `<template x-teleport="body">` modals that live inside cards (teleported content keeps its Alpine scope).
- Modal animation standard: enter 300ms on `ease-[cubic-bezier(0.34,1.56,0.64,1)]` (slight overshoot so the panel lands with a bounce), `opacity-0 scale-95 translate-y-4` → full; leave 200ms `ease-in` reverse; backdrop fades with `backdrop-blur-sm`. Always guard the movement with `motion-reduce:` variants (`motion-reduce:scale-100 motion-reduce:translate-y-0`, or `motion-reduce:transform-none` on JS-driven panels) so reduced-motion users still get the fade but no movement.
- Alpine modals use the two-flag pattern (`modal` = data, `show` = visibility) so leave transitions actually play (`x-if` alone doesn't animate). A child element with its own `x-transition:enter` needs a matching `leave` too, or it vanishes instantly while the parent is still fading out.
- Modals toggled by plain `classList` (not Alpine) need two extra things or the animation silently never fires: a double `requestAnimationFrame` before removing the start classes — otherwise the display and opacity changes batch into one style recalculation — and a guarded `setTimeout` that defers re-adding `hidden` until the leave transition finishes. Guard it with a stored timer id so reopening mid-close doesn't hide the modal afterwards.
- One global modal serves all four notification types — confirm, success, warning, error. Trigger it with `window.dispatchEvent(new CustomEvent('show-modal', { detail: { type, title, message, confirmText, cancelText, onConfirm } }))`. Pass `onConfirm` only for genuine confirmations: the Cancel button renders off that callback's presence, so plain notifications get a single OK button. Forms opt in declaratively via `data-confirm` (see `public/js/modal-confirm.js`).
- Don't build one-off modal components. `login-modal`, `register-modal` and `success-modal` were deleted in July 2026 after being found unrendered anywhere; the auth modal in `layouts/app.blade.php` and `x-confirm-modal` are the only two.
- Unit detail presentation is one shared pattern (photo top, status pill, teal rent, capacity/deposit tiles, amenity chips) across: unit create/edit Live Preview, occupancy modal, landlord units-page modal, tenant slideout. Reuse it for any new unit surface.

## UI/UX Pre-Delivery Checklist

**Visual**
- [ ] No emojis used as icons — Heroicons SVG only
- [ ] Hover states use `hover:brightness-95`, no layout shift
- [ ] Ocean Teal palette colors used correctly (fills only for `#2AA7A1` and `#69D2C6`)
- [ ] Card spec applied via `<x-card>`: `bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]`
- [ ] No raw Tailwind color utilities — token hexes only (see DESIGN.md §11b for the audit greps)

**Interaction**
- [ ] All clickable elements have `cursor-pointer`
- [ ] Transitions: `transition-all duration-200 ease-in-out`
- [ ] Focus states visible for keyboard nav
- [ ] Buttons disable during async operations
- [ ] Validation errors render near the field they belong to; post-redirect outcomes go through the flash modal instead (see Error Handling)

**Layout**
- [ ] No content hidden behind fixed nav
- [ ] Responsive at 375 / 768 / 1024 / 1440px, no horizontal scroll
- [ ] Correct page container for the context (DESIGN.md §5) — and it carries `mx-auto`
- [ ] `[x-cloak] { display: none; }` in global CSS

**Accessibility** (WCAG 2.2 AA — swept July 2026)
- [ ] Images have alt text
- [ ] Every input has a programmatic name: a `<label for>` matching the input's `id`. A visible `<label>` with no `for=` sitting next to an `<input>` with no `id=` is the exact failure mode this codebase had in 76 places — it looks correct and is invisible to screen readers. Use `aria-label` only for placeholder-only search/utility inputs that have no visible label, and for hidden file inputs driven by JS.
- [ ] Inputs wrapped inside a `<label>` element need neither — that's already a valid association.
- [ ] New layouts include a skip-to-main-content link as the first focusable element (`sr-only focus:not-sr-only`) plus `id="main"` on `<main>`
- [ ] 4.5:1 contrast minimum for text
- [ ] `prefers-reduced-motion` respected via `motion-reduce:` variants

**Code**
- [ ] No hardcoded secrets/credentials
- [ ] No leftover `dd()`, `console.log`, `var_dump`
- [ ] No inline `<style>` blocks
- [ ] `@continue` guard in loops where related model could be null (favorites, reviews)
- [ ] `favoritedIds` fetched via `pluck()` in controller, not N+1 in Blade loop
