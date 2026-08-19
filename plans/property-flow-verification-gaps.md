# Property Flow & Verification — Scope Notes

Source: analyst doc `context/PROPERTY FLOW AND VERIFICATION.docx`, compared against the
current implementation (`App\Http\Controllers\PropertyController`,
`App\Http\Controllers\Landlord\WalkInTenantController`, `SCHEMA.md`) on 2026-08-19.

This is a discovery/scope doc, not an approved implementation plan yet.

## What the analyst doc specifies but doesn't exist today

1. **No multi-step wizard / draft save.** `PropertyController::store()` takes property info +
   location + photos in one submit. Nothing is persisted (no Draft row) until the whole form
   validates. The doc calls for a stepper (Info → Location → Photos → Amenities → Documents →
   Units → Review) with a savable Draft state.
2. **Property-level amenities are not collected at creation.** `property_amenities` pivot exists
   in schema but is empty and unused (`SCHEMA.md` note — "nothing writes to it"). Only unit-level
   amenities (`unit_amenities`) are functional. See scope item below.
3. **No property verification documents.** No `property_documents` table, no upload UI, no admin
   review/approve/reject-with-reason flow for legal docs (proof of ownership, tax declaration,
   permits, etc.). Only `landlord_verifications` (identity, not property legitimacy) exists.
4. **No Cebu-only location restriction.** `address` is free text; `latitude`/`longitude` are
   optional and fall back to a hardcoded point (`10.3157, 123.8854`) if omitted. No province/city/
   barangay breakdown, no boundary validation. See scope item below.
5. **Single status field, not two.** `properties.verification_status` is
   Pending/Approved/Rejected only — no separate publication status (Draft/Unpublished/Published/
   Suspended/Archived), so "verified but currently unpublished" isn't representable.
6. **No submission checklist.** Missing-field feedback is per-field Laravel validation errors,
   not a consolidated "Your property cannot be submitted yet — missing: X, Y, Z" summary.
7. **Units are added after the property is already created/Pending**, rather than being one step
   inside a single Review & Submit at the end, per the doc's flow.

## New scope items (added 2026-08-19, on top of the doc)

### A. Cebu-wide location validation on property creation — **planned & implemented Aug 2026**, see `plans/cebu-validation-occupancy-amenities.md`
- Property location (used in both `store()` and `update()` in `PropertyController`) must be
  validated as within Cebu — not just Cebu City, all of Cebu (province-wide: cities/municipalities
  across mainland Cebu + islands like Mactan/Lapu-Lapu, Bantayan, Camotes, etc.).
- Current `latitude`/`longitude` are optional numeric fields with a hardcoded fallback — need
  either a bounding-box/polygon check against Cebu province, or a reverse-geocode + allow-list of
  city/municipality names, before a property can be created or edited.
- Needs a decision on validation method (bounding box vs. reverse geocoding vs. dropdown of Cebu
  cities/municipalities) before implementation.

### B. Occupant count validation against selected unit (walk-in tenant flow) — **planned & implemented Aug 2026**, see `plans/cebu-validation-occupancy-amenities.md`
- `StoreWalkInTenantRequest::rules()` validates `occupants_count` as `integer|min:1|max:20`,
  independent of which unit was selected.
- **Correction (Aug 2026): `property_units.occupancy_limit` is `NOT NULL`, not nullable** — it's
  `required|integer|min:1|max:100` on every unit create/update. The note below was wrong; the fix
  needed no null-guard on the unit side.
- Fix: validate `occupants_count <= unit.occupancy_limit` when the unit has a limit set (rule
  needs access to the selected `unit_id`'s `occupancy_limit`, e.g. a closure rule or
  `after()` hook in the FormRequest). Same fix likely needed wherever else occupants_count is
  set (e.g. the platform inquiry/reservation pipeline, if it also collects this) — checked:
  `StoreReservationRequest` doesn't collect `occupants_count` at all, so nothing to fix there.

### C. Property-level amenities at creation — **planned & implemented Aug 2026**, see `plans/cebu-validation-occupancy-amenities.md`
- Add an amenities step/field to property creation (and edit), writing to `property_amenities`
  (which currently exists but is dead — 0 rows, nothing writes to it per `SCHEMA.md`).
- Needs decision on whether property amenities remain a separate concept from unit amenities (per
  the analyst doc's intent — property = building-wide like Wi-Fi/security/parking, unit = in-room
  like AC/private bathroom) or whether to keep the current unit-only model and just surface
  aggregated unit amenities on the property page. Doc explicitly wants both levels to exist and be
  distinct.

## Open questions before scoping an implementation plan
- Do we build the full stepper/draft/document-verification system now, or land the three new
  validation/amenity items (A, B, C) first as smaller, independent fixes?
- For (A): what's the source of truth for "is this inside Cebu" — a static list of allowed
  city/municipality values, or geo-boundary math against a polygon?
- For (C): resurrect `property_amenities` as-is, or does the pivot's schema need changes first?
