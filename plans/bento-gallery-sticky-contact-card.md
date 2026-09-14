# Property show page: bento gallery + sticky contact card

## Context
`resources/views/properties/show.blade.php` currently renders as a flat top-to-bottom flow (Sept 9 2026 flattening, see `context/DESIGN.md` §6e): a single wide `aspect-[21/9]` hero image beside a plain (non-sticky) contact card, then Description/Subunits/Utilities/Amenities/House rules/Location/Reviews stacked full-width below, then Nearby Rentals.

Axcee wants the page reshaped to match a reference listing layout: a **bento gallery** (one large photo + two smaller stacked photos beside it), and the **price/host card sticky** — staying pinned in view while the visitor scrolls through the rest of the listing, until they reach the Nearby Rentals band at the bottom.

This is a return to a two-column split the page used to have (§6e's original "media rail / editorial column" architecture, and the actual bento gallery markup that shipped briefly — commit `57c3b12`/`0b429d1` — before both were flattened out Sept 2026). Nothing here is unprecedented in this codebase; it's recombining two patterns that were each already built and shipped, just not at the same time or in this configuration.

## What's changing and why

**1. Gallery → bento (big + 2 stacked).** Reuse the exact bento markup that previously shipped (git commit `57c3b12`), re-skinned to the current navy/gold tokens (the old commit predates that rename): a `grid-cols-3 grid-rows-2 gap-2 aspect-[4/3]` container when there are ≥3 photos (big tile `col-span-2 row-span-2`, two small tiles stacked in the third column — `id="thumb-1"`/`id="thumb-2"`, each a fixed photo index, each `onclick="setHero(n)"`), degrading to a 2-up row at exactly 2 photos and a single tile at 1. No JS changes needed — `window.setHero`/`shiftHero`/`openLightboxAtHero` (already generic, `@push('scripts')` block near the end of the file) work unchanged; only the container markup around `#hero-img` changes. The Verified-Property popover and the favorite heart stay on the big tile exactly as now.

**2. Contact card → sticky through the whole listing, not just the hero row.** The card currently sits in a `lg:flex lg:flex-row` row that closes right after "Property details" — so there's nothing tall enough for it to meaningfully stick against. To make it stay in view until Nearby Rentals, its *containing block* has to span the full height of gallery + details + description + subunits + utilities + amenities + house rules + location + reviews — i.e. everything Nearby Rentals sits below.

The mechanism (validated against this file's own documented CSS history in DESIGN.md §6e): use **CSS Grid**, not flex, for the wrapping row. DESIGN.md's changelog explains that with Grid, a sticky item's containing block is its **grid cell**, which is always stretched to match the row's height regardless of `items-start` — that's exactly what we want here (the card should track the *combined* height of both rows and detach gracefully once that content ends), as opposed to flex (used deliberately elsewhere in this same file for the opposite effect — a rail that never detaches and is allowed to overlap the footer). `items-start` on the grid container keeps the card's own visible box compact/top-aligned rather than stretched to fill the cell.

Concretely, three grid children inside a `flex flex-col gap-8 lg:grid lg:grid-cols-12 lg:gap-8 lg:items-start` wrapper (mobile stays a plain stacked flex column — same visual order as today, no behavior change below `lg`):
- **Gallery + Property details** — `lg:col-start-1 lg:col-span-7 lg:row-start-1` (this is today's `lg:basis-7/12` block, unchanged in content, just re-classed for grid).
- **Contact card** — `lg:col-start-8 lg:col-span-5 lg:row-start-1 lg:row-span-2 lg:sticky lg:top-6` (today's `lg:basis-5/12` block, unchanged in content). `row-span-2` is what makes its cell span both rows, giving the correct stop point.
- **Everything else** (Description → Reviews, currently siblings of the row, full width) — moved inside the same grid as one `flex flex-col gap-8 lg:col-start-1 lg:col-span-7 lg:row-start-2` wrapper. The nested `gap-8` reproduces the exact spacing these sections already have today (they're currently direct children of an outer `flex flex-col gap-8`; nesting one level deeper with the same gap value is visually identical — each section's own `mt-10 pt-8 border-t` divider is untouched).

Nearby Rentals stays exactly where it is — a sibling *after* this grid closes, full width — so the card's sticky range ends right where it does today conceptually, just now it actually spans that whole distance instead of stopping after "Property details".

**Mobile is unaffected.** Because the three pieces stay in the same DOM order (gallery+details, then card, then the rest), a plain `flex flex-col` on mobile reproduces today's stacking exactly — no reordering tricks needed. The existing `lg:hidden` fixed bottom bar + two-step sheet (teleported, independent of this DOM) remains the real mobile contact affordance either way.

**3. One incidental fix while moving Subunits.** Its grid is currently `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4` — sized for when this section spanned the full ~1336px page width. Once it lives in the ~760px left column, `xl:grid-cols-4` would cram 4 cards into ~180px each. Drop that step, capping at `lg:grid-cols-3`.

## Files touched
- `resources/views/properties/show.blade.php` — the restructure above. No other views reference this layout.
- `context/DESIGN.md` §6e — add a changelog entry documenting the bento-gallery-returns + sticky-card change, following the section's existing changelog style (it meticulously logs every layout revision here, including the two prior ones this reverses/recombines).

## Out of scope (confirmed by not touching)
- No reordering of the header (badges/title/rating) relative to the gallery — the reference image shows it below the gallery, but that wasn't asked for and is a separate, bigger call.
- No change to the mobile sticky bottom bar/two-step sheet, the lightbox, unit picker grid, amenities, location map, or reviews — content and behavior unchanged, only re-nested for the grid.
- No new JS — `setHero`/`shiftHero`/`openLightboxAtHero` are reused as-is.

## Verification
- `php artisan serve` (or existing dev workflow) and open a property with ≥3 photos, exactly 2, exactly 1, and 0 — confirm bento shape degrades correctly and arrows/lightbox/"Show all photos" still work.
- Desktop (≥1024px): scroll from the hero down through Reviews — the price/host card should stay pinned at `top-6`, then stop and get "left behind" right as Reviews ends / Nearby Rentals begins, with no overlap.
- Resize to 375px — page should look and behave exactly as it does today (stacked order unchanged, fixed bottom bar still present).
- Spot-check the Subunits grid at 1024–1279px width (now capped at 3 columns) and 1280px+ (previously would have jumped to 4) to confirm cards aren't cramped.
