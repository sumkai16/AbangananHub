# Landlord-side design consistency & robustness pass

## Context

This mirrors today's earlier tenant-side pass, now scoped to the landlord-facing pages (dashboard, properties, units, tenancies, payments, payouts, reservations, tenants, occupancy, analytics, reviews, profile, verification). Three parallel audits against `context/DESIGN.md` and `context/RULES.md` turned up one urgent environment issue plus a substantial, verified set of bugs and design drift — all confirmed by direct file reads, with two candidate findings checked and ruled out as false positives (noted below).

**Urgent, unrelated to the audit itself:** while reviewing, the user hit a live `SQLSTATE[42S22]: Column not found: 'publication_status'` 500 error on the homepage. `php artisan migrate:status` shows **13 pending migrations** never applied to the local DB — not a code bug, just schema drift between the code (which already expects these columns) and the local database. This needs to run first, before any of the below can even be verified against real data.

### Confirmed bugs

1. **Modals rendering behind the landlord sidebar** — `landlord/units/all.blade.php:508`, `landlord/reservations/index.blade.php:613,677`, and `landlord/occupancy/index.blade.php:249` are all teleported, blocking dialogs at `z-30`. Per DESIGN.md's z-index table, `z-30` is the *mobile slim top bar* layer, and these pages extend `layouts.landlord`, whose sidebar sits at `z-50` — always visible on desktop. These modals currently render **behind the sidebar**, not just clipped like the earlier `z-50`-in-`layouts.app` bug class — the sidebar's links stay clickable through the dimmed backdrop. The documented convention (DESIGN.md: "Page-level modal (`x-modal`) | `z-[200]`") applies regardless of shell.

2. **Two more latent `z-50` lightboxes, named explicitly in DESIGN.md itself** — `landlord/verification/create.blade.php:1046` and `landlord/verification/show.blade.php:289` both extend `layouts.app` (confirmed — these onboarding pages intentionally use the public shell since a landlord mid-verification hasn't unlocked the dashboard yet) and still sit at `z-50`. DESIGN.md's own text names these two files as the remaining latent instances of "a modal in `layouts/app` must be `z-[200]`, not `z-50`" (two others in that same list — `agreements/show` and `tenant/reservations/index` — were already fixed earlier today).

3. **Broken-thumbnail risk** — `landlord/tenants/index.blade.php:115` does `$reservation->property->media->first() ?? null`, unfiltered by media type, then renders it straight into an `<img>`. Every sibling file in this codebase (`tenancies/show.blade.php:30`, `walk-in/create.blade.php:21-22`) correctly uses `firstWhere('media_type', 'Image')`, because `property_media` also stores `Video` rows. Any property whose first-uploaded media is a video shows a broken image on every tenant card.

4. **Dead/landmine code** — `landlord/units/create.blade.php:160,168,176` reference `$unit->bedrooms`, `$unit->bathrooms`, `isset($unit)` — leftover copy-paste from `edit.blade.php`. Confirmed `PropertyUnitController::create()` never passes `$unit` to this view; the `??`/`isset()` guards happen to prevent a crash today, but it's confusing dead code and a landmine for a future refactor that touches those guards.

5. **One-off, off-palette color set** — `landlord/payouts/index.blade.php` invents its own amber palette (`#F5D47A`, `#FFF8E7`, `#FFF6D7`, `#FDF7E8`, `#FDE68A`, `#7A4F08`) for its "no GCash number" banner, plus an off-scale shadow value — confirmed used *nowhere else* in the codebase, unlike the established amber/warning tokens (`#FBBF24`/`#B45309`) used consistently in 66 other files.

6. **Missing inline `@error` validation** (RULES.md: "validation errors stay inline"), confirmed in:
   - `landlord/tenancies/partials/record-payment-modal.blade.php` — **zero** `@error` blocks across all 7 validated fields (`payment_type`, `amount`, `billing_period`, `payment_method`, `paid_at`, `reference_no`, `payment_notes`). Worse than a missing hint: since the modal's open/closed state is client-side Alpine state defaulting to `false`, a failed submit redirects back with the modal **closed** — the error is completely invisible, not just non-inline.
   - `landlord/properties/edit.blade.php` — missing on `title`, `property_type`, `rental_fee`, `description` (other fields on the same form already have it).
   - `landlord/units/edit.blade.php` — missing on nearly every field (`unit_label`, `occupancy_limit`, `unit_type`, `floor`, `bedrooms`, `bathrooms`, `is_furnished`, `rental_fee`, `security_deposit`, `availability_status`, `description`); sibling `create.blade.php` has full coverage.
   - `landlord/reviews/index.blade.php` — the inline reply `<textarea name="landlord_reply">` has no `@error('landlord_reply')`.
   - `landlord/profile/edit.blade.php` — same aggregate-banner-only pattern the tenant-side profile edit had, across ~10 validated fields.

### Card-spec drift (same class found on the tenant side, DESIGN.md §6)

Hand-rolled `bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]` divs instead of the shared `<x-card>` component, confirmed across: `landlord/properties/index.blade.php`, `show.blade.php`, `edit.blade.php`; `landlord/units/all.blade.php`, `create.blade.php`; `landlord/occupancy/index.blade.php` (Vacancy Watch + Recent Activity panels); `landlord/reviews/index.blade.php` (filter bar, empty state, review cards, sidebar cards); `landlord/profile/show.blade.php` (business info, four stat tiles, reviews panel, empty states); `landlord/payouts/index.blade.php`; `landlord/verification/show.blade.php` (identity/business/timeline cards). This is the largest-volume finding, same as the tenant-side pass — one recipe, drifted independently on many pages.

### Checked and ruled out (false positives)

- `PropertyController::index` (landlord) chains `->submitted()` unconditionally inside the status-filter branch — initially flagged as suspicious, but the code comment and logic are correct and intentional: it keeps unsubmitted Drafts (whose `verification_status` defaults to `'Pending'`) out of every status bucket, not just the Pending one. Not a bug.
- `#059669` stroke color on stat-card icons (`landlord/payments/index.blade.php`, `reservations/index.blade.php`) — flagged as off-palette, but it's actually used consistently across **15 files** spanning both admin and landlord views. This is an established, just-undocumented token, not drift. Excluded.

## Approach

1. **Run `php artisan migrate`** first — applies the 13 pending migrations, fixing the live `publication_status` 500 and any other schema-drift errors that would otherwise mask real testing of the rest of this pass.

2. **Fix the z-index bugs** — bump all 6 confirmed instances (4 landlord-shell `z-30` modals, 2 `layouts.app` `z-50` lightboxes) to `z-[200]`, matching the documented "Page-level modal" convention.

3. **Fix the tenants-index thumbnail bug** — change `landlord/tenants/index.blade.php:115` to `$reservation->property->media->firstWhere('media_type', 'Image')`, matching the sibling convention.

4. **Remove the dead `$unit` references** in `landlord/units/create.blade.php` — this view never receives `$unit`, so drop the `??`/`isset()` fallbacks entirely rather than keep dead code that only works by accident.

5. **Re-palette the payouts warning banner** — swap `landlord/payouts/index.blade.php`'s one-off amber hex values for the established `#FBBF24`/`#B45309` tokens and the standard card shadow, matching how every other warning-style banner in the app is built.

6. **Add missing `@error` blocks** to the 5 identified files/fields, following the same inline pattern already used correctly elsewhere in each file (or in the sibling file, for `units/edit.blade.php` vs. `units/create.blade.php`).

7. **Card-spec drift** — replace the hand-rolled card divs with `<x-card>` (using the `flush` prop where a table/list supplies its own padding) across the ~10 identified files, same mechanical approach used on the tenant side: keep any extra layout classes (padding overrides, flex/grid direction, margins) as additional attributes merged onto the component.

## Verification

- `php artisan migrate:status` after step 1 to confirm all migrations applied, then re-check the homepage 500 is gone.
- `php artisan view:cache` after each batch of Blade edits to catch syntax errors immediately (same approach used successfully twice today).
- `php -l` on any touched PHP controller/model files.
- HTTP smoke tests via `curl` against the local loopback server for the touched routes, same constraint as earlier today (no browser automation available in this sandbox — no reachable LAN server either, per the earlier port-8000 finding).
