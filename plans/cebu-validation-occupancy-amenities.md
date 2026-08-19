# Cebu location validation, occupancy limits, property amenities

## Context

The analyst doc `context/PROPERTY FLOW AND VERIFICATION.docx` specifies a property flow that the
codebase only partly implements. The full gap list lives in
`plans/property-flow-verification-gaps.md`. **This plan covers only three of those gaps** — the
ones Axcee scoped in. The wizard/draft-save, `property_documents` table, dual verification/
publication status, and submission checklist stay out of scope and get their own plan later.

The three problems being fixed:

- **A — Nothing constrains a property to Cebu.** `address` is free text and `latitude`/`longitude`
  are `nullable`, falling back to a hardcoded `10.3157, 123.8854` when omitted. A property can be
  created anywhere on earth, or with no pin at all — in which case it silently lands on the same
  downtown coordinate, which is a data-quality bug that also poisons the browse map.
- **B — Occupant count is never checked against the unit.** A walk-in tenant can be recorded with
  8 occupants in a 2-person unit. `property_units.occupancy_limit` exists and is required, but the
  walk-in validator never looks at it.
- **C — Property-level amenities do not exist in practice.** The `property_amenities` pivot has 0
  rows and nothing writes to it; amenities are unit-only. The doc wants building-wide amenities
  (Wi-Fi, CCTV, parking) distinct from in-room ones (AC, private bathroom).

Outcome: a landlord can only list a property inside Cebu with a real pin and a structured
city/municipality; walk-in occupant counts respect the unit's cap; and properties carry their own
building amenities, seeded from data landlords have already entered.

Model policy (now recorded in `CLAUDE.md`): planned on Opus 5, implemented on Sonnet 5.

## Live data state (verified against the dev DB, 2026-08-19)

Facts that drive several decisions below — worth re-confirming before running migrations:

- 22 properties. **0 on the fallback coordinate**, so there is no un-pinned-property cleanup.
- **8 properties at `8.9475, 125.5406` — Butuan City**, written by the fixture builders
  (`BuildsEscrowFixtures.php:103-106`, `WalkInScenarios.php:185-187`). They insert through
  Eloquent, bypassing validation.
- Properties are not all Cebu City: #4 is Mandaue, #7/11/12/13/14 are Talisay.
- 33 amenities, 130 `unit_amenities` rows across 38 units, **0 `property_amenities` rows**.
- **~66 of those 130 rows sit on building-level amenities** — Water Dispenser (8, the single
  most-used amenity in the system), 24/7 Security (5), Washing Machine (5), Shared Kitchen (5),
  CCTV / Elevator / Gated Entrance / Parking / Backup Generator (4 each).

---

## A. Cebu-only location validation

**Approach:** static LGU dropdown + a loose bounding box on the pin. No runtime geocoding API in
the request path — `resources/js/maps/location-picker.js` already reverse-geocodes through
Nominatim with `addressdetails=1`, so the client can auto-select the dropdown from the existing
response. The server never depends on that call.

1. **`config/cebu.php`** (new)
   - `lgus` — all Cebu cities and municipalities, including island LGUs (Bantayan, Sta. Fe,
     Madridejos, Poro, Pilar, San Francisco, Tudela).
   - `bounds` — `min_lat 9.30, max_lat 11.40, min_lng 123.15, max_lng 124.60`.
     **The east edge must reach 124.60** — the Camotes group sits at ~124.32–124.45 E. A tighter
     box (124.15) would reject four LGUs that are in the dropdown.
   - Config, not a table: this list changes roughly never; a table needs a seeder, model, and join
     for no benefit (`context/RULES.md` — KISS).

2. **`app/Rules/WithinCebu.php`** (new) — a `ValidationRule` taking the coordinate pair, failing
   with "Please pin a location inside Cebu." when outside `config('cebu.bounds')`. One object
   shared by store and update; no duplicated inline closures.

3. **Migration `add_locality_to_properties_table`** — three ordered steps in one file:
   nullable `city_municipality` VARCHAR(100) + `barangay` VARCHAR(100) → backfill by parsing the
   existing `address` (every live row is uniformly `Barangay, City, Cebu`, so the second
   comma-segment is the LGU) → `->nullable(false)->change()`.
   **Do not blanket-default to `'Cebu City'`** — that mislabels the six Mandaue/Talisay rows.

4. **Repoint the fixture builders** (`BuildsEscrowFixtures.php`, `WalkInScenarios.php`) to a real
   Cebu coordinate. Two one-line changes. Without them the fixture commands keep generating
   Butuan properties that violate the invariant, and once `WithinCebu` is enforced on `update()`
   those fixtures can never be edited through the form again.

5. **`StorePropertyRequest` / `UpdatePropertyRequest`** (new, matching the existing
   `app/Http/Requests/Landlord/*` convention) — extract the rules currently inline in
   `PropertyController::store()` (`:147-149`) and `update()` (`:208-210`) so the two cannot drift,
   and add: `city_municipality` required + `Rule::in(config('cebu.lgus'))`, `barangay` nullable,
   and `latitude`/`longitude` **required** plus `new WithinCebu(...)`.
   **Drop the `?? 10.3157` / `?? 123.8854` fallback** at `PropertyController:163-164` and `:231-232`.

6. **Views** (`resources/views/landlord/properties/create.blade.php` + `edit.blade.php`) — add the
   `city_municipality` select and optional `barangay` input beside the existing address field;
   update the map hint copy to say Cebu-only.
   **`location-picker.js`**: after a successful reverse geocode, match
   `data.address.city ?? town ?? municipality` against the select's options and auto-select;
   leave editable when no match. Warn inline when the returned province/state is not Cebu.

## B. Occupant count vs. the unit's cap

**`app/Http/Requests/Landlord/StoreWalkInTenantRequest.php`** is the single place to fix — both
walk-in entry points (`Landlord\WalkInTenantController:130` and
`Api\Landlord\WalkInTenantController:66`) share it.

1. Add an `after()` hook that loads the submitted `unit_id`'s `occupancy_limit` and fails
   `occupants_count` when it exceeds the cap. Message should name the real number
   ("This unit allows up to 2 occupants.") and sit alongside the existing `messages()` array,
   whose voice it should match.
   `occupancy_limit` is **NOT NULL** (`create_property_units_table:17`, and required on unit
   create/update), so the check needs no null guard on the unit side.
2. Keep `occupants_count` itself **nullable** — walk-ins are recorded after the fact and the
   landlord may not have the number. Skip the comparison when null. Making it required is a
   product decision outside this scope.
3. Client half is nearly free: `resources/views/landlord/tenants/walk-in/create.blade.php:20`
   **already** exposes `'cap' => $unit->occupancy_limit` in the Alpine `galleryProperties`
   payload. `$occupantsOptions` at `:43` is currently a flat `range(1, 20)` — bind it to the
   selected unit's `cap` so the dropdown can't offer an impossible number.
4. `StoreReservationRequest` does not collect `occupants_count` today, so nothing to fix on the
   tenant path. Leave a short note there so the same rule gets applied if it ever does.

## C. Property-level amenities

**Approach:** scope the amenity master, then *promote* the building-level tags landlords have
already attached to units up to the property that owns them. Promotion is what makes this work —
without it `property_amenities` stays empty, all 22 properties render a blank new section, and
~66 unit rows become invisible-but-attached (dropped on the next unit edit).

1. **Migration `add_scope_and_category_to_amenities_table`** — `scope` ENUM('property','unit','both')
   NOT NULL DEFAULT 'both'; `category` VARCHAR(50) nullable, promoting `AmenitySeeder`'s existing
   comment groupings into real data so both forms render grouped checkboxes, not a flat 33-item list.

2. **`AmenitySeeder`** — restructure to `category => [name => scope]`:
   - `property`: Elevator, Parking Space, Motorcycle Parking, CCTV, 24/7 Security, Gated Entrance,
     Rooftop Access, Backup Generator, Laundry Area, Shared Kitchen, Water Dispenser,
     Washing Machine, Near Public Transport, Near School / University, Near Market / Grocery,
     Curfew, Visitors Allowed, Pet Friendly
   - `unit`: Air Conditioning, Electric Fan, Private Bathroom, Shared Bathroom, Hot Shower,
     Bed Included, Study Table, Wardrobe / Cabinet, Private Kitchen, Refrigerator, Microwave,
     Balcony, Submeter (Electricity), Submeter (Water)
   - `both`: Wi-Fi
   - `updateOrCreate` keyed on `amenity_name` (already UNIQUE) so re-seeding never shifts an
     `amenity_id` and orphans the 130 existing pivot rows.

3. **Migration `promote_building_amenities_to_properties`** — for every `unit_amenities` row whose
   amenity is `scope = 'property'`: `insertOrIgnore` into `property_amenities` as
   `(unit.property_id, amenity_id)` — the composite PK dedupes when several units of one property
   carry the same tag — then delete the unit row.
   `down()` cannot faithfully reverse this (which unit did "CCTV" come from?). Make it a no-op
   with a comment saying why, rather than pretending.

4. **`app/Models/Amenity.php`** — add `scope`/`category` to `$fillable`; add `scopeForProperty()`
   and `scopeForUnit()`, each returning its own scope **plus `both`**, so views never hand-filter.

5. **`PropertyController::store()` / `update()`** — accept `amenities[]` (`array`, each
   `exists:amenities,amenity_id`) and `$property->amenities()->sync($ids)` inside the existing
   transaction. **Do not enforce `scope` in validation** — validate existence only. Scope is a
   display filter for the form, not a data constraint; enforcing it would make a later
   reclassification retroactively break saving, and would fail a landlord editing an old property
   on a checkbox the form no longer shows.

6. **Views** — grouped checkbox block in the property create + edit forms, mirroring the unit
   form's existing amenity UI so the two read alike.

7. **`properties/show` — same commit as migration (3), not after.** `context/DESIGN.md` §6e has the
   property page deriving its amenity list from `units.amenities`. Promotion moves ~66 rows off
   units, so if the page isn't updated in the same change **every property loses its building
   amenities**. Render property amenities as their own section, separate from the per-unit list,
   and re-add `amenities` to the eager load at `PropertyController::show():113-114`. Hard
   dependency, not polish.

---

## Sequencing — two passes

A and C touch the **same** two controller methods and the **same** two Blade files. Shipping them
separately means extracting the FormRequests and then rewriting those files again later.

1. **Pass 1 — B alone.** One `after()` hook + one Blade binding. No schema change, no overlap.
2. **Pass 2 — A + C together (the property-form pass).** One FormRequest extraction carrying both
   the Cebu rules and `amenities[]`; one migration batch; one set of Blade edits (locality select,
   barangay, grouped amenity checkboxes are all the same form); plus `properties/show` and the
   fixture repoint.

Migration order within pass 2: locality → amenity scope/category → **seeder** → promotion
(promotion reads `scope`, so the column must exist and be populated first).

## Files

**New:** `config/cebu.php`, `app/Rules/WithinCebu.php`,
`app/Http/Requests/Landlord/{Store,Update}PropertyRequest.php`, three migrations.

**Modified:** `app/Http/Controllers/PropertyController.php` (store/update/show),
`app/Http/Requests/Landlord/StoreWalkInTenantRequest.php`, `app/Models/Amenity.php`,
`database/seeders/AmenitySeeder.php`, `resources/js/maps/location-picker.js`,
`resources/views/landlord/properties/{create,edit}.blade.php`,
`resources/views/properties/show.blade.php`,
`resources/views/landlord/tenants/walk-in/create.blade.php`,
`app/Console/Commands/Concerns/BuildsEscrowFixtures.php`,
`app/Console/Commands/WalkInScenarios.php`.

**Docs to update as part of the work** (per `CLAUDE.md`): `context/SCHEMA.md` — new
`properties.city_municipality`/`barangay`, `amenities.scope`/`category`, the migrations log, and
the now-false "property_amenities is dead, don't eager-load it" note at `:166`;
`context/DESIGN.md` §6e — property vs unit amenity presentation;
`plans/property-flow-verification-gaps.md` — mark A/B/C planned and correct its claim that
`occupancy_limit` is nullable.

## Verification

Manual, in the running app (`npm run build`, `php artisan serve`, `php artisan reverb:start`).

**B:** on a 2-occupant unit, the occupants dropdown offers only 1–2; submitting 3 via a crafted
request is rejected naming the cap; 2 succeeds; leaving it blank still succeeds.

**A:** pin in Manila → rejected with the Cebu message. No pin at all → rejected (no silent
downtown fallback). Pin in Lapu-Lapu → dropdown auto-selects Lapu-Lapu City, submit succeeds.
Pin in Poro (Camotes, ~124.4 E) → accepted, confirming the bounding box. Pin in Bantayan →
accepted. Edit an existing Talisay property → its backfilled LGU shows preselected and saving
does not change it.

**C:** after migrating and seeding, the 22 existing properties each show building amenities they
never had (derived from their units), and no unit still lists CCTV / Elevator / Parking.
Re-run `php artisan db:seed --class=AmenitySeeder` twice → `unit_amenities` row count unchanged
(amenity_ids must not shift). Create a property with building amenities checked → they appear in
their own section on the property page, separate from unit amenities.

**Fixtures:** run the escrow and walk-in fixture commands → the properties they create land in
Cebu and can be opened and saved through the landlord edit form without a validation error.
