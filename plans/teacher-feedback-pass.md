# Teacher feedback pass — apply what's valid, explain what isn't

## Context

The teacher reviewed the public home/browse page and gave 12 suggestions. Three parallel code surveys
checked each against the actual code. Several claims don't match the code (the font isn't a serif, the
"All" and "House" icons aren't alike, the profile has no "redirect to Browse" button). Rule for this
pass: apply what is valid and low-risk, leave the rest alone and explain why.
User chose: **two-column hero**, and **auth + profile forms only** for validation.

## Verdicts

| # | Suggestion | Verdict | Why |
|---|---|---|---|
| 1 | Font looks like Times New Roman | **No font change** (small cleanup only) | Body is Inter, headings Plus Jakarta Sans; neither falls back to a serif. The only real serif is the 404 page. The likely cause is the exact bug we just fixed: `@fontsource/*` and `chart.js` were not installed, so CSS failed to build and the browser showed unstyled default Times. Fix is `npm install` on the demo machine, not a new font. |
| 2 | Banner: too much space on right, add text | **Apply** | Hero is a single centered 900px column; the photo sides are empty. |
| 3 | Sort by is slow | **Apply (indexes) + measure** | Sort by price/rating orders by 3 correlated subselects with no covering index on `reviews`. Also, the dev DB `cache` table hung MySQL twice today, which alone would make every request slow. |
| 4 | Widen filters modal to remove scrollbar | **Apply (tweak)** | Already `max-w-6xl` with a 5-column grid, so "widen" only helps on >1152px screens. Reducing height is what removes the scrollbar. Phone scroll stays. |
| 5 | Limit list to 2 rows, last = View all, redirects to all listings | **Apply (partly done)** | Already 9 cards + "View all" tile = 2 rows at 5 columns, and it already links to `/properties`. Gap: the cap is a fixed 9, so it is 3-5 rows at narrower widths. Make it 2 rows at every width. |
| 6 | Search bar too close to nav | **Apply** | Only in the filtered/browse state: sticky band has `py-4` directly under the nav (`layouts/app.blade.php:508`). The clean home hero is not close. |
| 7 | Icons: All/House and Room/Apartment alike | **Apply (partly)** | All (4 squares) vs House (roof) are not alike. There is no "Room" category. The real look-alikes are Apartment, Condominium and Boarding House (all building outlines). Redraw those. |
| 8 | Forms: custom validation, not HTML5 | **Apply, scoped** | `novalidate` + per-field inline errors on auth + profile forms. Not all 86 forms (many have no error display and would fail silently). |
| 9 | Profile: "Show all properties" must stay on profile | **No change** | No such redirect exists. `landlord/profile/show.blade.php:213` "View all" switches tabs in-page, and `:340` "Show more" loads the next 12 in-page via fetch. Ask the teacher which page they meant. |

## Changes

### 1. Font cleanup — `resources/views/errors/404.blade.php`
Switch its self-defined serif `.font-display` (lines 12, 21, 26, 30) to `font-jakarta`, so no page renders a serif.
Leave `app.css` imports and `tailwind.config.js` alone (DESIGN.md §4 keeps the serif as an opt-in).

### 2. Two-column hero — `resources/views/properties/index.blade.php` lines 30-51
- Widen container `max-w-[900px]` to `max-w-[1400px]`; on `lg:` use `grid grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] gap-12 items-center`, text left-aligned.
- Left: eyebrow, h1, subtext, `<x-search-pill variant="hero" />`, stats chips (unchanged content).
- Right (`hidden lg:block`): frosted panel (`bg-[#060D26]/40 backdrop-blur-lg border-white/15 rounded-3xl`, per DESIGN.md §214 for panels on photography) with 3 trust points + `$heroStats['units']`. Heroicons only, no emojis.
- Below `lg:` keep the current centered layout. Do not touch the `$heroStats` gating (DESIGN.md §6i).
- Check the pill's `max-w-[900px]` (`components/search-pill.blade.php:27`) still fits the left column.

### 3. Sort performance
- New migration: index `properties(created_at)`, `properties(property_type)`, `reviews(property_id, is_hidden, rating)`. Check column names against `SCHEMA.md` first.
- Measure `scopeBrowsable`/`scopeBrowseFilters` (`Property.php:249-354`) with `EXPLAIN` and timing in tinker before and after.
- Do not add `Cache::` (cache driver is the `cache` table that hung MySQL).
- Report honestly: with ~12 dev properties the query is not the real cost; the local `php -S` server and the stuck cache table are.

### 4. Filters modal — `properties/index.blade.php` ~432, 450
`sm:max-w-6xl` to `sm:max-w-7xl`, `max-h-[92vh]` to `max-h-[94vh]`, body `py-5 space-y-4` to `py-4 space-y-3`. Phone behavior unchanged (accordions + internal scroll stay).

### 5. Two-row teaser — `properties/index.blade.php` ~607-616
Keep `take(9)` and the tile. Cards at index 5-8 get `hidden xl:block`, index 3-4 get `hidden lg:block`, so the grid is 3+tile (sm), 5+tile (lg), 9+tile (xl) = 2 rows each. Tile stays last.

### 6. Search spacing — `layouts/app.blade.php:508`
`py-4` to `py-5` on the pill container (more room under the nav; sticky band stays compact).

### 7. Category icons — `components/category-strip.blade.php` `$items` lines 19-34
Redraw Apartment, Condominium and Boarding House with clearly different Heroicons outline paths (e.g. Apartment = building-office, Condominium = building-office-2, Boarding House = door/key). Leave All, House, Bedspace.

### 8. Forms — add `novalidate` + inline `@error` per field (`<x-input-error>` exists in `components/input-error.blade.php`)
- `auth/login.blade.php:38`, `auth/forgot-password.blade.php:50`, `auth/reset-password.blade.php:50` (currently one `$errors->first()` banner only, so add per-field errors).
- `auth/register.blade.php` (already has errors; add `novalidate`).
- `profile/partials/update-profile-information-form.blade.php`, `update-password-form.blade.php`, `delete-user-form.blade.php` (the last has no error display).
- Keep `maxlength`. Server rules already cover every field (`LoginRequest`, `RegisteredUserController`).

## Verification
- `php -l` on edited PHP; `php artisan view:cache` (compile all Blade); `php artisan migrate` then `migrate:status`.
- Tinker `app()->handle(Request::create('/'))`: 200, hero markup present, no `section-texture`; `/properties?sort=price_low` returns 200.
- Forms: POST invalid login/register/forgot data in tinker and confirm each field's error text renders in the HTML.
- EXPLAIN before/after for the sort query.
- **Cannot verify in a browser:** hero layout, modal scrollbar, icon look, 2-row cap at each width. Ask the user to check these.
- Per CLAUDE.md, copy this plan into `plans/teacher-feedback-pass.md` when implementing.
