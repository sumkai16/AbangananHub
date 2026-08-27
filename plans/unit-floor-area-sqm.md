# Unit floor area (m²)

## Context

The analyst asked for a square-meters field on units so tenants can see how big a unit is. Floor
area is one of the first things a tenant compares between two similarly-priced rooms, and nothing
in the app captures it today.

**Investigation turned up that half of this is already built — and broken.** `properties/show.blade.php`
references `$unit->size` in four places (the unit-picker row, the mobile bottom-sheet row, and the
slideout's info pill), but there is **no `size` column, no accessor, and no `$fillable` entry**:

```
["unit_id","property_id","unit_label","unit_type","floor","bedrooms","bathrooms",
 "is_furnished","description","rental_fee","security_deposit","occupancy_limit",
 "availability_status","vacated_at","verification_status","rejection_reason", ...]
```

So those four display slots have always rendered null and silently collapsed. This is the same
class of defect SCHEMA.md already documents twice (the misnamed `add_unit_type_floor_deposit_…`
migration; `landlord/units/edit` writing four columns it had no inputs for) — a view promising a
field the schema never had. This change is therefore mostly *finishing* a scaffolded feature
rather than inventing a new one, which is why the tenant-facing surface needs almost no new markup.

**Decisions taken** (confirmed with Axcee):
- **Optional, not required** — nullable, matching `bedrooms`/`bathrooms`/`is_furnished`. The ~30
  existing units predate it and every display site already handles null.
- **Floor area only** — `bedrooms`/`bathrooms`/`is_furnished` stay landlord-only this pass. (Noted
  as a real gap below, not fixed here.)

## Approach

Column `floor_area_sqm DECIMAL(6,2) NULLABLE`, sized like the other optional room details added in
Aug 2026. Display always goes through one accessor so the "24 m²" formatting exists in one place.

### 1. Migration (new file)

`database/migrations/2026_08_28_000000_add_floor_area_to_property_units_table.php`

Copy the shape of `2026_08_20_000000_add_room_details_to_property_units_table.php` exactly:

```php
$table->decimal('floor_area_sqm', 6, 2)->nullable()->after('bathrooms');
```

`down()` drops the column. No backfill — nullable is the honest state for units whose area nobody
recorded, and inventing a number for a listing would be worse than a blank (same reasoning as
`is_furnished`'s "NULL means not answered, not false" note in SCHEMA.md).

### 2. Model — `app/Models/PropertyUnit.php`

- Add `'floor_area_sqm'` to `$fillable` (RULES.md § Model Rules: every migration adding columns gets
  an immediate `$fillable` audit).
- Add `'floor_area_sqm' => 'decimal:2'` to `casts()`.
- Add a display accessor, because a `decimal:2` cast renders `24` as the string `"24.00"` and
  "24.00 m²" reads wrong. Four-plus display sites need the same formatting, which is exactly
  RULES.md's "extract when logic repeats 4+ times" threshold; precedent is
  `Amenity::getNameAttribute()` and `Reservation::getDurationOfStayAttribute()`:

```php
public function getFloorAreaLabelAttribute(): ?string
{
    return $this->floor_area_sqm === null
        ? null
        : rtrim(rtrim(number_format((float) $this->floor_area_sqm, 2), '0'), '.') . ' m²';
}
```

Returns `null` (not `'—'`) when unset, so each view keeps deciding how to render an absent value.

### 3. Write paths — validation + assignment

`nullable|numeric|min:1|max:9999.99` (a sub-1 m² unit is nonsense; the max matches `DECIMAL(6,2)`).
Assign as `$validated['floor_area_sqm'] ?? null`, sitting next to the existing `bedrooms`/`bathrooms`
lines. Four spots, two files:

- `app/Http/Controllers/Landlord/PropertyUnitController.php` — `store()` and `update()`
- `app/Http/Controllers/Api/Landlord/UnitWriteController.php` — its `store()` and `update()`

The API twin is not optional: ARCHITECTURE.md's July 27 2026 decision is that mobile write paths
mirror the web validation exactly, so they cannot drift.

Do **not** add `floor_area_sqm` to `PropertyUnitController::update()`'s `$materialChanged` check —
that flag resets `verification_status` to `Pending` and forces re-approval, which is right for rent
and photo changes but disproportionate for correcting a typo'd area.

### 4. Landlord unit form — create + edit

`resources/views/landlord/units/create.blade.php` and `landlord/units/edit.blade.php` (the wizard
reuses the create view via `?from=wizard`, so there is one form to change, not two).

Both already have a `grid sm:grid-cols-3` row holding Bedrooms / Bathrooms / Furnished. Add a
fourth "Floor area (m²)" `<input type="number" step="0.01" min="1" max="9999.99">` and widen that
row to `grid sm:grid-cols-2 lg:grid-cols-4` — mobile-first per DESIGN.md §0b, which explicitly
flags bare `grid-cols-N` without a narrower base. Follow the sibling inputs' exact class string and
`@error` block.

Also add `floorArea` to each view's Alpine `x-data` (seeded from `old()` / `$unit`, same as
`capacity` and `securityDeposit`) and render it in the **live preview rail** — the preview's meta
grid currently holds Capacity and Deposit tiles. Add a third "Floor area" tile shown only when
filled. Eyeball the grid at 375px and desktop when adding the third cell; a lone half-width tile on
a second row is acceptable, a visibly broken grid is not.

### 5. Tenant-facing — `resources/views/properties/show.blade.php`

This is where the dead `size` references get retired. Two edits light up all four sites:

- The `$unitsPayload` map (~line 65): `'size' => $unit->size ?? null` → `'floorArea' => $unit->floor_area_label`.
  Rename the key while touching it — `size` is vague and the payload's other keys are already
  camelCase (`rentRaw`, `depositRaw`, `hasActive`).
- The desktop unit-picker row (~line 354): `@if(!empty($unit->size)) … {{ $unit->size }}` →
  `$unit->floor_area_label`.

Then update the three JS readers to the renamed key: `u.size` → `u.floorArea` (~line 1247, mobile
sheet row) and `slideoutUnit.size` → `slideoutUnit.floorArea` (~lines 1494 and 1501, the slideout's
info pill — which already has a matching resize-arrows icon and a `<template x-if>` guard, so it
needs no new markup at all).

**Re-grep for `size` in this file after editing.** ARCHITECTURE.md's closing "recurring lesson" is
that a `replace_all` here previously missed a second occurrence differing only by indentation.

### 6. Remaining unit-detail surfaces

RULES.md § Modals & Overlays: *"Unit detail presentation is one shared pattern … across: unit
create/edit Live Preview, occupancy modal, landlord units-page modal, tenant slideout. Reuse it for
any new unit surface."* Preview and slideout are covered above; the other two are one payload line
plus one tile each, both mirroring the existing Capacity/Deposit pair:

- `resources/views/landlord/units/all.blade.php` — inline modal payload (~line 243, beside
  `'capacity'`) and the tile grid (~line 575).
- `app/Http/Controllers/Landlord/OccupancyController.php` (~line 80, beside `'capacity'`) and
  `resources/views/landlord/occupancy/index.blade.php` (~line 319).

Plus the plain server-rendered admin detail page:

- `resources/views/admin/units/show.blade.php` — add a "Floor Area" cell to the existing
  `grid grid-cols-2 sm:grid-cols-3` Unit Details block, rendering `$unit->floor_area_label ?? '—'`.

### 7. API resource + CSV export

- `app/Http/Resources/PropertyUnitResource.php` — add `'floor_area_sqm' => $this->attr('floor_area_sqm')`.
  Use `attr()`, not `$this->floor_area_sqm`, to preserve the sparse-select behaviour that resource
  was built for. *(Note: `bedrooms`/`bathrooms`/`is_furnished` are missing from this resource
  entirely — the Aug 2026 migration never updated it. Out of scope per the scope decision above,
  but worth a follow-up.)*
- `app/Http/Controllers/Landlord/UnitIndexController.php` — add a `Floor Area (sqm)` column to the
  CSV header and row, beside the existing `Capacity`. Emit the raw number, not the `m²` label, so
  the column stays sortable in a spreadsheet.

`Admin\UnitCatalogueController`'s export is deliberately left alone — ARCHITECTURE.md records that
it already omits `unit_type`/`floor`/`security_deposit`, so adding one lone new column there would
be inconsistent either way.

### 8. Dev fixtures + docs

- `database/seeders/PropertySeeder.php` — set a plausible `floor_area_sqm` where it already sets
  `bedrooms`/`bathrooms`, so the field is actually visible when manually testing rather than blank
  on every seeded unit. Leave a couple of units null on purpose to exercise the absent-value path.
- `context/SCHEMA.md` — add the column to the `property_units` table and a row to the Migrations Log.
- `context/ARCHITECTURE.md` — a decision-log row recording that `properties/show`'s `$unit->size`
  was dead for months and this change retired it, since that filename-vs-schema pattern is the
  recurring lesson that file already tracks.

## Verification

No automated tests (RULES.md § Testing — Axcee tests manually). Run `php artisan migrate`, then
`npm run build` + `php artisan serve` + `php artisan reverb:start`, and check:

1. **Landlord create** — `/landlord/properties/1/units/create`. Floor area field renders in the room-
   details row; typing a value updates the live preview tile. Submit → value persists.
2. **Leave it blank** — submit with floor area empty. Must save cleanly (nullable), and every
   surface below shows `—` or omits the row rather than "0 m²" or a broken tile.
3. **Landlord edit** — reopen the unit; the saved value is pre-filled. Change only the floor area
   and save: `verification_status` must **stay Approved**, not drop back to Pending.
4. **Tenant show page** — `/properties/{id}`. The value now appears in three previously-dead spots:
   the desktop unit-picker row's meta line, the slideout's info pill, and (below `lg`) the mobile
   bottom-sheet's unit row. Check at 375px and desktop per DESIGN.md §0b.
5. **Other unit surfaces** — landlord Units page modal, landlord Occupancy modal, admin unit detail.
6. **Validation** — `0`, `-5`, and `banana` are all rejected inline beside the field (RULES.md:
   validation errors stay inline, not in the flash modal).
7. **CSV** — export from the landlord Units page; the new column is populated and the row still
   lines up with the header.
8. **Grep check** — `grep -rn 'unit->size\|\.size' resources/views/properties/show.blade.php`
   returns nothing referring to the old key.
