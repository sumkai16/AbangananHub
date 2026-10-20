# Rewrite `context/SCHEMA.md` as a lean, verified reference + drift-guard test

## Context
`context/SCHEMA.md` is 716 lines. Most of the length is incident stories and design essays sitting in
table-note cells, plus a 60-row migrations log that repeats `database/migrations/` and CHANGELOG.md.
It is also **wrong**: a read-only dump of the dev DB (`information_schema`, Oct 18 2026) shows about
15 undocumented columns, a documented table that doesn't exist (`complaints`), an orphan table, and
wrong nullability. The doc has drifted before (the `property_units` columns, the `reviews.unit_id`
claim), so the rewrite adds a test that fails when the doc and the DB disagree.

Outcome: a reference you can skim (target ~350–400 lines), true to the live schema, with every
rule code depends on kept, rationale living in ARCHITECTURE.md, and CI catching future drift.

Scope: documentation + one test. **No migrations, no schema changes.** The normalization proposal
(table splits) is a separate, later plan.

Models: implement with Sonnet 5 (per CLAUDE.md). First implementation step: copy this plan to
`plans/schema-md-rewrite.md`.

## Source of truth
The live schema, not migration filenames. Re-dump before writing (read-only):
`php artisan tinker --execute` querying `information_schema.COLUMNS`, `KEY_COLUMN_USAGE` +
`REFERENTIAL_CONSTRAINTS`, and `STATISTICS` for `abanganan_hub`. Only pending migration is
`2026_10_15_000000_backfill_agreed_rent_from_lease_snapshot` (data only, no schema change).

## Corrections the dump found (must land in the rewrite)
| Table | Fix |
|---|---|
| `users` | Add `email_verified_at`, `bio`, `profile_visibility` ENUM('public','landlords_only','private') default 'private', `expo_push_token`, `remember_token`. Remove the duplicate `email` row. `gcash_number` is varchar(255) |
| `landlord_verifications` | Add `id_back`, `id_type`, `selfie`, `id_image_hash`, `id_number`, `ocr_name`, `ocr_confidence`, `ocr_status`, and `business_name`, `description`, `logo_url`, `contact_number`, `business_address`. `user_id` is UNIQUE → 1:1, not "1:many, resubmission possible" |
| `properties` | `description` is NOT NULL |
| `property_media` | Add `unit_id` (nullable FK, cascade; 0 rows use it), `cloudinary_public_id`, timestamps |
| `property_units` | `unit_label` varchar(255); `occupancy_limit` NOT NULL; `availability_status` nullable; `rejection_reason` varchar(500) |
| `amenities` | `amenity_name` UNIQUE; has timestamps |
| `property_unit_amenities` | Undocumented, 0 rows, no code references (`unit_amenities` is the live pivot, 633 rows, used by `PropertyUnit`). Document under Cleanup candidates |
| `reservations` | `property_id` NOT NULL (doc says nullable); add `conversation_id` (FK, set null) |
| `notifications` | Add `notifiable_type`, `notifiable_id`, `conversation_id` (FK cascade), `updated_at`; index `(user_id, type, is_read)` |
| `reports` | Add `admin_notes`, `action_taken`, `resolved_by` (FK set null), `resolved_at`, `updated_at` |
| `payments` | `payout_status` is varchar(255), not ENUM; `released_by` has no FK; has timestamps |
| `complaints` | Table does not exist. Remove the section |
| `audit_logs` | Drop the "the one user FK that does NOT cascade" claim: ~15 FKs to `users` are SET NULL |
| framework | List once: `cache`, `cache_locks`, `failed_jobs`, `jobs`, `job_batches`, `migrations`, `password_reset_tokens`, `personal_access_tokens` (Sanctum, mobile API), `sessions` |

## New file shape (`context/SCHEMA.md`)
1. **How to read this file** (3 lines): reference only; verified against the DB; `SchemaDocTest`
   enforces it; why-it-is-this-way lives in ARCHITECTURE.md §6.
2. **ERD**: a Mermaid `erDiagram`, relationships only (no attribute blocks), each edge labelled with
   its FK column, e.g. `users ||--o{ properties : "landlord_id"`. Replaces §3 Relationships.
3. **Tables**, grouped by domain: Identity, Listings, Tenancy, Money, Chat, Trust & moderation,
   Scheduling, Ops/logs. Per table, exactly this:
   ```
   ### payments
   One money movement on a tenancy: online escrow, landlord-recorded, or a correction.

   | Column | Type | Null / default | Key | Note |
   |---|---|---|---|---|
   | payment_id | bigint unsigned | | PK | |
   | reservation_id | bigint unsigned | | FK reservations, cascade | |
   ...
   Indexes: `(reservation_id, billing_period)`, `(status, paid_at)`, `payout_status`.
   Rules:
   - `status = 'Voided'` is excluded by whitelists (`RentLedger::SETTLED_STATUSES`, `AnalyticsController::EARNED_STATUSES`).
   - Advance rent = `Monthly` rows with a future `billing_period`. Never add an `Advance` type.
   ```
   - **One column per row**, never `created_at / updated_at` combined (the test depends on it).
   - Note cell: ≤ ~20 words, present tense, no dates, no history.
   - Rules: only invariants that break code if ignored, max ~4 per table, each naming the
     method/constant that enforces it.
4. **Copies kept on purpose**: `reservations.property_id`, `viewing_requests.landlord_id`,
   `occupancy_activities.landlord_id/property_id`, `property_units.availability_status`,
   `occupancy_snapshots` counts, `reviews.landlord_id`, `audit_logs.actor_name/actor_email`.
5. **Cleanup candidates** (facts only, no action taken): 6 dead `property_units` columns +
   `is_furnished`; `reservations.duration_of_stay`; `property_unit_amenities` orphan; unused
   `property_media.unit_id`; `landlord_verifications` business_* columns duplicating
   `rental_businesses`; `payments.released_by` without FK; `payout_status` varchar; the
   `occupancy_snapshots` rate mismatch; the test review comment in `reviews`.
6. **Framework tables**: one line.
7. **Migration traps** (replaces the 60-row log): misnamed
   `2026_07_18_022220_add_unit_type_floor_deposit_description_to_property_units`; enum changes are
   raw `ALTER` guarded to MySQL; `promote_building_amenities_to_properties` has a no-op `down()`;
   `add_browse_sort_indexes` rollback needs `reviews_property_id_index` re-added first (`6a7240d`);
   adding a composite index can let MySQL drop an FK's auto index.
8. **Seeders**: 2 lines (AmenitySeeder idempotent on `amenity_name`; `name` accessor).

## Where the removed prose goes
For each long block, grep ARCHITECTURE.md first. Already covered → delete. Rationale missing →
add one row to ARCHITECTURE.md §6 Key Decisions Log. Pure incident history → delete (git and
CHANGELOG keep it).

| Block in SCHEMA.md | Action |
|---|---|
| walk-in / social-login account paragraphs | Covered by ARCHITECTURE §5 "Two ways a tenancy is born", "Social Login". Keep one rule: random unknowable password |
| `rental_businesses` nullable crash + Aug 21 backfill | Delete (history) |
| `properties` correction banner, `publication_status` essay | Rule: visibility only through `Property::isLive()` / `scopeLive()` / `scopeBrowsable()`. Add a Key Decisions row for "publication_status separate from verification_status" if missing |
| `property_documents` intro (private disk, admin route pair) | Check §5 File Upload; add a row if missing. Rule: `Expired` is derived |
| `property_units` misnamed-migration saga | Already ARCHITECTURE line ~150. Delete; trap list keeps the pointer |
| `property_amenities` history | Delete. Rule: form syncs only `scope = property` amenities |
| `reservations` two clocks | Covered by §5 Move-In Escrow. Keep the `isTurnoverClock()` rule |
| `payments` advance rent, walk-in split, no schedule table, released_by vs release_reason | Keep as 4 rules; add Key Decisions rows for advance-rent and walk-in split if missing |
| `reviews` property-grain correction | One rule: no `unit_id`, property-grain |
| `tenant_ratings`, `occupancy_snapshots` history | Keep "write-only, do not prune"; delete the rest |
| `audit_logs`, `settings` | Covered by §5 Admin Audit Trail / System Settings. Keep append-only + set-null rules |

Fix inbound references after the rewrite:
- `context/ARCHITECTURE.md:36` ("see that table's notes in SCHEMA.md for the Admin→Actor rename"): check the rename is still explained somewhere; move it into the §5 Audit Trail text if not.
- `context/ARCHITECTURE.md:356` ("see SCHEMA.md's property_units note") → point at ARCHITECTURE's own known-bug paragraph.
- `context/PRD.md:102` (cites the SCHEMA flag) → reword to stand alone.
- `context/CHANGELOG.md:3` (lists SCHEMA.md as a home for reasoning) → "SCHEMA.md is reference only".

## Drift-guard test: `tests/Feature/SchemaDocTest.php`
Match `PageIconTest.php` style (namespace `Tests\Feature`, class docblock, snake_case test names).
Runs against `abanganan_hub_test` (phpunit.xml), no DB writes, so no trait needed.
- Parse `context/SCHEMA.md`: every `### <table>` heading inside the Tables section; collect the
  first cell of each row of the following column table (skip header/separator rows, strip backticks).
- `test_every_documented_table_matches_the_database`: per table, assert
  `sort(doc columns) == sort(Schema::getColumnListing($table))`, with a failure message listing
  missing and extra columns by name.
- `test_every_app_table_is_documented`: `array_column(Schema::getTables(), 'name')` (unqualified
  names) minus a `FRAMEWORK_TABLES` constant must equal the documented set.
- Keep it ~60 lines; one private parser helper.

## Artifact correction
The published proposal (https://claude.ai/artifact/7a1qj1Nfdtsxx3Zc6Cqc2z) has errors the dump
exposed. Republish the same file
(`/tmp/claude-1000/-home-axci-Projects-AbangananHub-sumkai16/09ef1d82-9c86-41af-aea0-3fcc62c3ad55/scratchpad/schema-erd.html`)
with `edits`: `property_media` already has `cloudinary_public_id` + timestamps (only `unit_media`
needs `public_id`); add the `landlord_verifications` business_* duplication, the
`property_unit_amenities` orphan and the unused `property_media.unit_id` to the clean-ups; note
`reservations.conversation_id`; state `complaints` is confirmed absent; fix the doc-mistakes list.

## Steps
1. Copy this plan to `plans/schema-md-rewrite.md`.
2. Re-dump columns, FKs and indexes (read-only).
3. Write the new `context/SCHEMA.md` (shape above, corrections applied).
4. Move missing rationale into ARCHITECTURE.md §6; fix the 4 inbound references.
5. Add `tests/Feature/SchemaDocTest.php`; run it until green, fixing the doc, not the test.
6. CHANGELOG.md entry (newest first): what changed, the corrections found, the new test.
7. Republish the artifact with the corrections.
8. Do not commit until Axcee has read the new SCHEMA.md (per `test-before-commit`).

## Verification
- `php artisan test --filter=SchemaDocTest` passes.
- Negative check: temporarily delete one column row from SCHEMA.md → test fails naming that
  column → restore.
- `php artisan test` full suite still passes (was 430).
- `wc -l context/SCHEMA.md` ≈ 350–400.
- Spot-check 5 rules survived: `Reservation::TERMINAL_STATUSES`, `isTurnoverClock()`, no
  `Advance` payment type, `Property::scopeLive()`, `PropertyDocument` Expired derived.
- Mermaid ERD renders in GitHub/VS Code preview.
- Manual: Axcee skims the new file and confirms it reads well.
