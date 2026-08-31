# Landlord: de-duplicate Units vs Occupancy

**Status:** implemented Aug 30 2026 — pending manual verification
**Date:** 2026-08-30
**Trigger:** the sidebar's `Units` entry read as redundant. Investigation showed the real
duplication is **Units vs Occupancy**, not Units vs Properties.

---

## The problem

Three landlord surfaces render unit rows:

| Surface | Scope | Search | Status filter | Rent | Tenant | Edit | Delete |
|---|---|---|---|---|---|---|---|
| `Units` (`landlord.units.index`) | all properties | yes, server-side | yes | yes | yes | yes | yes |
| `Occupancy` (`landlord.occupancy.index`) | all properties | yes, client-side | yes | modal only | yes | yes | no |
| `Properties -> show -> Units` tab | one property | no | no | yes | — | yes | yes |

`Properties -> Units` is single-property, so it can't answer "what does Bed C rent for?"
without first recalling which property Bed C is in. **Units earns its sidebar slot.**

`Occupancy`'s "Unit Status Overview" card is the duplicate: same rows, same status chips,
same tenant, same per-unit edit link, and a **near-identical unit detail modal** (compare
`occupancy/index.blade.php:243-360` with the modal in `units/all.blade.php`). That card is
~258 lines of Blade + Alpine that Units already covers.

What *is* unique to Occupancy: aggregate occupancy rate, Vacancy Watch (idle rent, empty
longest), and the Recent Activity timeline. Those are analytics, not a unit list.

## The decision

**Units = manage units. Occupancy = monitor occupancy.** Occupancy loses its unit list and
becomes an analytics page whose stat tiles link into Units for the actual list. Nothing is
lost: per-property mix counts already exist on `properties/index.blade.php:216-229`, and the
per-property drill-in already exists at `properties/index.blade.php:245`.

Rejected alternative: delete Units and fold rent/delete into Occupancy. Fewer nav items, but
it merges managing and monitoring into one heavy page, which fights the mobile-first rule in
`context/DESIGN.md` §0b.

---

## Changes

### 1. `resources/views/landlord/units/all.blade.php` — drop the breadcrumb

Delete lines **16-24** (the `Properties > Units` crumb). Units is a top-level, cross-property
page; a crumb naming Properties as its parent contradicts the sidebar, which is what made the
nav entry read as a duplicate in the first place. The `<x-page-header>` below it already titles
the page.

### 2. `resources/views/landlord/occupancy/index.blade.php` — remove the unit list

- **Delete lines 105-362** — the whole `lg:col-span-3` LEFT column: the "Unit Status Overview"
  card, its Alpine `x-data` (search `q`, `status` chips, `open` accordion, `modal`), the
  property-group accordion, and the teleported unit detail modal.
- **Restructure the grid** at line 103: `grid-cols-1 lg:grid-cols-5` -> `grid-cols-1 lg:grid-cols-2`,
  and drop `lg:col-span-2` from the remaining column (line 365) so Vacancy Watch and Recent
  Activity sit side by side on desktop and stack on mobile. Keep `items-start`.
- **Trim `$statusStyles`** (lines 4-9) to the keys Recent Activity still consumes: `tile`, `dot`,
  `verb`. The `text` and `chip` keys existed only for the removed chips, tiles and modal.
- **Link the stat tiles into Units.** `x-stat-card` already accepts an `href`
  (`components/stat-card.blade.php:8`), so this is one attribute per card:
  - Total Units -> `route('landlord.units.index', ['property' => $selectedPropertyId])`
  - Available / Reserved / Occupied / Maintenance -> same, plus `'status' => '<Status>'`
  - Occupancy Rate tile stays a plain `div` — no list corresponds to it.

  This is what replaces the removed list: the page still answers "which units are occupied?",
  it just hands off to the page that owns that list.

### 3. `app/Http/Controllers/Landlord/OccupancyController.php` — drop the dead payload

- **Delete lines 58-87** (`$unitStatusOverview`) and its key in the `view(...)` array.
- **Drop `media` and `amenities`** from the `$units` eager load (line ~41). Once the overview is
  gone, `index()` only reads `availability_status`, `rental_fee`, `vacated_at`, `created_at`,
  `unit_label`, `property_id`, `unit_id`. Those two relations existed solely to fill the modal —
  loading them cost two extra queries plus every unit's media rows and amenity pivot on every
  page view.
- **Keep** `reservations.tenant` and `tenantNameFor()` — `export()` still uses them.

### 4. `Units` — add the missing Maintenance stat

Occupancy has five status tiles; Units shows four and silently omits Maintenance, even though
its filter dropdown offers it (`units/all.blade.php:130`). A Maintenance tile linking from
Occupancy would otherwise land on a page with no matching tile.

- `UnitIndexController::index()` — add `'maintenance'` to `$stats`.
- While there: `$stats` currently fires **four separate `whereHas` COUNT queries**. Replace with
  one grouped aggregate over the same base scope:

  ```php
  $counts = PropertyUnit::whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
      ->groupBy('availability_status')
      ->pluck(DB::raw('count(*)'), 'availability_status');
  ```

  then derive `total` as the sum. Five counts become one query.
- `units/all.blade.php` — add the Maintenance `x-stat-card` (grey `#64748B` value, `#94A3B8` bar,
  matching the Occupancy tile) and widen the stat grid `grid-cols-2 lg:grid-cols-4` ->
  `lg:grid-cols-5`.

---

## Files

| File | Change |
|---|---|
| `resources/views/landlord/units/all.blade.php` | remove breadcrumb; add Maintenance stat card; widen stat grid |
| `resources/views/landlord/occupancy/index.blade.php` | remove Unit Status Overview card + modal; 5-col -> 2-col grid; trim `$statusStyles`; stat tiles get `href` into Units |
| `app/Http/Controllers/Landlord/OccupancyController.php` | remove `$unitStatusOverview`; drop `media`/`amenities` eager loads |
| `app/Http/Controllers/Landlord/UnitIndexController.php` | add `maintenance` stat; collapse 4 counts into 1 grouped query |

No route changes. No schema changes. No sidebar changes — both entries stay.

Other references to these routes, all unaffected: `layouts/landlord.blade.php:204`,
`layouts/app.blade.php:560`, `landlord/analytics/index.blade.php:182`.

---

## Manual test checklist

Occupancy (`/landlord/occupancy`):

- [ ] Renders with no unit list; Vacancy Watch and Recent Activity sit side by side at >=1024px and stack at 375px.
- [ ] Five status tiles plus Occupancy Rate still show correct numbers and bars.
- [ ] Clicking **Available** lands on Units filtered to Available, and the Units result count matches the tile's number.
- [ ] Same for Reserved, Occupied, Maintenance, and Total Units (no status filter).
- [ ] With the property filter set, clicking a tile carries `?property=` through to Units.
- [ ] Occupancy Rate tile is not clickable.
- [ ] Recent Activity still renders coloured dots per status — check a row for each of Available / Reserved / Occupied / Maintenance.
- [ ] Vacancy Watch unchanged: idle rent, day badges (grey, amber >=30, red >=60), edit links work.
- [ ] Export Report still downloads and still includes the Tenant column.
- [ ] Landlord with zero properties: no errors, empty states hold.

Units (`/landlord/units`):

- [ ] No breadcrumb above the header; page header unchanged.
- [ ] Five stat cards; Maintenance count correct and total equals the sum of the four statuses.
- [ ] Stat grid is 2-up at 375px and 5-up at >=1024px, nothing clipped.
- [ ] Search, property filter, status filter (all five options), Clear, and grid/table toggle all still work.
- [ ] Unit detail modal, Edit and Delete still work from both grid and table view.
- [ ] Export CSV still matches the on-screen filters.

Properties:

- [ ] `Properties -> a property -> Units` tab untouched and still working.

---

## Not doing

- Not touching the sidebar — both `Units` and `Occupancy` keep their entries.
- Not touching the Properties -> Units tab.
- Not adding a Maintenance segment to the Properties index.
