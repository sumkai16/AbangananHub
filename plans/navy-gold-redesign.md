**Status: implemented Sept 8 2026, pending manual visual verification by Axcee** (see `test-before-commit`
in CLAUDE.md — run the plan's own checklist below in-browser before deleting this file per the
plan-lifecycle rule).

# Navy/Gold Visual Identity — App-Wide Reskin

## Context

Axcee imported a Claude Design canvas (`AbanganAnHub.dc.html`) proposing a new visual identity for
AbangananHub: a navy/gold editorial look replacing the current Ocean Teal system. The mockup covers
the public/tenant surfaces only (Home, Login, Register, Browse, Listing Detail, Areas, a list-a-property
wizard); it has nothing for Landlord back-office or Admin.

**Decisions taken (agreed before planning):**
1. **Scope:** app-wide — Tenant, Landlord *and* Admin all move to the new identity.
2. **Approach:** restyle existing structure. Every current layout, component and feature stays; only
   colors, fonts, and card/button/badge treatments change. No layout rebuilds, no feature loss.
3. **Typography:** broad serif — DM Serif Display on page titles, section headings, card titles and
   display prices (matching the mock), with two scoped exceptions noted below.
4. **Glass nav:** not adopted. Headers stay flat and opaque, honoring the July 2026 glassmorphism
   retirement (DESIGN.md §6) and avoiding the `backdrop-filter` containing-block trap in RULES.md.

**Intended outcome:** the whole app reads as one new brand, with zero functional regressions and no
loss of the accessibility guarantees the current palette was built around.

---

## Two things to know before starting

**1. This is a ~6,276-site change, not a config edit.** The palette lives as raw bracket-hex Tailwind
classes (`bg-[#2AA7A1]`, `text-[#64748B]`) across ~135 Blade files. `tailwind.config.js` *does* define
`colors.brand.*`, but **zero views reference it** — those tokens are dead. Heaviest files:
`properties/show.blade.php` (316), `landlord/verification/create.blade.php` (224),
`landlord/properties/show.blade.php` (211), `layouts/app.blade.php` (164).

**2. Timing.** PRD §6 targets a September 2026 panel defense with "zero critical bugs during live demo,"
and PRD §8's readiness table is almost entirely *Not started* (VPS, DNS, OAuth callbacks, PayMongo
webhook — which has never once fired). Today is 2026-09-08. A cosmetic refactor of this size competes
directly with that list. **Mitigation: do this on a branch off `axci`, complete it in one pass, and
verify by rendering every surface before merging** — never half-migrated on the demo branch. Axcee's
call on sequencing; the risk is stated, not decided here.

---

## Palette mapping

| Current | Role | New | Uses |
|---|---|---|---|
| `#156F8C` Deep Ocean Blue | nav/headings/accents | `#060D26` navy | 570 |
| `#2AA7A1` Ocean Teal | **two roles — see below** | `#060D26` *or* `#C9A84C` | 844 |
| `#69D2C6` Aqua | badge/highlight fills | `#C9A84C` gold | 16 |
| `#FF8A65` Soft Coral | CTA | `#060D26` navy (cream `#F7F4ED` text) | 30 |
| `#1F2937` Charcoal | body text | `#060D26` | 1,488 |
| `#64748B` Slate Gray | muted text | `#5B6A8E` | 1,633 |
| `#E2E8F0` Soft Gray | borders | `#E2E4EC` | 899 |
| `#F7FCFC` Ice White | page background | `#F7F8FC` | 333 |
| `#EEF8F8` Mist Blue | section tints | `#ECEEF6` | 463 |
| `#0F172A` footer navy | footer, date-picker popover | `#060D26` | — |

New tokens with no current equivalent: **`#F7F4ED`** warm cream (text on navy buttons — the mock never
uses pure white there) and **`#8a6e1e`** dark gold (see accessibility below).

### `#2AA7A1` is the one hex that must NOT be blind-replaced

It plays two roles today and they split in the new system:
- **Primary button / solid fill → `#060D26` navy** with `#F7F4ED` cream text.
- **Accent, active state, icon tint, focus ring → `#C9A84C` gold.**

A global find/replace here turns every primary button gold — which both fails contrast for text on it
and contradicts the mock, whose buttons are navy. **These 844 sites get reviewed by role, not
substituted mechanically.** This is the single largest risk in the job.

### Accessibility — the new palette slots into the existing rule shape

Measured contrast ratios:

| Pair | Ratio | Verdict |
|---|---|---|
| `#060D26` on white | 19.2:1 | Pass |
| `#060D26` on `#F7F8FC` | 18.4:1 | Pass |
| `#5B6A8E` on white | 5.39:1 | Pass (muted text) |
| `#5B6A8E` on `#F7F8FC` | 5.08:1 | Pass |
| `#C9A84C` on white | **2.28:1** | **Fails — fills only** |
| `#C9A84C` on `#060D26` | 8.41:1 | Pass (gold on dark is fine) |
| `#8a6e1e` on white | 4.85:1 | Pass (the text-safe gold) |

Gold behaves exactly like `#2AA7A1`/`#69D2C6` do today, so DESIGN.md §3's existing rule needs its
values swapped, not its logic rewritten: **`#C9A84C` is fills, borders and large display text on dark
only — never small foreground text on light. Use `#8a6e1e` for gold text on light.**

### Semantic status colors stay as they are

Keep `#22C55E` / `#FBBF24` / `#EF4444` / `#94A3B8` and their darkened text variants
(`#15803D` / `#B45309` / `#DC2626`). They are load-bearing across the rent ledger, escrow clocks and
occupancy surfaces, with contrast-safe variants already documented (DESIGN.md §7). The mock's softer
`#7fae6a` / `#c0392b` appear only on small form-validation states and are not worth the churn.

---

## Typography

Net effect: **three families become two.** Poppins is dropped; Source Serif 4 → DM Serif Display.

- `tailwind.config.js` — `heading` and `display` both → `['"DM Serif Display"', 'Georgia', ...serif]`;
  `sans` stays Inter.
- `resources/css/app.css` — the `h1..h6, .font-heading` rule swaps Poppins → DM Serif Display.
- Google Fonts `<link>` — one byte-identical string in **all four** layouts
  (`layouts/app|admin|landlord|guest.blade.php`): drop Poppins + Source Serif 4, add
  `DM+Serif+Display:ital@0;1`, add weight 300 to Inter (the mock uses it for light body copy).

**Two traps to handle during implementation:**

1. **DM Serif Display ships a 400 weight only.** Every `font-bold` / `font-semibold` / `font-extrabold`
   currently sitting on a heading will render as browser-synthesized fake bold. Headings drop to normal
   weight and lean on size/color for hierarchy — which is how the mock's own headings are built. This is
   the same class of defect as the missing `ital` axis documented in DESIGN.md §4.
2. **Serif figures are proportional, not tabular.** Money columns in the rent ledger and payments tables
   (`landlord/tenancies/show`, `landlord/payments`, `tenant/tenancy/show`) must stay **Inter with
   `tabular-nums`** or peso amounts stop aligning. Scoped exception to "broad serif": serif for *display*
   prices (hero, card headline price), Inter for anything in a table column.

---

## Files to change

**Token/config layer (the small, high-leverage set):**
- `tailwind.config.js` — fonts + replace the dead `colors.brand.*` with the real, complete new palette.
- `resources/css/app.css` — heading font rule; `.scrollbar-thin-light` teal (`rgba(42,167,161,…)`, `#2AA7A1`).
- `resources/css/maps.css` — Leaflet pin/popup/cluster hexes. **Also fixes an orphaned
  `font-family: 'Figtree'`** here that matches no loaded font.
- The four `resources/views/layouts/*.blade.php` — font `<link>` (identical edit ×4).

**Shared components (change once, propagate everywhere) — all in `resources/views/components/`:**
`card`, `stat-card` (note its inline `style="color: …"` hex defaults), `section-header`, `confirm-modal`
(largest single concentration), `search-pill`, `styled-select`, `page-header`, `empty-state`,
`verification-status-badge`, `publication-status-badge`, `document-status-badge`, `date-picker`,
`datetime-picker`. `amenity-icon` needs nothing — it inherits `currentColor`.

**Per-view sweep:** the remaining ~135 Blade files, heaviest first (list above).

**Docs:** `context/DESIGN.md` §3 (palette), §4 (typography), §6 (glass rule unchanged — restate),
§6i (**see discrepancy below**), §7 (button/CTA/status rules), §10 (old teal hexes become the new
"always wrong" values), §11b (audit greps take the new hexes), §12 (admin token list). Per CLAUDE.md,
copy this plan to `plans/navy-gold-redesign.md` when work starts.

---

## Substitution method (this is where the known failure mode lives)

DESIGN.md §11b records a real incident: a bulk `sed` ordered `bg-emerald-50` before `bg-emerald-500`,
matched the shorter prefix, and produced `bg-[#22C55E]/[0.07]0` — a nonsense class that silently
rendered colorless across 32 sites in 5 files, and shipped past a "no raw colors remain" check.

Rules taken directly from that entry:
1. **Order slash-opacity variants first, then `\b`-anchored bases**, so `-50` can never match inside `-500`.
2. **Validate what was produced, not just the absence of what was replaced.** Run the §11b malformed-class
   and off-scale-opacity greps after every batch.
3. Substitute **hex → hex, preserving class shape** (`bg-[#2AA7A1]/20` → `bg-[#060D26]/20`). Do *not*
   convert to named `brand-*` tokens in this pass — that is a second refactor with its own risk surface,
   and bundling it multiplies the ways this can go wrong before a defense.
4. `#2AA7A1` is exempt from automation entirely — role-reviewed by hand (see above).

Order of work: config/CSS → shared components → verify a couple of pages render → per-view sweep
heaviest-first → docs.

---

## Flagged: DESIGN.md §6i documents a hero that does not exist

§6i describes the browse hero at length — full-bleed gradient, italic accent word, live-count trust
strip, collapse-on-filter driven by `$heroStats === null`. **None of it is in the code.** Verified:
no hero markup or `$heroStats` in `resources/views/properties/index.blade.php`, no `$heroStats` in
`PropertyController::index()`, and `<x-search-pill variant="hero">` is fully built but never invoked
anywhere. `/` and `/properties` render the identical grid view.

This is the third occurrence of the failure mode §5 already warns about (documenting intent rather
than what shipped). It matters here because the mockup's centerpiece *is* a photo hero, and §6i implies
one exists to restyle.

**Not in scope** — "restyle existing structure" was the agreed approach and there is nothing to restyle.
Two follow-ups for Axcee to decide separately: correct §6i to match reality, and optionally build the
hero (cheap — the `search-pill` hero variant is already written and waiting).

Also out of scope, for the same reason (no current analog): area explore page, tenant dashboard with
saved/recommended sections, all-units page, register role-toggle and password-strength meter.

---

## Verification

1. `npm run build` — must succeed.
2. **Audit greps from DESIGN.md §11b, updated to the new hexes** — every one returns zero:
   malformed classes `\[#[0-9A-Fa-f]{6}\](/\[0\.[0-9]+\])?[0-9]`, off-scale opacity modifiers,
   raw Tailwind color utilities, and the old palette hexes (which now join the banned list).
3. **Render every surface** at 375 / 768 / 1440px — a class-name defect is valid Blade and only shows
   up on the page (§11b): home/browse, property detail, auth modal + the four full-page auth views,
   landlord dashboard / units / tenancies / payments / verification wizard, admin dashboard /
   verifications / payments, conversations chat panel, `agreements/show`.
4. **Read the `<script>` blocks.** DESIGN.md §7 records Chart.js hex literals drifting off-palette
   because Blade-only greps can't see them — check `admin/dashboard`, `admin/users/index`,
   `admin/report-analytics`, and confirm each legend swatch matches its dataset color.
5. **Leaflet:** confirm map pins, cluster bubbles and popups pick up the new palette (`maps.css`).
6. **Contrast spot-check:** no `#C9A84C` as small text on a light ground anywhere; `#8a6e1e` used
   instead. Muted text is `#5B6A8E`.
7. **Serif weight check:** no synthesized bold on DM Serif Display headings; ledger/payment tables
   still Inter + `tabular-nums` with aligned columns.
8. Existing flows still work end-to-end (unstyled logic must be untouched): log in, browse, open a
   listing, send an inquiry, record a payment, void a payment.

---

## What actually happened (execution notes, added after implementation)

The plan above was followed with one change of method: shared components were hand-edited individually
(13 files), but the ~113-file remaining per-view sweep was done via a single audited PowerShell script
rather than file-by-file, since the originally-planned parallel-subagent delegation wasn't available in
this session. The script applied the exact hex→hex mapping and `#2AA7A1` role-split above, plus the
opacity-first ordering rule, then the full app was re-verified against every check in this file's own
Verification section.

Three things surfaced during execution that the plan above didn't anticipate:

1. **`#69D2C6` (Aqua) needed the same role-split as `#2AA7A1`, and initially didn't get it.** The plan
   only flagged `#2AA7A1` as needing per-use judgment; Aqua's 16 uses were mapped as a flat 1:1 to gold.
   A post-pass audit (grepping every resulting `text-[#C9A84C]`) found several on light backgrounds that
   fail contrast: a headline price and a "View profile" link on `properties/show.blade.php`, an
   "Account" heading word on `auth/register.blade.php`, and three checkbox accent-fill colors. All six
   were moved to `#8a6e1e`. **Lesson for next time: any hex being retired to an accent color needs the
   same text-vs-fill role check as `#2AA7A1`, not just the one hex called out in the plan.**
2. **Chart.js/PHP color literals outside `resources/views` were a real, separate blind spot** — exactly
   the failure mode DESIGN.md §7 already documents for Chart.js, but it also reached two backend files:
   `app/Http/Controllers/Landlord/AnalyticsController.php` and its `Api/Landlord` twin both hard-code an
   `'Available'` donut-segment color as `'#2AA7A1'` in a PHP array. Two Leaflet JS files
   (`resources/js/maps/{browse-map,property-map}.js`) and `public/js/verification-wizard.js`'s liveness
   guide color also carried literal hex outside any Blade template. None of these show up in a
   `resources/views`-scoped grep. Fixed; a repo-wide grep (`app/`, `resources/js`, `public/js`) is now
   folded into §10's audit note.
3. **A bulk-script encoding bug was caught before it corrupted anything.** See DESIGN.md §15 for the
   full account — cross-checking a `Select-String`-built file list against a `grep`-built one surfaced a
   13-file gap that traced to non-ASCII characters (₱, em dashes, arrows) being mis-decoded by .NET's
   default `ReadAllText` overload on BOM-less UTF-8 files. Fixed by forcing UTF-8 explicitly before
   running the substitution against the full file set.

Manual visual verification (this file's own checklist, items 3, 6 and 7 especially) has **not** been
run in-browser by Axcee as of this writing — do that before deleting this file per the CLAUDE.md
plan-lifecycle rule.
