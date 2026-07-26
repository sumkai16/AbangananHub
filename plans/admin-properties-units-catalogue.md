# Admin: Properties & Units catalogue pages

Unlocks the two `SOON` items under **Property Management** in the admin sidebar
(`resources/views/layouts/admin.blade.php:232-244`). Rental Businesses, Inquiries
and Messages stay `SOON` for now — they need schema decisions this plan doesn't make.

## The distinction being drawn

The admin panel already has two approval **queues**:

| Existing | Route | Scope |
|---|---|---|
| Property Verifications | `admin.listings.approval` | `properties` filtered by `verification_status` — a review workflow |
| Unit Approvals | `admin.units.index` | `property_units` filtered by `verification_status` — same |

Both are workflow inboxes: pick a Pending row, approve or reject, it leaves the list.
The new pages are **catalogues** — the full inventory across every status, built for
looking things up and spotting data health problems, not for clearing a queue.

## Naming / route decision

`admin.units.index` is already taken by the approvals queue. Renaming it to
`admin.units.approvals` would be tidier but touches ~10 call sites across
`dashboard.blade.php`, `layouts/admin.blade.php`, `units/show.blade.php` and
`PropertyUnitController` itself. **Decision: leave the approval routes alone**,
give the catalogues their own segment. Lower churn, and the URL reads correctly.

```
GET  /admin/catalogue/properties            admin.catalogue.properties.index
GET  /admin/catalogue/properties/export     admin.catalogue.properties.export
GET  /admin/catalogue/properties/{property} admin.catalogue.properties.show
GET  /admin/catalogue/units                 admin.catalogue.units.index
GET  /admin/catalogue/units/export          admin.catalogue.units.export
```

Unit detail reuses the existing `admin.units.show`
(`/admin/properties/{property}/units/{unit}`) — it already renders everything a
unit detail page needs; no second detail view.

Registered inside the existing `Route::middleware('admin')->prefix('admin')->name('admin.')`
group in `routes/web.php:180`. `export` must be declared **before** `{property}`
so it isn't swallowed by the wildcard.

## Files

**New**
- `app/Http/Controllers/Admin/PropertyCatalogueController.php`
- `app/Http/Controllers/Admin/UnitCatalogueController.php`
- `resources/views/admin/catalogue/properties/index.blade.php`
- `resources/views/admin/catalogue/properties/show.blade.php`
- `resources/views/admin/catalogue/units/index.blade.php`

**Modified**
- `routes/web.php` — five routes
- `resources/views/layouts/admin.blade.php` — Properties + Units become real links;
  the `@foreach` over the three `SOON` items shrinks to Rental Businesses only
- `context/ARCHITECTURE.md` — record the catalogue-vs-queue split

## Controller shape

Both follow `Landlord\UnitIndexController`'s pattern: a private `filteredQuery()`
shared by `index()` and `export()`, so a CSV always matches exactly what the page shows.

### PropertyCatalogueController

```
filters: search (title, address, landlord name/email)
         verification_status  Pending|Approved|Rejected
         availability_status  Available|Reserved|Occupied
         property_type        Bedspace|Room|Apartment|House
         business_id
         min_fee / max_fee
         health              no-media | no-geocode | no-units | stale
sort:    newest (default) | oldest | fee_asc | fee_desc | most_units
paginate 15, ->withQueryString()
eager:   landlord:user_id,first_name,last_name,email
         business:business_id,business_name
         media (media_type=Image, limit 1)
         withCount('units')
```

`health` filter clauses:
- `no-media` → `doesntHave('media')`
- `no-geocode` → `latitude = 10.3157 AND longitude = 123.8854` — the columns
  are actually `NOT NULL` (SCHEMA.md was wrong; corrected during build).
  `PropertyController::store()/update()` defaults to the Cebu City center
  when the landlord never drops a pin, so that sentinel pair, not `NULL`, is
  the "never pinned" signal
- `no-units` → `doesntHave('units')`
- `stale` → `availability_status = 'Available'` AND `updated_at < now()->subDays(90)`

Stat tiles: Total · Approved · Pending · Needs attention (union of the four health
clauses, deduped — compute with one `selectRaw` of conditional counts, not five queries).

`show()` — property detail: media gallery, full address + map pin (or a "no
coordinates" warning), landlord card linking to `admin.users.show`, business,
units table linking each row to `admin.units.show`, reservation history, review
summary, and the audit fields (`created_at` / `updated_at`).

### UnitCatalogueController

```
filters: search (unit_label, parent property title, landlord name)
         verification_status
         availability_status  Available|Reserved|Occupied|Maintenance
         property_id
         min_fee / max_fee
         health              long-vacant | stuck-maintenance | no-media
sort:    newest | oldest | fee_asc | fee_desc | vacant_longest
paginate 15
eager:   property:property_id,title,landlord_id + property.landlord
         media (Image, limit 1)
         current reservation → tenant name
```

- `long-vacant` → `availability_status = 'Available'` AND `vacated_at < now()->subDays(60)`
- `stuck-maintenance` → `availability_status = 'Maintenance'` AND `updated_at < now()->subDays(30)`

Occupancy tiles: Total · Occupied · Reserved · Available · Maintenance, plus an
occupancy-rate percentage (occupied ÷ total, guard the divide-by-zero on an empty DB).

Columns include `rejection_reason` (truncated, full text on hover) so an admin can
see why a unit was bounced without opening it.

## Schema landmine to route around

`property_units` has **no `unit_type`, `floor` or `security_deposit` columns** —
the migration named for them only altered the `availability_status` enum
(SCHEMA.md, verified 24 Jul 2026). Reads of those attributes return `null`
silently. So:

- Neither catalogue may show, filter on, sort by, or export a security deposit,
  unit type or floor.
- The units CSV omits them entirely rather than exporting blank columns.

Fixing that migration is **out of scope here** — it's a separate change with
`PropertyUnit::$fillable` and `Landlord\PropertyUnitController` implications.
Flagging it because it currently breaks unit *creation* outright.

## Views

Extend `layouts.admin`, `@section('page-title')`, `max-w-[1600px]` wrapper —
matching `admin/reservations/index.blade.php`, which is the closest existing
analogue (stat tiles → search bar → status tabs → table → pagination).

Palette per `context/DESIGN.md` and the reservations page: `#2AA7A1` primary,
`#156F8C` deep teal, `#F7FCFC` field bg, `#E2E8F0` borders, `#22C55E`/`#15803D`
success, `#FBBF24`/`#B45309` warning, `#EF4444`/`#DC2626` danger, `#94A3B8` muted.
Health warnings use the amber pair, not coral — coral `#FF8A65` stays reserved for CTAs.

Reuse `<x-empty-state>`, `<x-styled-select>` and `<x-stat-card>` rather than
hand-rolling filter selects and tiles.

Both index pages get an "Export CSV" button that carries the current query string,
streaming via `response()->streamDownload()` like the landlord units export.

## Explicitly not in scope

- No admin edit/delete of properties or units. Read + navigate only. Destructive
  admin actions (force-unpublish, revoke approval, soft-delete) are a follow-up
  once the read surface is confirmed useful — they need notification copy and an
  audit trail, and Audit Logs is itself still `SOON`.
- Rental Businesses, Inquiries, Messages — separate plans.

## Manual test checklist

1. Sidebar: Properties and Units no longer show `SOON`; each highlights when active;
   Property Verifications and Unit Approvals still work and still highlight correctly.
2. Properties: every filter alone, then search + type + status combined; confirm the
   result count matches the tile math and the filters survive pagination.
3. Health filters: seed one property with no media, one with `latitude = null`,
   one with no units — each appears under exactly its own filter and in "Needs attention".
4. Export CSV with filters applied → row count matches the on-screen total.
5. Property detail: a property with no coordinates shows the warning, not a broken map.
6. Units: occupancy tiles sum to Total; occupancy rate on an empty filter set shows
   0% and not a division error.
7. A rejected unit shows its `rejection_reason`; clicking through lands on the
   existing `admin.units.show`.
8. Empty states: filter to something with zero rows on all three pages.
