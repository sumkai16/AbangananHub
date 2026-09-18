# Navy / Coral rebrand — app-wide accent swap (Gold → Coral)

## Context
`context/DESIGN.md` §3 currently documents a **Navy/Gold** identity (Sept 2026): primary navy
`#060D26` (unchanged, stays), accent gold `#C9A84C` / text-safe dark-gold `#8a6e1e`. Axcee wants the
accent swapped to **coral `#FF8A66`**, applied "throughout the entire UI" — a ~70% white / 25% navy /
5% coral hierarchy, coral reserved for CTAs, active states, highlighted words, prices, icons, badges,
decorative lines, and hover states, explicitly *not* a wholesale recolor. Confirmed with Axcee: coral
fully replaces gold as the app's accent (not a one-off logo tweak); DESIGN.md gets updated to match,
same pattern as the prior Ocean Teal → Navy/Gold rewrite.

Scope confirmed by exploration: gold hexes (`#C9A84C` / `#8a6e1e`) appear **~635 times across ~110
files** (Blade views, `app.css`, `maps.css`, `tailwind.config.js`). Primary navy (`#060D26`, ~2255
occurrences) and white already match the target and need no changes. One uncommitted edit already
exists (`layouts/app.blade.php` wordmark "Hub" → `#FF8A66`) — consistent with this plan, will be kept
and extended.

## The one non-obvious technical call: a text-safe coral variant is required
Bright coral `#FF8A66` against white contrasts at **~2.31:1** — fails WCAG AA even for large text
(needs 3:1), same failure mode gold had (`#C9A84C` was ~2.28:1, which is *why* `#8a6e1e` exists as its
text-safe twin). Reusing bright coral for small text/links/icons on white would make ~114 occurrences
of body-sized coral text illegible-ish and non-compliant.

**Proposed new token: Deep Coral `#B35A3D`** — same hue family, darkened. Computed contrast against
white: **~4.72:1**, passes AA (comparable to dark-gold's ~4.85:1). This mirrors the exact two-tier
pattern the codebase already uses for gold, just re-hued — not an unrelated "random color."
- **Bright coral `#FF8A66`**: fills, backgrounds, borders, rings, outlines, decorative lines/dots,
  Chart.js series, and text **only when rendered on the dark navy surface** (footer, sidebar headers).
- **Deep coral `#B35A3D`**: any foreground text/icon/link color rendered on a white/light surface —
  direct swap target for every current `#8a6e1e`.
- **Exception — the "Hub" wordmark accent** (navbar, footer, landlord/admin sidebar headers): stays
  bright `#FF8A66` everywhere, including on the white navbar. WCAG 1.4.3 exempts logotype text from
  contrast requirements, and this mirrors the already-uncommitted navbar edit.

If Axcee would rather not introduce a second hex, the fallback is: bright coral only for
fills/borders/decorative elements, and all current gold *text* roles fall back to navy/slate instead
of coral. Flagging this explicitly since it's the one place this plan adds a hex beyond the three
given — happy to swap approaches if preferred, otherwise proceeding with Deep Coral as above.

## Category → treatment mapping
Derived from the full usage catalog (3 exploration passes). One mechanical rule per role, not a
blind find/replace — role is determined by whether the hex colors a fill/border/ring or a
foreground text/icon:

| Current gold usage | New treatment |
|---|---|
| Focus rings/borders on inputs, selects, textareas, checkboxes (the dominant pattern, ~400+ sites, e.g. `text-input.blade.php`, `styled-select.blade.php`, `date-picker.blade.php`, every auth/form field in `layouts/app.blade.php`, `properties/show.blade.php`) | `#C9A84C` → `#FF8A66` (1:1 hex swap, same classes) |
| Active/selected states (unit-picker card border+ring, inquiry-modal selected option, date-picker selected day fill, filter-chip active borders on the homepage) | `#C9A84C` → `#FF8A66` |
| Decorative fills (footer SDG dot, section-header rule line, badge background tints `bg-[#C9A84C]/10`, avatar-fallback fills, Chart.js `borderColor`/`pointBackgroundColor`/doughnut segment, CSS scrollbar thumb, `maps.css` `.map-highlighted` outline) | `#C9A84C` → `#FF8A66` |
| Small text/links/icons on white (`text-[#8a6e1e]`: "View all"/"View profile" links, verified checkmarks, badge icon color, amenity/house-rule check icons, `search-pill` field icons, stat-card mini icons, `styled-select` checkmark) | `#8a6e1e` → `#B35A3D` |
| Hero highlighted word ("Cebu" in `properties/index.blade.php:18`) | `#8a6e1e`-style treatment → `#B35A3D` (large text on white is still text, not a logo) |
| Wordmark "Hub" accent (navbar white bg, footer/sidebar navy bg) | `#FF8A66` everywhere (logo exemption, see above) |
| **Primary CTA buttons** (`primary-button.blade.php`, and hand-rolled navy-fill CTAs matching the same pattern — "Contact Landlord", hero Search, wizard Next/Submit, inquiry/report modal submits) | fill `#060D26`→`#FF8A66`, text `white`/cream→`#060D26` (navy). Contrast navy-on-coral ≈ 8.6:1. This is the biggest single visual shift — makes coral literally "the action color" per Axcee's brief, while every page still shows only one (small) coral button, keeping page-level coral weight low |
| Secondary buttons (`secondary-button.blade.php`), admin/landlord sidebar active nav-link, table row hover, badge semantic colors (success/warning/error) | **Unchanged** — these already use navy or neutral mist, never used gold, no reason to introduce coral there (keeps dense admin/landlord data views scannable, avoids "excessive coral") |

## Files touched
- **Token layer** (do first, low-risk, high-leverage): `tailwind.config.js` (`colors.brand.gold`/`goldText` → new hex values, still unreferenced by views — matches existing pattern, not a bigger token-migration refactor), `resources/css/app.css` (scrollbar thumb/track), `resources/css/maps.css` (`.map-highlighted` outline).
- **Shared components** (each fixes many pages at once): `primary-button.blade.php`, `secondary-button.blade.php`, `search-pill.blade.php`, `property-card.blade.php`, `verification-status-badge.blade.php`, `publication-status-badge.blade.php`, `section-header.blade.php`, `text-input.blade.php`, `styled-select.blade.php`, `date-picker.blade.php`, `datetime-picker.blade.php`.
- **Layout shells**: `layouts/app.blade.php` (navbar/footer/auth modal), `layouts/landlord.blade.php`, `layouts/admin.blade.php` (sidebar wordmark only — active-nav stays navy).
- **High-traffic pages**: `properties/index.blade.php` (homepage/hero), `properties/show.blade.php` (largest single file, ~24 sites — badges, contact card, unit picker, all modals), `landlord/dashboard/index.blade.php`, `admin/dashboard.blade.php` (Chart.js configs).
- **The remaining ~95 files**: same two mechanical hex-swap rules (fill-role → `#FF8A66`, text-role → `#B35A3D`), applied per-file via grep + targeted edits — pattern is identical throughout, not enumerating every path/line here.
- **Docs**: `context/DESIGN.md` — update §3's Accent/Accent-text rows and rule text (73-74) to the new hexes, add a new changelog section mirroring §15's Ocean-Teal→Navy/Gold structure (what replaced what, the text-safe-variant reasoning, the CTA-fill change, the logo exemption, scale/count). Historical prose elsewhere (e.g. §6e's old "CTA stays coral #FF8A65" line) is left as-is, matching the doc's own convention of preserving history rather than editing old entries.
- This plan file gets saved to `plans/navy-coral-rebrand.md` per `CLAUDE.md`.

## Verification
- Grep `resources/` afterward for `#C9A84C`/`#8a6e1e` (case-insensitive) — should return zero hits outside DESIGN.md's own historical/banned-hex records.
- `npm run build` (or the project's existing asset build command) to confirm Tailwind/Vite compiles cleanly.
- Manual browser pass (dev server) on: homepage/hero, a property detail page (badges, contact card, unit picker, inquiry modal), an auth modal, a landlord dashboard page, an admin dashboard page (charts), and one long form (property wizard) — confirm white still dominates, navy carries structure, coral only shows at CTAs/prices/badges/active states/decorative touches, and no coral text on white reads as washed-out or fails contrast at a glance.
