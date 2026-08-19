# Property Creation Wizard — Implementation Plan

Source: analyst mockups (10 wizard screens + landlord property detail + My Properties index),
provided 2026-08-19. Closes items **1, 6, 7** of `plans/property-flow-verification-gaps.md`,
which are the last outstanding pieces of `context/PROPERTY FLOW AND VERIFICATION.docx`.

Prerequisite work already landed (Aug 2026): `publication_status` w/ `Draft` member,
`property_documents`, `property_amenities` + `amenities.scope`/`category`, Cebu locality
validation, unit occupancy caps.

## Decisions taken

| Decision | Choice | Reasoning |
|---|---|---|
| Draft persistence | Real `Property` row, `publication_status = 'Draft'` | Photos, documents and units all need a real `property_id` FK. Gives the Draft tab a genuine producer. Survives browser loss. |
| Row creation point | **End of Step 2 (Location)**, not Step 1 | `address`, `city_municipality`, `latitude`, `longitude` are all NOT NULL. See "Why not Step 1" below. |
| `Archived` tab | Out of scope | Not in the enum; mockup shows it at (0). Revisit when an archive action exists. |
| First-pass scope | Wizard + dead-field bug + supporting schema | My Properties tab row and the Documents/Bookings/Payments detail tabs are a follow-up. |

### Why not Step 1

Inserting after Step 1 requires making `address`, `city_municipality`, `latitude` and `longitude`
nullable. Those became NOT NULL deliberately in Aug 2026 — the whole point of `App\Rules\WithinCebu`
was to remove the silent fallback to a hardcoded downtown coordinate. Relaxing them again to
accommodate a transient wizard state would weaken the invariant for every row in the table, and
every consumer of `latitude`/`longitude` (map pins, browse, distance sort) would need a null guard.

Step 1's scalar fields (`title`, `property_type`, `description`, unit count) instead live in the
session for exactly one hop, and are written together with Step 2's location in a single insert.
Steps 3-6 are updates against a real row. The only user-visible consequence: abandoning the wizard
on Step 1 leaves nothing behind, which is the correct behaviour anyway.

## Schema changes

1. `add_room_details_to_property_units` — `bedrooms` TINYINT UNSIGNED NULL,
   `bathrooms` TINYINT UNSIGNED NULL, `is_furnished` BOOLEAN NULL. Nullable because every
   pre-existing unit predates the fields; the unit form makes them required going forward.
2. **No barangay dropdown this pass.** The mockup shows Barangay as a `<select>`, but there is no
   barangay data anywhere in the repo — `config/cebu.php` stops at the 53 LGUs, and the only
   "barangay" strings in the seeders are prose inside property descriptions. Cebu has ~1,200.
   Inventing them would attach fabricated localities to real listings, which is strictly worse than
   the free-text field in place today. Step 2 keeps `barangay` as a text input, unchanged, and the
   dropdown is deferred to its own task once the official PSGC list is sourced and reconciled
   against the 53 LGU names already in config. This is the one deliberate divergence from the
   mockups.

No change to `properties`, `property_documents`, or the amenity tables — they already carry
everything the mockups show.

## Draft leak points (must all be closed)

A `Draft` row carries `verification_status = 'Pending'` (column default), so it is visible to
anything filtering on that column alone. Each of these needs a `publication_status != 'Draft'`
exclusion:

- `Admin\ListingController::index()` — the queue query (`:35`) **and** the three count badges
  (`:42-44`). Without this the admin sees half-built listings the moment a landlord reaches Step 2.
- `Landlord\PropertyController::index()` — drafts must not sit in the default list *unfiltered*,
  but they must remain reachable. See "Draft re-entry" below — excluding them outright strands them.
- `Admin\PropertyCatalogueController` (index + export) — verify before shipping.
- Tenant-facing paths are already safe: `Property::scopeLive()`/`scopeBrowsable()` require
  `publication_status = 'Published'`.

Follow the existing precedent — put the predicate in one scope on `Property` (e.g. `scopeSubmitted()`),
don't hand-copy it. That is the exact mistake `scopeLive()` was introduced to fix.

## Draft re-entry (in scope — a Draft with no way back is worse than no Draft)

The full tab row is a follow-up, but the minimum re-entry path ships with the wizard, otherwise a
Draft is unreachable as soon as the landlord closes the tab:

- Drafts render in the My Properties list with the existing `<x-publication-status-badge>` (it
  already handles `Draft`) and a **Continue setup** primary action in place of Manage/Edit.
- That action deep-links to `GET /properties/{property}/wizard/{step}`, resuming at the furthest
  step the row can satisfy — derived from the data itself (has amenities? has documents? has
  units?), not from a stored `current_step` column. A stored step pointer would drift out of sync
  with the row the moment a landlord edits something outside the wizard.
- Drafts are excluded from the list's counts and from the status filter's Verified/Under Review
  options, so they don't distort the summary cards.

## Routes

```
GET  /properties/create                       → wizard entry, Step 1 (existing route, re-pointed)
POST /properties/wizard/info                  → stash Step 1 in session, → Step 2
GET  /properties/{property}/wizard/{step}     → resume any step of an existing Draft
POST /properties/wizard/location              → INSERT Draft row, → Step 3
POST /properties/{property}/wizard/amenities  → sync property_amenities, → Step 4
POST /properties/{property}/wizard/documents  → reuse PropertyDocumentController::store, → Step 5
POST /properties/{property}/wizard/submit     → Draft → Published, verification_status Pending
```

Units in Step 5 reuse the existing `landlord.properties.units.*` resource routes, returning into
the wizard rather than the standalone units page. All wizard routes are landlord-only and go
through the existing ownership check / policy.

## Steps

**1 — Property Information.** Name, Type, Description, Number of Units. Session only.
`number_of_units` is a *target* used by the Review checklist ("4 units added" vs. declared) — it is
not persisted as a column; the real count is already derivable from `units`.

**2 — Property Location.** Province (fixed to Cebu, disabled), City/Municipality (existing LGU
dropdown), Barangay (text input — see Schema note 2), Street/Address, map pin. Reuses `WithinCebu`
and the existing location-picker JS bundle. **Inserts the Draft row here.**

**3 — Property Amenities.** Existing `Amenity::forProperty()` grouped by `category`. The mockup's
group names (Internet & Utilities / Security / Facilities / Other) differ from the seeder's
(Connectivity & power / Kitchen & laundry / Bath & comfort / Building & access / Rules & extras).
Keep the seeder's — they are already persisted in `amenities.category` and drive the existing
property and unit forms; renaming them is cosmetic churn with a migration attached.

**4 — Property Documents.** Reuses `property_documents` and its private-disk upload. Proof of
Ownership and Tax Declaration are marked required; SPA, Permit and Other stay optional. "Required"
is enforced at the Review step, not per-upload, so a landlord can still move forward and come back.

**5 — Units.** List of added units w/ Edit, plus `+ Add Unit` opening the three-screen sub-flow
(Unit Information → Unit Amenities → Unit Photos). Unit Information gains Bedrooms, Bathrooms and
Furnished. `occupancy_limit` keeps the cap validation added in the Aug 2026 commit.

**6 — Review & Submit.** Per-section completeness checklist with Edit deep-links back to each step,
matching the mockup: Property Information / Location / Amenities / Documents / Units / Unit
Amenities & Photos. This is gaps-doc item 6 — one consolidated summary replacing scattered
per-field errors. Submit sets `publication_status = 'Published'`, leaves `verification_status` at
`Pending`, and drops the landlord on the property detail page.

Submission blocks on: Steps 1 and 2 complete; at least one unit; both required document types
uploaded; every unit has a main photo.

## Bug fix (independent of the wizard)

`resources/views/landlord/properties/create.blade.php:66-81` renders **Monthly rent (₱)** and
**Occupancy limit** as `required`, but neither appears in `StorePropertyRequest` nor
`UpdatePropertyRequest`, and `PropertyController::store()` never reads them — `properties` has no
such columns (they live on `property_units` and are exposed only as derived accessors). Whatever
the landlord types is silently discarded. The mockup drops both from Step 1, confirming the intent:
delete the fields. Same shape as the `property_units` and `reviews` bugs recorded in `SCHEMA.md` —
markup promising a column that does not exist.

## Out of scope (follow-up)

- My Properties tab row (All / Verified / Under Review / Draft) — needs the Draft exclusion above
  as a prerequisite, so it lands second.
- Property detail page: Documents / Bookings / Payments tabs. Built today: Overview, Units,
  Amenities, Photos, Reviews, Activity Log (placeholder at `show.blade.php:790`).
- `Archived` publication status.
- Barangay dropdown — needs the official PSGC list for Cebu sourced and reconciled against the 53
  LGU names in `config/cebu.php` first.
- Abandoned-draft cleanup (a Draft with no activity for N days). No producer for the problem yet —
  revisit once real drafts exist.
- **Open question for the analyst:** the 6-step stepper has no *property* photos step, but property
  photos are required by `store()` today and drive the browse cards and the detail hero
  ("View Photos (12)" in the detail mockup). Assumption for now: property photos stay, folded into
  Step 1. Confirm whether the analyst intended to drop them in favour of unit photos only.

## As built (2026-08-20)

Implemented per the plan above, with two refinements found necessary during the build:

- **Photos moved from "Step 1" to the Step 2 submit**, not literally folded into Step 1's form.
  Step 1's fields are session-only (no property row yet), and Cloudinary uploads need somewhere to
  attach to — creating temporary un-owned media rows or stashing uploaded files across a request
  boundary would have added real complexity for no benefit. Functionally this is still "no separate
  photos step," just anchored to the request that actually creates the row.
- **`number_of_units` (the Step 1 target) is carried in session, keyed by property ID** once the
  Draft exists (`property_wizard.target_units.{id}`), not a database column, exactly as scoped. A
  session loss (new browser, cleared cookies) degrades gracefully — the Review step's Units count
  just stops showing "X of N planned" and shows the plain count instead.

**Incidental bug fixed along the way:** `resources/views/landlord/units/edit.blade.php` had no
form inputs at all for `unit_type`, `floor`, `description`, or `security_deposit` — despite
`Landlord\PropertyUnitController::update()` validating and writing all four. Every edit through
that page was silently wiping those fields to null (same shape as the dead-field bug this plan
already flags in the *create* form, but on *edit*, so worse — data loss on an existing unit, not a
rejected typed value). Fixed as part of adding the new bedrooms/bathrooms/furnished inputs to the
same card, since leaving it would have made the new fields subject to the identical bug.

**Verified end-to-end via live HTTP walkthrough** (not just code review): created a property
through all 6 steps against the running dev server, confirmed the Draft was invisible to the admin
queue and its counts, to `Property::live()`/`browsable()`, and to a direct tenant URL (404);
abandoned a second Draft mid-flow and confirmed "Continue Setup" resumed it at the exact step
`resumeWizardStep()` predicted; submitted the first Draft and confirmed it appeared in the admin's
Pending queue immediately after. Test properties removed afterward.

## Manual test checklist

1. Create a property through all 6 steps; confirm it appears as Under Review after submit.
2. Abandon at Step 3, close the tab, return to My Properties — the Draft is listed with a Draft
   badge and a **Continue setup** action that resumes at Step 3, not Step 1.
3. While that Draft exists, log in as admin: it must **not** appear in the listing queue, and the
   Pending count must not include it.
4. While that Draft exists, browse as a tenant: it must not appear.
5. Try to submit with no units → blocked, checklist shows Units incomplete.
6. Try to submit with Proof of Ownership missing → blocked, Documents incomplete.
7. Set a unit's Maximum Occupants to 2, then walk in 3 tenants → still rejected (regression).
8. Pin a location outside Cebu → rejected (regression).
9. Edit an existing pre-wizard property → still saves, no data loss (regression on the c32d814 fix).
10. Confirm the rent/occupancy fields are gone from the create form and nothing references them.
