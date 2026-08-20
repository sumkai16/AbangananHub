# Landlord-side remaining mobile-readiness fixes

## Context

Continuing today's landlord-side work: earlier passes fixed the sidebar shell's mobile nav, six z-index bugs, missing validation, and card-spec drift. This pass specifically checks page *content* (not shell/nav) for 375px-readiness, per CLAUDE.md's mobile-first requirement for the Landlord surface. Three parallel audits covered every landlord page not yet checked for this. Most pages were already properly responsive (dashboard, properties/index+show, units/all+create+edit, analytics, reviews, profile/edit, verification wizard, tenancies, payments, payouts, tenants) — good confirmation that the earlier work already established a solid mobile-first pattern most pages already follow.

Three genuine, confirmed gaps stood out — all the same underlying pattern (a bare `grid-cols-2`/`grid-cols-N` with no `grid-cols-1` step for the narrowest phones), all verified by direct file reads against the established correct pattern used elsewhere in the same codebase (e.g. `payments/index.blade.php`'s `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` stat row):

1. **`landlord/reservations/index.blade.php:52`** — the 4 summary stat cards use `grid-cols-2 lg:grid-cols-4`, skipping straight to 2-up at the narrowest width instead of starting single-column. At 375px each card gets ~170px, cramming label/value/icon.
2. **`landlord/reservations/index.blade.php:105`** — the filter row (property select, two date pickers, filter/clear buttons, view toggle) uses `grid-cols-2 sm:grid-cols-4 lg:flex`. At 375px this forces the date pickers into ~170px cells, which is tight for their input text.
3. **`landlord/occupancy/index.blade.php:53`** — 6 stat cards (5 regular + 1 spanning "Occupancy Rate" card) use `grid-cols-2 sm:grid-cols-3 lg:grid-cols-6`. Unlike the two above, these cards each also carry a percent bar and a longer sub-label (e.g. "42% of total"), so 2-up at 375px is denser and more likely to wrap awkwardly than the simpler label+value cards elsewhere.

### Checked and excluded (flagged by the audits but not genuine breakage)

Consistent with this session's established practice of fixing only confirmed breakage, not cosmetic risk:
- `properties/edit.blade.php:272` and `units/edit.blade.php:282` — bare `grid-cols-3` photo-thumbnail grids. Small square read-only images; agents explicitly called these "not a hard fail."
- `landlord/profile/show.blade.php:82` — 2-up stat row; agent found no `truncate` issue, just "visually rougher than 1-column," not breaking.
- `verification/create.blade.php` and `show.blade.php`'s `grid-cols-[110px_1fr]` label/value pairs — minor cramping risk for unusually long values, not breaking.
- `properties/create.blade.php` doesn't exist at that path — property creation is a wizard flow elsewhere, out of scope for this pass unless you want it audited separately.

## Approach

Apply the same one-column-first fix to all three, matching the established sibling pattern already used correctly elsewhere in this codebase:

1. **`reservations/index.blade.php:52`**: `grid-cols-2 lg:grid-cols-4` → `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` (exactly matches `payments/index.blade.php`'s stat row).
2. **`reservations/index.blade.php:105`**: `grid-cols-2 sm:grid-cols-4 lg:flex` → `grid-cols-1 sm:grid-cols-2 lg:flex` — single column on phones (property select, then each date picker, then the button/view-toggle group stack full-width and readable), 2-up at `sm`, flex row from `lg`.
3. **`occupancy/index.blade.php:53`**: `grid-cols-2 sm:grid-cols-3 lg:grid-cols-6` → `grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6`, and update the spanning "Occupancy Rate" card's class from `col-span-2 sm:col-span-3 lg:col-span-1` to `col-span-1 sm:col-span-2 md:col-span-3 lg:col-span-1` so it still fills the last row's remaining slot at each step instead of overflowing awkwardly.

## Verification

- `php artisan view:cache` after the edits to catch any Blade syntax errors (same approach used successfully throughout today's work).
- `curl` smoke test on `/landlord/reservations` and `/landlord/occupancy` to confirm no 500s (expect a 302 guest-redirect, consistent with every other landlord route tested today, since there's no reachable authenticated session or LAN-visible server in this sandbox).
- No browser automation is available in this environment (confirmed earlier today — no npm registry access, no Playwright/chromium-cli), so visual confirmation at 375px is code-review-based against the same verified-correct sibling patterns, not a rendered screenshot.
