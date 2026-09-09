# Browse page: "Filters" panel for amenities + verified-only

## Context

The browse page (`/properties`) reference mockup shows an always-expanded filter bar (Property
Type / Unit Type / Must Have chips) with a "Filters" button next to Search. Axcee doesn't want a
button in the hero search bar and asked where else it should go — agreed placement: a **"Filters"
button in the results toolbar row**, next to the existing "Show map" control, opening a panel
(reusing the app's existing bottom-sheet/modal pattern) instead of permanently expanding the page.

Checking the mockup's filter categories against the real schema turned up two things worth
building around rather than copying blindly:

- **Property Type** is already a control in the main search pill (`x-search-pill`, single-select:
  Bedspace/Room/Apartment/House). Duplicating it as multi-select chips in the new panel would be
  two competing controls for the same field — the panel won't repeat it.
- **Unit Type** (mockup: Entire Unit/Private Room/Shared Room/Bedspace) has no real data —
  `property_units.unit_type` exists as a nullable free-text column but nothing ever sets it. Not
  buildable yet, so it's dropped from scope.
- **Amenities** is the one dimension with real data and no UI anywhere yet — and it was already
  anticipated: the landlord amenities wizard step's own copy says *"Optional, but tenants filter on
  these"* (`resources/views/landlord/properties/wizard/amenities.blade.php:35`).
- **Bonus find:** `verified` is already fully wired server-side (`Property::scopeBrowseFilters()`,
  and the page's own "Filtering by:" chip row already renders a removable "Verified only" chip) but
  has **no control anywhere to turn it on**. Cheap to add to the same panel.

So the panel's actual content: **"Verified listings only" checkbox + Amenities, grouped by
category, as multi-select toggle pills.** Both submit as normal GET params on full-page reload,
consistent with how Sort already works on this page — no AJAX/client-filtering exists here today
and this doesn't introduce it.

## Backend — `app/Models/Property.php` (`scopeBrowseFilters`)

Extend the existing scope (currently handles `location`/`type`/`price_max`/`verified`/`sort`,
`Property.php:264-298`) with an `amenities` key: an array of amenity IDs, **AND-semantics** (a
property must have *every* selected amenity to match — the "Must Have" copy implies AND, not OR).

For each selected amenity ID, add a `whereHas` requiring it on **either** the property itself
(`property_amenities`) **or** any of its units (`unit_amenities`) — a tenant filtering "Wi-Fi"
doesn't care whether it's building-level or unit-level, matching how the show page already treats
"Building amenities" and "Room amenities" as one combined offering:

```php
if (!empty($filters['amenities'])) {
    foreach ($filters['amenities'] as $amenityId) {
        $query->where(function ($q) use ($amenityId) {
            $q->whereHas('amenities', fn ($aq) => $aq->where('amenity_id', $amenityId))
              ->orWhereHas('units.amenities', fn ($aq) => $aq->where('amenity_id', $amenityId));
        });
    }
}
```

`verified` needs no scope changes — it already works, it just needs a checkbox.

## Backend — `app/Http/Controllers/PropertyController.php` (`index`)

- Pass `'amenities' => $request->query('amenities', [])` into the `browseFilters([...])` array
  (currently `PropertyController.php:80-86`).
- Add `'amenities'` to the `$request->hasAny([...])` check at the top of `index()`
  (`PropertyController.php:28`) so the hero collapses when an amenity filter is active, same as it
  already does for `location`/`type`/`price_max`/`verified`.
- Fetch the filter panel's amenity list — **unscoped**, not `forProperty()` (that scope excludes
  unit-only amenities like Air Conditioning/Private Bathroom, which the filter query above
  deliberately still matches via `units.amenities`):
  ```php
  $amenityGroups = Amenity::orderBy('category')->orderBy('amenity_name')->get()->groupBy('category');
  ```
  (`Amenity` is already imported in this controller for the `edit()` action.) Pass `amenityGroups`
  to the view.

## Frontend — `resources/views/properties/index.blade.php`

- Add `filtersOpen: false` to the page's root `x-data` (`index.blade.php:40`, alongside
  `mobileView`/`mapVisible`).
- **Filters button**, in the "RESULTS COUNT + SORT" row (`index.blade.php:227-238`), next to the
  existing "Show map" button — but visible at every breakpoint (unlike "Show map", which is
  `hidden lg:inline-flex` because the mobile List/Map switcher below covers that job on small
  screens; there's no mobile equivalent for Filters, so it can't be desktop-only):
  ```blade
  <button type="button" @click="filtersOpen = true"
      class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-full border border-[#5B6A8E]/30 bg-white text-[#060D26] text-[13px] font-semibold hover:bg-[#F7F8FC] transition cursor-pointer">
      <svg ...funnel icon.../>
      Filters
      @php $activeCount = count(request('amenities', [])) + (request()->boolean('verified') ? 1 : 0); @endphp
      @if($activeCount > 0)
          <span class="ml-0.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-[#060D26] text-[#F7F4ED] text-[10px] font-bold">{{ $activeCount }}</span>
      @endif
  </button>
  ```
- **Panel**, teleported to `body`, reusing the exact responsive shell from `properties/show.blade.php`'s
  inquiry bottom-sheet (`show.blade.php:1394-1399`: `x-teleport="body"` → overlay `fixed inset-0 ...
  flex items-end sm:items-center justify-center` → backdrop `absolute inset-0 bg-black/40` → panel
  `w-full sm:max-w-md ... bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl`) — bottom sheet on
  mobile, centered modal from `sm:` up, one template, no separate desktop variant needed (this
  panel has no multi-step flow like the inquiry modal does).
  - `<form method="GET" action="{{ route('properties.index') }}">`
  - Hidden inputs carrying forward `location`, `type`, `price_max`, `sort` via
    `request()->except(['amenities', 'verified', 'page'])` (mirrors the existing carry-through
    pattern in the Sort form, `index.blade.php:241-243`, just excluding the two fields this form
    itself sets).
  - "Verified listings only" checkbox (`name="verified" value="1"`, `@checked(request()->boolean('verified'))`).
  - Amenities grouped by `$amenityGroups`, each rendered as a toggle pill — reusing the
    `peer sr-only` checkbox + `peer-checked:` styled `<span>` pattern already established in
    `admin/users/create.blade.php:84-91` (checkbox visually hidden, label becomes the pill):
    ```blade
    <label class="relative cursor-pointer">
        <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}"
            @checked(in_array($amenity->amenity_id, request('amenities', [])))
            class="peer sr-only">
        <span class="inline-flex items-center px-3.5 py-1.5 rounded-full border border-[#E2E4EC] bg-white text-[13px] font-semibold text-[#5B6A8E] peer-checked:border-[#060D26] peer-checked:bg-[#060D26] peer-checked:text-[#F7F4ED] transition-all">
            {{ $amenity->name }}
        </span>
    </label>
    ```
  - Footer: a "Clear" link resetting just this panel's two fields
    (`route('properties.index', request()->except(['amenities', 'verified', 'page']))`) and an
    "Apply filters" submit button — explicit apply, not auto-submit-per-checkbox like Sort, since
    multi-selecting several amenity chips shouldn't reload the page on every click.
- **Active-filters chip row** (`index.blade.php:133-218`) gets two more chip types, copying the
  exact markup/style already used for `location`/`type`/`price_max`/`verified` there:
  - "Verified only" chip already exists (`index.blade.php:196-212`) — no change needed once the
    checkbox exists to set it.
  - One chip per selected amenity, each individually removable. Removing one amenity out of
    `amenities[]=1&amenities[]=2` isn't a plain `fullUrlWithoutQuery` (that drops the whole key) —
    build the href by re-merging the route with the array minus that one ID:
    `route('properties.index', array_merge(request()->except(['amenities', 'page']), ['amenities' => array_values(array_diff(request('amenities', []), [$amenity->amenity_id]))]))`.

## Verification

1. `php artisan view:clear` + `npm run build` after edits (this session already hit the
   stale-CSS trap twice — any new arbitrary/utility class needs a rebuild before it'll render).
2. In-browser (`agent-browser`), desktop (1440px) and mobile (390px):
   - Open Filters, select 2-3 amenities across different categories + "Verified listings only",
     click Apply → confirm the URL carries `amenities[]=...&verified=1`, the results count and
     grid actually narrow, and the active-filters chip row shows a chip per amenity plus "Verified
     only", each with a working "×".
   - Confirm the Filters button's count badge matches the number of active filters.
   - Remove one amenity chip via its "×" → confirm only that one amenity drops from the URL/results,
     the rest (and `verified`) stay.
   - Confirm `location`/`type`/`price_max` set from the main search pill survive opening the panel,
     applying an amenity filter, and reloading — nothing in the carry-through drops them.
   - Confirm the hero collapses (no "Find your next home" banner) once an amenity/verified filter
     is active, matching how it already collapses for the other filters.
3. Spot-check the actual filter correctness against seeded data: filter by an amenity that's
   unit-only (e.g. "Air Conditioning") and confirm properties whose *units* (not the property
   itself) carry it still show up.
