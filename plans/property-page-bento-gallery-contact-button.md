# Property detail page — bento gallery for 2-photo units + bigger Contact Landlord button

## Context

An analyst mockup (image 1: "Modern Studio in IT Park") shows the target look for the tenant-facing property detail page: a photo gallery with one large image on the left and photos stacked on the right, and a prominent "Contact Landlord" button that fills the space next to the landlord's profile row. The live page for a different property ("Private Room Near USC Main", image 2) doesn't match on two points:

1. **Gallery**: `resources/views/properties/show.blade.php` already has this exact 1-big+stacked "bento" gallery for units with 3+ photos, but a unit with exactly 2 photos falls back to a flat, side-by-side layout instead (a deliberate past decision — see the comment at line 243 — made to avoid stretching 2 photos into a 3-row shape). The 2-photo case is the one that doesn't match the mockup.
2. **Contact Landlord button**: the button already exists (it's the pink pill that reads "Contact Landlord" or, once an inquiry is open, "Inquiry already active") but is undersized relative to the amount of card space around it — in the live page there's a large empty area around/below it that the mockup fills with a bigger, bolder button.

Confirmed with the user: for point 1, make 2-photo units use the same bento shape (not a literal duplicate thumbnail — the one remaining photo spans the full right-hand column instead of being split into two half-height cells). For point 2, restyle/enlarge the *existing* button rather than adding a second, separate action.

## Approach

Both changes are scoped to `resources/views/properties/show.blade.php`.

### 1. Bento gallery for 2-photo units

Around line 252, `$galleryGridClass` currently picks a flat `grid grid-cols-2 gap-2` shape for `$mediaCount === 2`. Change this case to reuse the same `grid grid-cols-3 grid-rows-2 gap-2 aspect-[4/3]` shape used for 3+ photos, so the hero tile (`col-span-2 row-span-2`, lines 267+) renders the same way in both cases.

The thumbnail markup at lines 325-330 (the `$mediaCount === 2` branch) currently renders a single `thumb-1` sized `aspect-[4/3]` (matching the old flat layout). Change its class to `row-span-2` (dropping the fixed `aspect-[4/3]`) so it fills both right-hand grid rows as one tall image, instead of a half-height cell. No JS changes needed — `setHero(1)`/`shiftHero()` and the `id="thumb-1"` contract stay the same.

Update the stale comment at lines 243-251 (currently explains *why* 2-photo units were kept flat) to reflect the new behavior — it no longer applies once this change lands.

### 2. Enlarge the Contact Landlord button

Lines 553-605 hold the primary action row: a `flex items-stretch gap-3` container with the CTA button (`flex-1`) and, for tenants, a favorites heart button (`h-full aspect-square`) beside it.

Increase the button's visual weight so it fills the surrounding whitespace instead of sitting as a slim pill:
- Bump vertical padding on the three button states (lines 556-559 log-in state, 560-563 owner state, 564-570 the actual CTA) from `py-3.5` to something taller (e.g. `py-5`) and bump font size (e.g. `text-[15px]` → `text-[16px]` or `text-base`), keeping the existing color (`bg-[#FF8A65]`), rounded corners, and all Alpine bindings (`x-on:click`, `x-text`, `:disabled`, `:class`) untouched — this is a style-only change.
- Since the heart button next to it is `h-full aspect-square` inside `items-stretch`, it will automatically grow to match the new button height — no separate edit needed there.
- Leave "Usually responds within a few hours" and "Report this listing" as-is; they're compact secondary text, not part of the empty-space problem.

## Files touched

- `resources/views/properties/show.blade.php` — the two edits above (gallery grid logic ~lines 240-347, contact button sizing ~lines 553-605).

## Verification

- `php artisan view:cache` to confirm the Blade still compiles.
- Since there's no browser automation in this sandbox, ask the user to check a 2-photo unit's page and a 3+-photo unit's page in-browser to confirm: (a) the 2-photo case now shows one big left image + one tall right image instead of side-by-side, (b) 3+-photo pages are visually unchanged, and (c) the Contact Landlord/heart button row now reads as a larger, more prominent action.
