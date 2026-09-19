# Landlord dashboard — clean look, semantic color fix, mobile-first pass

**Status:** pending manual verification (per CLAUDE.md — do not delete this file until Axcee has run the checklist below in-browser at 375/768/1024/1440px)

## Context

The landlord dashboard (`resources/views/landlord/dashboard/index.blade.php`) works, but three things undercut "clean, user-friendly, well-aligned":

1. **The attention tiles' colors are backwards.** "My open complaints" (a genuine concern) is tinted green — the app's "success" token — so it reads as good news. "Pending reservations" (a routine item awaiting a decision) is tinted red — reserved for failures/critical alerts — so it overstates urgency. "New reviews" (positive feedback) is tinted amber ("cautionary"), underselling good news. Two of the four also use raw off-token hexes (`#92400E`, `#166534`, plus `#F59E0B` on the donut) instead of the app's locked status-text hexes.
2. **The occupancy donut duplicates a decision the app already made once.** `context/DESIGN.md` §6l records this exact donut being deleted from `landlord/occupancy` in July 2026 ("four categories summing to a known total is part-to-whole, and length is easier to judge than angle") — and this page still has one, next to a KPI grid that leaves it visually unbalanced.
3. **The page was never validated at 375px**, which `context/DESIGN.md` §0b makes a hard requirement for Tenant/Landlord surfaces (not just Admin's desktop-first exception).

This plan fixes the color semantics, replaces the donut with a full-width stacked bar (matching the already-established §6l pattern), and gives every section an explicit 375px spec — all using only existing components (`<x-card>`, `<x-stat-card>`) and locked DESIGN.md tokens.

## Approach

All changes are confined to `resources/views/landlord/dashboard/index.blade.php`. **No controller changes** — every value the new markup needs is already passed to the view.

### 1. Attention tiles — reorder to a single 4-up row, recolor by real status

Replace the `md:grid-cols-[220px_1fr]` donut+tiles wrapper with one row:
`<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">` (matches the convention already used on `landlord/payments/index.blade.php` and `landlord/properties/index.blade.php` — 2-up on phone, 4-up at `lg`).

Each tile's tint is **conditional on its count** (zero = neutral mist, no false alarm/false positive on an empty dashboard) — same pattern `landlord/payments/index.blade.php:76-118` already uses for its stat cards:

| Tile | Count = 0 | Count > 0 | `sub` (0 / >0) |
|---|---|---|---|
| Pending reservations | `icon-bg="#ECEEF6"`, stroke `#060D26` | `icon-bg="#FBBF2412"`, stroke `#B45309` | "Nothing waiting" / "Awaiting your decision" |
| Unread messages | same neutral | `icon-bg="#FBBF2412"`, stroke `#B45309` | "Inbox is clear" / "Waiting on your reply" |
| Open complaints | same neutral | `icon-bg="#EF444412"`, stroke `#DC2626`, **value-color `#DC2626`** | "Reports you filed" (constant — matches controller's own comment that this counts reports *filed*, not received) |
| New reviews | same neutral | `icon-bg="#22C55E12"`, stroke `#15803D` | "Last 7 days" (constant) |

Justification against the locked color table (`context/DESIGN.md` §3, and the text-on-white variants at §7 line 481: amber text `#B45309`, green text `#15803D`, red text `#DC2626`): amber = "notifications/cautionary" fits a queue item awaiting the landlord (reservations, messages); red = "critical alerts" fits the one genuine unresolved problem (complaints); green = "successful actions" fits the one piece of good news (reviews). Red and green never sit adjacent in the row.

Also remove `class="group"` and `group-hover:stroke-white transition-colors duration-200` from all four tiles — `<x-stat-card>` sets the icon-chip background via an inline `style` attribute (`stat-card.blade.php:21`), which no Tailwind hover class can override, so the icon currently disappears (turns white-on-pale-tint) on hover. The card's own `hover:shadow-lg` is enough hover affordance.

### 2. Replace the donut with a full-width Unit Status band

Delete the `<x-card class="!p-5">` donut block and its `@php` math (`$radius`, `$circumference`, `$occupiedLen`, `$availableLen`, `$reservedLen`, `$occupancyTotal`). Keep `$occupiedPct`, guarded: `$totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100) : 0`.

Add one full-width `<x-card class="mb-6">` directly above the properties/activity row:
- Left block (label "Unit status" + big `{{ $occupiedPct }}% occupied` figure), then a stacked `h-2.5` bar (**Available → Reserved → Occupied**, matching the per-property bars' segment order below it — the donut used a different order, which read as inconsistent).
- Segments use the token hexes already in the file (`#22C55E`, `#FBBF24`, `#EF4444` — not the donut's stray `#F59E0B`).
- **Zero-count segments are skipped entirely, never rendered at 0% width** (§6l's own rule — a 0%-width div next to a gapped sibling still draws a hairline).
- The bar carries `role="img" aria-label="…counts…"` plus a `title` per segment, since a stacked bar is otherwise color-only (§6l, §9 accessibility).
- `$totalUnits === 0` renders "Add units to a property to start tracking occupancy." instead of an empty bar.
- Layout: `flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6` — stacks at 375px, goes horizontal at `sm`.

### 3. Header/CTA alignment

Change `sm:items-center` → `sm:items-start` on the greeting row's flex container. Center-aligning the CTA against the combined h1+pills block puts its optical center ~10px below the h1 baseline; top-alignment matches the h1's cap height, and matches how every other page header in the app aligns (`§6b`).

At 375px, make the CTA full-width (`w-full sm:w-auto`) and drop the `List`/`List a property` text-swap — one label, always "List a property," now that it has room.

### 4. 375px pass, section by section

| Section | 375px | ≥`sm` | ≥`lg` |
|---|---|---|---|
| Greeting | first-name variant, pills wrap, CTA full-width below | business-name variant, CTA top-right auto-width | unchanged |
| Attention tiles | `grid-cols-2`, whole card is the tap target | still 2-up | `lg:grid-cols-4` |
| Unit status band | stacked (label → % → bar → legend) | horizontal, fixed-width label column | unchanged |
| Properties list | thumbnail+text only; chevron `hidden sm:flex`; bar goes full-width (`sm:max-w-[220px]` instead of always-capped); add `active:bg-[#ECEEF6]` for touch feedback | chevron + occupancy % return, bar caps at 220px | `lg:col-span-2` |
| Recent activity | stacks below properties, full width | unchanged | `lg:col-span-1` |

### 5. Small cleanups found in the same file (fix alongside)

- `$occupiedPct` is reassigned inside the property `@foreach` (shadowing the page-level variable) — rename the loop's three locals to `$rowAvailPct`/`$rowReservedPct`/`$rowOccupiedPct` (keep `$propPct` as-is) to remove the latent trap.
- Bump the 6 occurrences of `transition-colors duration-150` in this file to `duration-200`, per §30's 200–300ms standard.
- Drop `group-hover:text-[#060D26] transition-colors duration-150` on the property title (line ~237) — navy transitioning to navy is a no-op.

## Critical files

- `resources/views/landlord/dashboard/index.blade.php` — all changes land here
- `resources/views/components/stat-card.blade.php` — reference only, not modified (props: `label`, `value`, `sub`, `valueColor`, `iconBg`, `percent`, `barColor`, `href`)
- `resources/views/landlord/payments/index.blade.php` — the conditional-tint/`sub`-caption precedent this plan copies (lines ~76–118)
- `context/DESIGN.md` §0b (mobile-first), §3 (color tokens), §6l (donut → band precedent), §7 line 481 (status text hexes), §30 (200–300ms transitions) — verified against the actual file, not just cited from memory
- `app/Http/Controllers/Landlord/DashboardController.php` — verified only, no changes needed

## Verification (manual, in-browser — do not mark this plan done without it)

**Visual, at each width — 375px, 768px, 1024px, 1440px:**
- [ ] No horizontal scroll at 375px; tiles/band/list all reflow per the table above
- [ ] All-zero state (current seed data): all four tile chips are neutral mist, no red/amber/green anywhere — page reads calm, not alarming
- [ ] Seed one pending reservation + one open complaint, reload: amber chip appears on Pending reservations, red chip + red value on Open complaints
- [ ] Hover every tile — icon must stay visible (regression check for the `group-hover:stroke-white` bug)
- [ ] Unit status band: bar segment order is Available → Reserved → Occupied, matches the per-property bars below it
- [ ] Set a landlord to 0 units, reload dashboard: band shows the "Add units…" empty state, not a blank bar

**Grep, from `resources/views` (all must return zero on this file):**
```bash
grep -nE '\b(bg|text|border|ring)-(emerald|amber|red|slate|green|gray|blue|indigo|purple)-[0-9]+' landlord/dashboard/index.blade.php
grep -nE '#(F59E0B|92400E|166534|1A1A2E|EEF2F5)' landlord/dashboard/index.blade.php
grep -n 'duration-150\|backdrop-blur\|group-hover:stroke-white' landlord/dashboard/index.blade.php
```

**After the pass:** run `php artisan view:clear` then actually render the page — a malformed Tailwind class is still valid Blade and only shows up rendered (§11b).
