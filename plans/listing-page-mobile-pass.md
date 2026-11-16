# Listing page mobile pass (properties/show) + login copy

Status: **planned, awaiting approval** (Nov 15 2026). Found while recording the promo video at 375px.

## Why

Recording Mika's flow on a phone frame showed four problems on the tenant listing page and one on the login page:

1. **Gallery is a desktop bento squeezed to 375px.** The 3-column grid gives the main photo ~2/3 of the width and two
   slivers beside it, and the main tile carries the Verified pill, two arrows, the heart and "Show all photos" at once.
   The heart did not respond to taps in the phone frame (the server saves fine: `POST /favorites/{id}/toggle` → 200).
2. **"Verified landlord" depends on the wrong thing.** The landlord row shows the checkmark and "Verified landlord" only
   when the *property's ownership documents* are verified (`hasVerifiedDocuments()`). Every published landlord has an
   approved ID check, so Rosa shows as plain "Landlord" — the trust claim the page should make, it doesn't.
3. **Directions hide the address box.** "Get directions" asks for location first; the address box only appears after
   the request fails or times out (~8 s, longer in practice). On a phone that is a dead wait.
4. **Login copy promises escrow.** "Sign online, and your deposit is held until move-in." Held payments are behind an
   admin switch that is off by default (PRD); the default is direct QR/InstaPay with "I paid" + landlord confirmation.

Desktop (`md:` and up) stays exactly as it is.

## Changes

### 1. Phone gallery (below `md`)

- New `md:hidden` block above the existing bento; the bento wrapper becomes `hidden md:block`. Nothing inside the bento
  changes.
- **One full-width swipeable strip**: a horizontal `overflow-x-auto snap-x snap-mandatory` row, one photo per slide,
  `aspect-[4/3]`, `rounded-2xl`, scrollbar hidden. Native swipe, no library, no arrows on phones.
- **Overlays, one per corner, nothing stacked**:
  - top-left: the same Verified pill / "Verified Property" popover trigger as desktop (same partial, same rules as §14);
  - top-right: the heart, 44×44 touch target;
  - bottom-right: a `1 / 3` counter that updates on scroll (IntersectionObserver), doubling as the "all photos" button.
- Tapping a photo opens the existing lightbox at that index (`openLightbox(i)`).
- **Heart**: moved into one partial (`properties/partials/favorite-button.blade.php`) used by both layouts. The two
  instances stay in sync through a `favorite-changed` window event, so saving on a phone and resizing never shows a
  stale heart. `@click.stop` so the tap never reaches the photo underneath. Optimistic toggle with rollback on failure,
  so the tap feels instant.

### 2. Landlord row

- Checkmark + "Verified landlord" keyed on `$property->landlord->verificationApplication?->isApproved()` (ID + selfie
  approved by admin), not on property documents.
- "Verified Property" (ownership documents, §14) is unchanged: still on the photo and still its own claim.
- Copy: `Verified landlord · Listed since 2026`.

### 3. Directions

- Address box shown from the start, as the primary control: input `Start from an address or landmark` + **Go**.
- Location becomes a secondary text button beside it: **Use my location**. Same geolocation call, same fallbacks.
- Mobile: input + Go on one row, "Use my location" under it. Desktop: one row.
- JS (`resources/js/maps/property-map.js`): button resets to "Use my location"; `showManualInput()` becomes a no-op
  focus on the input. Routing, OSRM, Nominatim and the route animation are untouched.

### 4. Login brand panel

- "Protected agreements" → **"Clear agreements"**: "Sign the rental agreement online and keep every payment and receipt
  in one place." (true for both payment routes; no claim about holding money).

## Files

| File | Change |
|---|---|
| `resources/views/properties/show.blade.php` | phone gallery block, bento wrapped `hidden md:block`, heart via partial, landlord row gate, directions markup |
| `resources/views/properties/partials/favorite-button.blade.php` | new: shared heart |
| `resources/js/maps/property-map.js` | button label + manual box always visible |
| `resources/views/auth/partials/brand-panel.blade.php` | third bullet copy |
| `context/DESIGN.md` | new section: phone gallery + landlord-verified rule (amends §26) |
| `context/CHANGELOG.md` | entry |

## Test checklist (Axcee, in browser)

1. 375px: listing page shows one big photo; swipe left/right moves photo by photo; counter changes `1 / 3 → 2 / 3`.
2. Tap a photo → lightbox opens on that photo. Tap the counter → lightbox opens.
3. Tap the heart → fills red instantly; reload → still red; Saved Listings shows it. Tap again → removed.
4. Rosa's card reads "Verified landlord · Listed since 2026" with the checkmark.
5. "What's nearby": address box visible immediately; type "Talisay City Hall" → Go → route + distance. "Use my location"
   still asks for location.
6. Desktop (1440px): gallery bento, arrows, "Show all photos", heart and directions look exactly as before.
7. Login page: third bullet reads "Clear agreements".
8. `npm run build` passes; `php artisan test` passes.
