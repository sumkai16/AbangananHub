# SCHEMA.md — Database Schema

## 1. ERD
(To be generated — see relationships below for the logical ERD)

## 2. Tables

### users
| Column | Type | Constraints | Notes |
|---|---|---|---|
| user_id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Custom PK — `$primaryKey = 'user_id'` in model |
| first_name | VARCHAR(255) | NOT NULL | |
| last_name | VARCHAR(255) | NOT NULL | |
| email | VARCHAR(255) | UNIQUE, NOT NULL | |
| password | VARCHAR(255) | NOT NULL | Hashed via Breeze |
| contact_number | VARCHAR(20) | NULLABLE | |
| gcash_number | VARCHAR(20) | NULLABLE | Added July 26 2026 — a landlord's payout destination. `User::hasPayoutDestination()` requires this and `gcash_account_name` both set before the admin payouts queue will let a payout be recorded |
| gcash_account_name | VARCHAR(255) | NULLABLE | Added July 26 2026 — name on the GCash account, so an admin can verify before sending |
| profile_picture | VARCHAR(255) | NULLABLE | Cloudinary URL |
| account_status | ENUM('active','suspended','inactive') | DEFAULT 'active' | Normalized to lowercase July 21, 2026 — was `ENUM('Active','Suspended')` with no `inactive` member, which silently didn't match the lowercase values the Users admin UI had been writing/reading (see RULES.md → Concurrency & State Transitions and migration `2026_07_21_000001_normalize_users_account_status`) |
| email | VARCHAR(255) | UNIQUE, **NULLABLE** | Was NOT NULL until July 24 2026; made nullable for walk-in tenants who often have only a phone number. MySQL allows many NULLs under a UNIQUE index, so real addresses stay unique. Anything rendering an avatar/name must use `?: '—'`, not assume a value |
| is_walk_in | BOOLEAN | DEFAULT false | A landlord-entered tenant, not a self-registered account. Cast to bool. Drives the **Walk-in** pill everywhere the user surfaces (landlord tenants, admin users, occupancy, exports) — the identity is landlord-asserted, never platform-verified |
| created_by_landlord_id | FK → users.user_id | NULLABLE, nullOnDelete | The landlord who added this walk-in. Scopes `User::walkInTenants()` |
| provider | VARCHAR(255) | NULLABLE | Added July 25 2026 for Google/Facebook login (`'google'`/`'facebook'`). NULL for password-only accounts. One provider per user — no multi-provider linking table |
| provider_id | VARCHAR(255) | NULLABLE, UNIQUE with `provider` | The provider's own user ID. An existing account is auto-linked (not duplicated) when a social login's email matches `users.email` |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

Walk-in tenants (added July 24 2026) are real `users` rows with a random unknowable password and `account_status='inactive'`, so the row can never be logged into — that is why they structurally cannot leave reviews or ratings. Keeping them in `users` (rather than a separate table) is what lets `reservations.tenant_id` stay NOT NULL so the ~20 views reading `$reservation->tenant->…` need no null-handling. Written by `Landlord\WalkInTenantController`.

Social login accounts (Google/Facebook, added July 25 2026) also get a random unknowable `Hash::make(Str::random(40))` password, same precedent as walk-ins — the account is meant to only ever be entered via the provider, not email/password. Unlike walk-ins, `account_status` stays default (`active`) and `email_verified_at` is set immediately since the provider already verified the address. Written by `Auth\SocialiteController::resolveSocialUser()`.

### user_roles
| Column | Type | Constraints | Notes |
|---|---|---|---|
| role_id | BIGINT UNSIGNED | PK | `$primaryKey = 'role_id'` |
| user_id | FK → users.user_id | NOT NULL | |
| role | ENUM('Tenant','Landlord','Admin') | NOT NULL | Landlord role granted on verification approval |
| assigned_at | TIMESTAMP | | |

### landlord_verifications
| Column | Type | Constraints | Notes |
|---|---|---|---|
| verification_id | BIGINT UNSIGNED | PK | `$primaryKey = 'verification_id'` |
| user_id | FK → users.user_id | NOT NULL | |
| government_id | VARCHAR(255) | NOT NULL | Local disk path (private) |
| liveness_passed | BOOLEAN | DEFAULT false | False = applicant fell back to manual capture (face-api.js unavailable); admin screen shows a warning banner |
| verification_status | ENUM('Pending','Approved','Rejected') | DEFAULT 'Pending' | |
| admin_notes | TEXT | NULLABLE | Rejection reason |
| reviewed_by | FK → users.user_id | NULLABLE | Admin who reviewed |
| reviewed_at | TIMESTAMP | NULLABLE | |
| submitted_at | TIMESTAMP | | |

### rental_businesses
**Corrected July 27 2026 — this table was missing four real columns
(`description`, `logo_url`, `logo_public_id`, `contact_number`,
`business_address`) and had the wrong name for one (`business_logo` doesn't
exist; the real column is `logo_url`).**

| Column | Type | Constraints | Notes |
|---|---|---|---|
| business_id | BIGINT UNSIGNED | PK | `$primaryKey = 'business_id'` |
| landlord_id | FK → users.user_id | NOT NULL, UNIQUE | one business per landlord |
| business_name | VARCHAR(255) | NULLABLE | made nullable July 27 2026 — see note below |
| description | TEXT | NULLABLE | |
| logo_url | VARCHAR(255) | NULLABLE | Cloudinary secure_url |
| logo_public_id | VARCHAR(255) | NULLABLE | Cloudinary public_id, needed to `destroy()` the old logo on replace |
| contact_number | VARCHAR(255) | NULLABLE | made nullable July 27 2026 |
| business_address | VARCHAR(255) | NULLABLE | made nullable July 27 2026 |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**`business_name`, `contact_number` and `business_address` were `NOT NULL`
with no default until `2026_07_27_000002_make_rental_businesses_columns_nullable`,**
even though `Landlord\ProfileController@update` (and its API mirror,
`Api\Landlord\ProfileController@update`) validate all three as `nullable`
and only write whatever was actually submitted. A blank text input reaches
the controller as an empty string, which `ConvertEmptyStringsToNull` turns
into `null` before validation runs — so a landlord's **first** profile save
crashed with `SQLSTATE[HY000] 1364` the moment any one of the three was left
blank. Same shape as the `property_units` bug above: validation promised
optional, the schema never agreed. Found via the mobile API's landlord
profile probe, but it was reachable from the web form the whole time.

### properties
**Verified against `2026_..._create_properties_table.php` July 26 2026 — the row below was wrong for
an unknown period: `business_id` does not exist (no property↔business FK; a business only links via
`landlord_id` → `rental_businesses.landlord_id`), `rental_fee`/`occupancy_limit`/`availability_status`
don't exist either (those live on `property_units` — `Property` exposes them only as derived
accessors, aggregating from its units), and `latitude`/`longitude` are `NOT NULL`, not nullable.**

| Column | Type | Constraints | Notes |
|---|---|---|---|
| property_id | BIGINT UNSIGNED | PK | `$primaryKey = 'property_id'` |
| landlord_id | FK → users.user_id | NOT NULL | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| house_rules | JSON | NULLABLE | cast to `array` |
| property_type | ENUM('Bedspace','Room','Apartment','House') | NOT NULL | |
| address | VARCHAR(255) | NOT NULL | Text-searched for browse |
| city_municipality | VARCHAR(100) | NOT NULL | Added Aug 2026 — must be one of `config('cebu.lgus')`, enforced by `StorePropertyRequest`/`UpdatePropertyRequest`. Backfilled from `address`'s second-to-last comma segment |
| barangay | VARCHAR(100) | NULLABLE | Added Aug 2026 |
| latitude | DECIMAL(10,7) | NOT NULL | `parseFloat()` client-side. Bounded to Cebu (`App\Rules\WithinCebu`, `config('cebu.bounds')`) since Aug 2026 — no more silent fallback to a hardcoded downtown point when omitted; a pin is required |
| longitude | DECIMAL(10,7) | NOT NULL | |
| verification_status | ENUM('Pending','Approved','Rejected') | DEFAULT 'Pending' | Admin's verdict on legitimacy. **No longer the sole gate on tenant visibility** — see `publication_status` below and `Property::isLive()`/`scopeLive()` |
| publication_status | ENUM('Draft','Published','Unpublished','Suspended') | DEFAULT 'Published' | Added Aug 2026. Orthogonal to `verification_status`: this answers "should it be live right now", not "is it legitimate". `Draft` has no producer yet (reserved for the property-creation wizard). `Suspended` is admin-only, set by `Admin\ReportController`'s "delist property" report action and lifted only by `Admin\ListingController::unsuspend()` — a landlord's own publish/unpublish toggle (`PropertyController::publish()`/`unpublish()`) explicitly refuses to touch a `Suspended` row. Neither `PropertyController::update()` nor its API twin ever writes this column, so an edit-triggered reset of `verification_status` to `Pending` (and a subsequent re-approval) leaves a suspension untouched — the bug this column was added to fix: the delist action used to reuse `verification_status = 'Rejected'`, which the next edit-and-reapprove cycle silently undid |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**Visibility funnels through one place**: `Property::isLive()` (single-row) and `scopeLive()`/`scopeBrowsable()` (query) are the only sanctioned checks for "can a tenant see this" — `verification_status = 'Approved' AND publication_status = 'Published'`, `scopeBrowsable()` additionally requiring an available approved unit. Before Aug 2026 this predicate was hand-copied in three places (`Property::scopeBrowsable()`, web `PropertyController::index()`, and the `navAreas` header composer in `AppServiceProvider`); all three now call the scope.

### property_documents
Added Aug 2026 — proof of ownership, tax declarations, and permits, one per property. The second
upload path (after `landlord_verifications`) to use the **private** disk (`storage/app/private`)
rather than Cloudinary: these are sensitive legal documents that must never get a public Cloudinary
URL, so they're served only through policy-gated controller routes
(`Landlord\PropertyDocumentController::preview/download`, `PropertyDocumentPolicy@view`), mirroring
`landlord_verifications`/`VerificationController` exactly.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| document_id | BIGINT UNSIGNED | PK | `$primaryKey = 'document_id'` |
| property_id | FK → properties.property_id | NOT NULL, cascade | |
| document_type | VARCHAR(100) | NOT NULL | Validated against `PropertyDocument::TYPES` |
| file_path | VARCHAR(255) | NULLABLE | **NULL = admin-requested, not yet uploaded** — a "requested" document is a row with no file, not a fifth status |
| file_name | VARCHAR(255) | NULLABLE | Landlord's original filename |
| document_number | VARCHAR(100) | NULLABLE | TCT no., tax dec no., permit no. |
| status | ENUM('Pending','Verified','Rejected') | DEFAULT 'Pending' | **`Expired` is deliberately not a stored member** — see below |
| rejection_reason | TEXT | NULLABLE | Required by `RejectPropertyDocumentRequest` when rejecting |
| expiry_date | DATE | NULLABLE | Permits expire; titles don't |
| verified_by | FK → users.user_id | NULLABLE, set null | |
| verified_at | TIMESTAMP | NULLABLE | |
| requested_by | FK → users.user_id | NULLABLE, set null | Set when an admin requests the document |
| created_at / updated_at | TIMESTAMP | | |

Index on `(property_id, status)`. `Expired` is computed on read (`PropertyDocument::isExpired()` /
`getDisplayStatusAttribute()`) from `status = 'Verified' AND expiry_date < today` rather than stored —
the same lesson as `publication_status` (see `properties` above): a nightly job maintaining a stored
`Expired` state can drift, so nothing maintains it. `Property::hasVerifiedDocuments()`
(`scopeCurrentlyValid()`) is the single source of truth behind the public "Verified Property" badge on
`properties/show.blade.php` — the badge is no longer unconditional decoration on every approved
listing with photos, it requires an actual currently-valid verified document. Documents never gate
property approval itself; the admin uses judgment, with the "request a document" action
(`requested_by`) as the lever for a specific missing one.

### property_units
| Column | Type | Constraints | Notes |
|---|---|---|---|
| unit_id | BIGINT UNSIGNED | PK | `$primaryKey = 'unit_id'` |
| property_id | FK → properties.property_id | NOT NULL | |
| unit_label | VARCHAR(100) | NOT NULL | e.g. "Room A", "Bed 3" — column is `unit_label`, not `unit_name` |
| unit_type | VARCHAR(50) | NULLABLE | Added July 27 2026 — see the note below the table |
| floor | VARCHAR(50) | NULLABLE | Added July 27 2026 |
| description | TEXT | NULLABLE | |
| rental_fee | DECIMAL(10,2) | NOT NULL | |
| security_deposit | DECIMAL(8,2) | NULLABLE | Added July 27 2026 |
| occupancy_limit | INT | NULLABLE | |
| availability_status | ENUM('Available','Reserved','Occupied','Maintenance') | DEFAULT 'Available' | Maintenance added for unit form |
| vacated_at | TIMESTAMP | NULLABLE | Occupancy tracking |
| verification_status | ENUM('Pending','Approved','Rejected') | DEFAULT 'Pending' | Admin approval; reset to Pending on material edit |
| rejection_reason | TEXT | NULLABLE | Admin rejection note |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**`unit_type`, `floor` and `security_deposit` were missing for months and were finally created July 27 2026** by `2026_07_27_000000_add_unit_type_floor_security_deposit_to_property_units`. The original cause was a **misnamed migration**: `2026_07_18_022220_add_unit_type_floor_deposit_description_to_property_units` promises all four in its filename, but its body contains a single `ALTER TABLE ... MODIFY COLUMN availability_status` adding the `Maintenance` enum member and nothing else. It was recorded in `migrations` as run (batch 1), so `migrate` reported nothing outstanding and `migrate:fresh` reproduced the gap exactly — which is why a *new* migration was needed rather than a re-run.

Until then: `PropertyUnit::$fillable` declared all three and `Landlord\PropertyUnitController::store()/update()` validated and wrote them, so **creating a unit threw `SQLSTATE[42S22] Unknown column 'unit_type'`**. Reads failed silently instead — a missing attribute returns null — so `agreements/show`'s "Security deposit" row could never render, `OccupancyController` reported null deposits, the units CSV exported blanks, and `properties/show`'s unit payload sent `deposit: null` to the cost breakdown. All of those now carry real values for units created after the fix; units that predate it (seeder rows) have NULL in the three columns, which every consumer already handles.

Column sizes were taken from the validation the controllers were already enforcing (`string|max:50` ×2, `numeric|max:999999.99`), not chosen fresh.

**Do not trust a migration by its filename.** This one was cross-checked against `Schema::hasColumn` before this entry was written; the previous version of this table was written from the filename alone and was wrong for months.

### property_media
| Column | Type | Constraints | Notes |
|---|---|---|---|
| media_id | BIGINT UNSIGNED | PK | `$primaryKey = 'media_id'` — verify this is set |
| property_id | FK → properties.property_id | NOT NULL | |
| media_type | ENUM('Image','Video') | NOT NULL | |
| media_url | VARCHAR(255) | NOT NULL | Cloudinary URL — output as-is, never wrap in `Storage::url()` |

### unit_media
| Column | Type | Constraints | Notes |
|---|---|---|---|
| media_id | BIGINT UNSIGNED | PK | `$primaryKey = 'media_id'` |
| unit_id | FK → property_units.unit_id | NOT NULL | onDelete cascade |
| media_type | ENUM('Image','Video') | NOT NULL | Filter to 'Image' for image galleries — videos must not render in `<img>` |
| media_url | VARCHAR(255) | NOT NULL | Cloudinary URL — output as-is |
| source | ENUM('camera','upload') | DEFAULT 'upload' | 'camera' = live in-browser capture; ≥3 camera photos required on unit create |
| caption | VARCHAR(150) | NULLABLE | Optional per-photo caption, shown to tenants |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### amenities
| Column | Type | Constraints | Notes |
|---|---|---|---|
| amenity_id | BIGINT UNSIGNED | PK | `$primaryKey = 'amenity_id'` |
| amenity_name | VARCHAR(255) | NOT NULL | |
| scope | ENUM('property','unit','both') | DEFAULT 'both' | Added Aug 2026. Display filter only — `Amenity::forProperty()`/`forUnit()` scopes, each also returning `both`. Not enforced in validation, so a later reclassification never breaks previously-saved data |
| category | VARCHAR(50) | NULLABLE | Added Aug 2026. Groups the checkbox UI on both the property and unit forms — `AmenitySeeder` is the source of truth |

### property_amenities (pivot)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| property_id | FK → properties.property_id | | Composite key |
| amenity_id | FK → amenities.amenity_id | | `belongsToMany` needs all 4 args |

**No longer empty (as of Aug 2026).** Was 0 rows/no landlord-facing form through July 2026 (see the superseded note this replaces). `PropertyController::store()`/`update()` now sync `amenities[]` here directly, restricted by the form to `scope = property` amenities. A one-time migration (`promote_building_amenities_to_properties`) seeded it by moving every `scope = property` row already sitting in `unit_amenities` up to the unit's property (deduped per property) and deleting the unit-side row — so existing landlords' building-level tags (Water Dispenser, 24/7 Security, CCTV, etc.) didn't just vanish when the two concepts split. `properties/show` now renders this as its own "Building amenities" section, separate from the per-unit list (DESIGN.md §6e). `Property::amenities()` is safe to eager-load again.

### unit_amenities (pivot)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| unit_id | FK → property_units.unit_id | | Joseph's table |
| amenity_id | FK → amenities.amenity_id | | |

### reservations
| Column | Type | Constraints | Notes |
|---|---|---|---|
| reservation_id | BIGINT UNSIGNED | PK | `$primaryKey = 'reservation_id'` |
| property_id | FK → properties.property_id | NULLABLE | |
| unit_id | FK → property_units.unit_id | NULLABLE | Unit-grain reservations |
| tenant_id | FK → users.user_id | NOT NULL | |
| reservation_date | DATE | NOT NULL | |
| target_move_in_date | DATE | NULLABLE | Negotiated. Clock 1 derives from it — see below |
| target_move_out_date | DATE | NULLABLE | |
| duration_of_stay | VARCHAR | NULLABLE | |
| agreed_monthly_rent | DECIMAL(10,2) | NULLABLE | Rent negotiated for this tenancy; the ledger's "expected". Falls back to `unit->rental_fee` via `Reservation::monthlyRent()`. Added July 24 2026 for walk-ins whose door rent differs from the listed price |
| rent_due_day | TINYINT UNSIGNED | NULLABLE | Day of month rent falls due (1–28). Falls back to the move-in day via `Reservation::rentDueDay()`, clamped to 28 so it exists in February |
| occupants_count | INT | NULLABLE | |
| rental_status | VARCHAR | DEFAULT 'Inquiry' | Inquiry → Under Negotiation → Pending Rental Agreement → Rental Agreement Signed → Occupied → **Completed**; or Rejected / Cancelled. `Completed` (July 24 2026) is the end-of-tenancy terminal state; before it an Occupied reservation had no exit and held its unit forever. **`Reservation::TERMINAL_STATUSES` = `['Cancelled','Rejected','Completed']`** is the single source every "is this unit spoken for" query filters on — see RULES.md note on the audit |
| agreement_terms_notes | TEXT | NULLABLE | |
| agreed_at / agreed_ip | TIMESTAMP / VARCHAR | NULLABLE | Set by `signAgreement()` |
| landlord_tc_accepted_at | TIMESTAMP | NULLABLE | |
| tenant_tc_accepted_at | TIMESTAMP | NULLABLE | |
| tenant_confirmed_move_in_at | TIMESTAMP | NULLABLE | **Only** written by a genuine tenant confirmation — never by auto-expiry or admin release |
| keys_turned_over_at | TIMESTAMP | NULLABLE | Turnover assertion. Null = Clock 1 running, set = Clock 2 running |
| move_in_deadline_at | TIMESTAMP | NULLABLE | Whichever clock is active. Null = no clock |
| move_in_disputed_at | TIMESTAMP | NULLABLE | Non-null = frozen, in the admin review queue |
| move_in_dispute_reason | TEXT | NULLABLE | Tenant's own words, or the system sentence on a Clock 1 timeout |
| move_in_last_reminder_on | DATE | NULLABLE | Per-day idempotency guard on reminders |
| handover_at | TIMESTAMP | NULLABLE | The agreed key-handover slot. Once confirmed it becomes Clock 1's basis instead of `target_move_in_date` |
| handover_proposed_by | FK → users.user_id | NULLABLE | Who put the current slot up — the *other* party is the one who may confirm it |
| handover_proposed_at | TIMESTAMP | NULLABLE | When the current proposal was made |
| handover_confirmed_at | TIMESTAMP | NULLABLE | Null = proposed only. Set = both agreed, and only then does the slot move `move_in_deadline_at` |
| rejection_reason | TEXT | NULLABLE | |
| remarks | TEXT | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

Index `reservations_move_in_deadline_index` on `(move_in_deadline_at, move_in_disputed_at)` — the nightly command scans on both together.

**One deadline column, two clocks.** `keys_turned_over_at` is the switch: null means the deadline belongs to the landlord (turn over the keys), set means it belongs to the tenant (confirm move-in). Any code reading `move_in_deadline_at` must check which clock it is looking at — `Reservation::isTurnoverClock()` exists for exactly this.

### conversations
| Column | Type | Constraints | Notes |
|---|---|---|---|
| conversation_id | BIGINT UNSIGNED | PK | `$primaryKey = 'conversation_id'` — permanent, no schema swap |
| tenant_id | FK → users.user_id | NOT NULL | |
| landlord_id | FK → users.user_id | NOT NULL | |
| property_id | FK → properties.property_id | NOT NULL | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### messages
| Column | Type | Constraints | Notes |
|---|---|---|---|
| message_id | BIGINT UNSIGNED | PK | `$primaryKey = 'message_id'` |
| conversation_id | FK → conversations.conversation_id | NOT NULL | |
| sender_id | FK → users.user_id | NOT NULL | |
| message | TEXT | NOT NULL | |
| sent_at | TIMESTAMP | | |

### favorites
| Column | Type | Constraints | Notes |
|---|---|---|---|
| favorite_id | BIGINT UNSIGNED | PK | `$primaryKey = 'favorite_id'` |
| tenant_id | FK → users.user_id | NOT NULL | |
| property_id | FK → properties.property_id | NOT NULL | |
| created_at | TIMESTAMP | | |

### reviews
| Column | Type | Constraints | Notes |
|---|---|---|---|
| review_id | BIGINT UNSIGNED | PK | `$primaryKey = 'review_id'` |
| tenant_id | FK → users.user_id | NOT NULL | |
| property_id | FK → properties.property_id | NOT NULL | |
| landlord_id | FK → users.user_id | NOT NULL | Which landlord owns the property; lets reviews roll up to a landlord score |
| rating | TINYINT UNSIGNED | NOT NULL | 1–5 |
| review_comment | TEXT | NULLABLE | Audit: remove test comment "hahaha suled" before defense |
| landlord_reply | TEXT | NULLABLE | Landlord's reply to the review |
| landlord_replied_at | TIMESTAMP | NULLABLE | |
| is_hidden | BOOLEAN | DEFAULT false | Admin-moderated; excluded from every average |
| created_at / updated_at | TIMESTAMP | | |

`unique(tenant_id, property_id)` — one review per tenant per property.

**Reviews are PROPERTY-grain, not unit-grain** (verified against the migration July 24 2026 — there is **no `unit_id` column**). This table and the relationships list below claimed unit-grain reviews for months; they were wrong, the same filename-vs-body trap as `add_unit_type_floor_deposit_description_to_property_units`. The Overall Ratings feature therefore reports tenant→property (and rolls it up to landlord by `landlord_id`); tenant→unit is deferred until a real `unit_id` is added. `tenant→landlord` and `tenant→property` share this one source. `User::landlordRatingSummary()` aggregates it (hidden excluded).

### reports
| Column | Type | Constraints | Notes |
|---|---|---|---|
| report_id | BIGINT UNSIGNED | PK | `$primaryKey = 'report_id'` |
| reporter_id | FK → users.user_id | NOT NULL | |
| property_id | FK → properties.property_id | NULLABLE | |
| reported_user_id | FK → users.user_id | NULLABLE | |
| report_reason | TEXT | NOT NULL | |
| report_status | ENUM('Pending','Resolved') | DEFAULT 'Pending' | |
| created_at | TIMESTAMP | | |

### complaints
| Column | Type | Constraints | Notes |
|---|---|---|---|
| complaint_id | BIGINT UNSIGNED | PK | `$primaryKey = 'complaint_id'` |
| (schema per implementation) | | | Modules 12.1–12.4 completed |

### notifications
| Column | Type | Constraints | Notes |
|---|---|---|---|
| notification_id | BIGINT UNSIGNED | PK | `$primaryKey = 'notification_id'` |
| user_id | FK → users.user_id | NOT NULL | |
| title | VARCHAR(255) | NOT NULL | |
| message | TEXT | NOT NULL | |
| link | VARCHAR(255) | NULLABLE | Destination URL. Added July 21, 2026 — notifications previously had no target of their own, so anything that wasn't a message notification fell back to the index |
| type | VARCHAR(255) | DEFAULT 'system' | reservation / agreement / payment / verification / listing / review / report / account — drives the dropdown's icon and tint |
| is_read | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | | |

### payments
| Column | Type | Constraints | Notes |
|---|---|---|---|
| payment_id | BIGINT UNSIGNED | PK | `$primaryKey = 'payment_id'` |
| reservation_id | FK → reservations.reservation_id | NOT NULL, cascade | |
| payment_type | ENUM('Initial','Monthly','Deposit','Utility','Other') | NOT NULL | Widened July 24 2026. **`Monthly` went live** with the rent ledger — it and `billing_period` were in the schema from day one and had never been written by any code until then |
| billing_period | DATE | NULLABLE | The month a `Monthly` payment settles. The rent ledger derives its periods and matches payments to a month on this — required for `Monthly`, null otherwise |
| amount | DECIMAL(10,2) | NOT NULL | Serializes as a **string** — `parseFloat()` client-side |
| payment_method | ENUM('GCash','QRPh','Cash','Bank Transfer','Maya','Check','Other') | NOT NULL | Widened July 24 2026 (offline methods) and July 26 2026 (QRPh online). Escrow uses GCash/QRPh via PayMongo; the rest are for landlord-recorded offline payments |
| paymongo_payment_intent_id | VARCHAR | NULLABLE, UNIQUE | |
| paymongo_payment_id | VARCHAR | NULLABLE | |
| paymongo_checkout_session_id | VARCHAR | NULLABLE, UNIQUE | |
| status | ENUM('Pending','Paid','Held','Released','Failed','Refunded') | DEFAULT 'Pending' | `Held` = escrow. **`Paid` went live July 24 2026** — a landlord-recorded offline payment (rent ledger), money already received, never escrowed. It counts as revenue (`AnalyticsController::EARNED_STATUSES = ['Paid','Held','Released']`) and can never be released (`Admin\PaymentController::release` refuses anything not `Held`). **No code writes `Refunded`** — there is no refund action |
| paid_at | TIMESTAMP | NULLABLE | Clock 1 falls back to this when there is no target move-in date |
| released_at | TIMESTAMP | NULLABLE | |
| released_by | BIGINT UNSIGNED | NULLABLE | Admin user id, or null when the platform released it |
| release_reason | ENUM('tenant_confirmed','auto_expiry','admin_manual') | NULLABLE | |
| recorded_by | FK → users.user_id | NULLABLE, nullOnDelete | The landlord who typed this payment in. **Null = platform-settled (PayMongo); non-null = landlord-asserted.** The only field that distinguishes the two — same role `release_reason` plays for releases. Drives the "Recorded by landlord" badge on the admin payments screen. Added July 24 2026 |
| reference_no | VARCHAR | NULLABLE | OR number / GCash reference for a recorded payment |
| payment_notes | TEXT | NULLABLE | Free-text note on a recorded payment |
| payout_status | ENUM('Pending Payout','Paid Out') | NULLABLE | Added July 26 2026. Null = not payout-eligible (most rows — `Pending`/`Held`/`Failed`, or landlord-recorded offline payments, which need no platform payout). Set to `Pending Payout` the instant a payment becomes money owed to a landlord (initial payment `Released`, or `Paid` monthly rent), by every code path that writes those statuses. See `docs/specs/2026-07-26-landlord-payout-design.md` |
| paid_out_at | TIMESTAMP | NULLABLE | When an admin recorded the manual GCash transfer as sent |
| paid_out_by | FK → users.user_id | NULLABLE, nullOnDelete | Admin who recorded the payout |
| payout_reference | VARCHAR | NULLABLE | The GCash transaction reference the admin typed in after sending |

Index `payments_reservation_period_index` on `(reservation_id, billing_period)` — the rent ledger's per-period lookup. Index on `payout_status` for the admin payouts queue.

**The rent ledger has no schedule table.** A billing period is derived: a month between move-in and move-out, settled by a `Monthly` payment whose `billing_period` falls in it (`App\Services\RentLedger`). Editing rent, due day or move-out date can't leave stale rows because there are none — `payments` is the only stored fact. Serves walk-in and platform tenancies identically; the escrow only ever covered the initial payment.

`released_by` is null for **both** a tenant confirmation and a timer expiry, so it cannot distinguish them on its own — `release_reason` is what carries that, and it is the field a disputed payout is argued from months later. Its three values are written by exactly three paths: `Reservation::confirmMoveIn()`, `ProcessMoveInDeadlines::releaseExpiredConfirmations()`, and `Admin\PaymentController::release()`.

### tenant_ratings
| Column | Type | Constraints | Notes |
|---|---|---|---|
| rating_id | BIGINT UNSIGNED | PK | `$primaryKey = 'rating_id'` |
| reservation_id | FK → reservations.reservation_id | UNIQUE, NOT NULL, cascade | One rating per reservation |
| landlord_id | FK → users.user_id | NOT NULL | The rater |
| tenant_id | FK → users.user_id | NOT NULL | The rated tenant |
| rating | TINYINT UNSIGNED | NOT NULL | 1–5 |
| comment | TEXT | NULLABLE | |
| created_at / updated_at | TIMESTAMP | | |

Landlord → tenant. **Collected since mid-2026 but never displayed until the Overall Ratings feature (July 24 2026)** — a tenant's received rating now surfaces on their profile, in the admin user detail, and in the admin `/admin/ratings` overview. `User::tenantRatingSummary()` aggregates it; `User::tenantRatingsReceived()` / `tenantRatingsGiven()` are the relations. Fixtures: `php artisan ratings:scenarios` (dev-only, `--clean`) seeds this table plus `reviews`, since the seeder ships neither.

### rent_reminders
| Column | Type | Constraints | Notes |
|---|---|---|---|
| reminder_id | BIGINT UNSIGNED | PK | `$primaryKey = 'reminder_id'` |
| reservation_id | FK → reservations.reservation_id | NOT NULL, cascade | |
| billing_period | DATE | NOT NULL | The month the reminder is about |
| milestone | VARCHAR(20) | NOT NULL | `due_soon` / `due_today` / `overdue_w1..wN` |
| created_at | TIMESTAMP | | No `updated_at` — written once, never changed (`RentReminder::UPDATED_AT = null`) |

`unique(reservation_id, billing_period, milestone)` (`rent_reminders_unique_milestone`). Idempotency guard for `reservations:process-rent-reminders` (July 24 2026): each fired milestone records one row, so a missed or repeated nightly run can't gap or double-notify. Same role the escrow loop's `move_in_last_reminder_on` plays, generalised to many periods per tenancy. `RentReminder::firstOrCreate(...)->wasRecentlyCreated` is the send gate.

### occupancy_snapshots
| Column | Type | Constraints | Notes |
|---|---|---|---|
| snapshot_id | BIGINT UNSIGNED | PK | `$primaryKey = 'snapshot_id'` |
| landlord_id | FK → users.user_id | NOT NULL | |
| snapshot_date | DATE | NOT NULL | `unique(landlord_id, snapshot_date)` |
| total_units / available_units / reserved_units / occupied_units / maintenance_units | INT | DEFAULT 0 | |
| occupancy_rate | DECIMAL(5,2) | DEFAULT 0 | From `OccupancyRateCalculator` |
| created_at / updated_at | TIMESTAMP | | |

Written daily by the `occupancy:snapshot` command (scheduled 23:55). `updateOrCreate` on (landlord_id, date) so re-running is idempotent.

**Write-only since July 26 2026, deliberately.** Its only reader was the occupancy trend chart, which was removed from `landlord/occupancy` (see ARCHITECTURE.md's decision log). The command still runs because occupancy history cannot be reconstructed after the fact — `property_units` stores only each unit's *current* status — so stopping it would permanently foreclose bringing a trend back. **Do not prune this table or its command as dead code.**

Known inconsistency, unresolved: `SnapshotOccupancy` counts *all* units, while the `occupancy_rate` it stores comes from `OccupancyRateCalculator`, which counts only `verification_status = 'Approved'` ones. So `occupancy_rate` will not equal `occupied_units / total_units` for any landlord with unapproved units. Settle this before anything reads the table again.

### occupancy_activities
| Column | Type | Constraints | Notes |
|---|---|---|---|
| activity_id | BIGINT UNSIGNED | PK | `$primaryKey = 'activity_id'` |
| landlord_id | FK → users.user_id | NOT NULL | |
| property_id | FK → properties.property_id | NOT NULL | |
| unit_id | FK → property_units.unit_id | NOT NULL | onDelete cascade |
| actor_id | FK → users.user_id | NULLABLE | Who triggered the change (null for system/CLI) |
| tenant_id | FK → users.user_id | NULLABLE | Tenant involved, if any |
| from_status | VARCHAR(20) | NULLABLE | |
| to_status | VARCHAR(20) | NOT NULL | |
| created_at / updated_at | TIMESTAMP | | `index(landlord_id, created_at)` |

Written by `PropertyUnitObserver` whenever a unit's `availability_status` changes (any path); feeds the Recent Activities feed.

### audit_logs
| Column | Type | Constraints | Notes |
|---|---|---|---|
| log_id | BIGINT UNSIGNED | PK | `$primaryKey = 'log_id'` |
| actor_id | FK → users.user_id | NULLABLE | **`onDelete('set null')` — the one user FK in this schema that does NOT cascade** |
| actor_name | VARCHAR(255) | NOT NULL | Snapshot, so the row survives the account |
| actor_email | VARCHAR(255) | NOT NULL | Snapshot |
| action | VARCHAR(60) | NOT NULL | `'payment.release'` — see `AuditLog::ACTION_LABELS` |
| auditable_type | VARCHAR(255) | NULLABLE | Laravel morph; null when the target was deleted |
| auditable_id | BIGINT UNSIGNED | NULLABLE | |
| summary | VARCHAR(255) | NOT NULL | Human sentence, rendered verbatim |
| reason | TEXT | NULLABLE | Rejection reason / admin note |
| metadata | JSON | NULLABLE | Before/after values, amounts; cast to `array` |
| ip_address | VARCHAR(45) | NULLABLE | 45 chars fits IPv6 |
| created_at | TIMESTAMP | NULLABLE | **No `updated_at`** — `const UPDATED_AT = null` |

Indexes: `created_at`, `(action, created_at)`, `(auditable_type, auditable_id)`.

**Append-only.** Rows are written by `AuditLog::record()` called from inside the acting controller's
existing `DB::transaction()` + `lockForUpdate()` block, so a rolled-back action leaves no phantom row
and a 409 on the idempotency guard doesn't log twice (verified July 26 2026). Nothing updates or
deletes an audit row — there is no route, controller method, or UI affordance for it, deliberately.

**Why `set null` and not `cascade`:** every other FK to `users.user_id` cascades (see §Hard deletes in
RULES.md). An audit log must not — deleting a user would erase exactly the history proving what they
did. `actor_name`/`actor_email` are denormalized at write time so the row stays readable once the FK
is nulled; the index view shows an "Account deleted" note when `actor_id` is null.

Instrumented actions (15) live in `AuditLog::ACTION_LABELS`; the destructive subset that renders with
a red pill is `AuditLog::DESTRUCTIVE_ACTIONS`. **When adding a new consequential admin action, add its
key to `ACTION_LABELS` and call `AuditLog::record()` inside that action's transaction** — the filter
dropdown reads from the same constant, so an unlisted action is invisible to the filter.

### settings
| Column | Type | Constraints | Notes |
|---|---|---|---|
| setting_id | BIGINT UNSIGNED | PK | `$primaryKey = 'setting_id'` |
| key | VARCHAR(100) | UNIQUE, NOT NULL | Must exist in `Setting::DEFINITIONS` or it is refused |
| value | TEXT | NOT NULL | Cast per the key's `type` — `integer`, or `integer_list` stored comma-separated |
| created_at / updated_at | TIMESTAMP | | |

**Overrides only.** A key with no row falls through to `config/rentals.php`, which stays the defaults
*and* the documentation for what each key means. Clearing a field in the admin form **deletes** the row
rather than writing a copy of the default, so a key is never silently pinned to today's default.

`Setting::overrides()` is `Cache::rememberForever`'d under `settings.rentals.overrides` and busted by
`Setting::put()`, so the boot-time merge in `AppServiceProvider` costs a cache read, not a query. Only
the 10 keys in `Setting::DEFINITIONS` are editable; each declares its type, validation rule, label,
help text and group, and both the form and `UpdateSettingsRequest` derive from that one map.

Every change writes a `settings.update` audit row with a `label => 'before → after'` metadata entry per
changed key, inside the same transaction as the writes.

## 3. Relationships
- users → user_roles (1:many — a user can have multiple roles)
- users → landlord_verifications (1:many — resubmission possible after rejection)
- users → rental_businesses (1:many — one landlord, multiple businesses)
- rental_businesses → properties (1:many)
- users → properties (1:many — via landlord_id)
- properties → property_units (1:many — units are the atomic rentable thing)
- properties → property_documents (1:many — verification documents, admin-only)
- properties → property_media (1:many)
- property_units → unit_media (1:many)
- properties ↔ amenities (many:many via property_amenities)
- property_units ↔ amenities (many:many via unit_amenities)
- property_units → reservations (1:many — unit-grain)
- users → reservations (1:many — via tenant_id)
- users + users + properties → conversations (tenant + landlord + property scoped)
- conversations → messages (1:many)
- users → favorites (1:many)
- properties → reviews (1:many — **property-grain**, tenant→property; no unit_id)
- users → reviews (1:many — via landlord_id, for the landlord roll-up)
- reservations → tenant_ratings (1:1 — landlord→tenant, unique per reservation)
- users → reports (1:many — as reporter)
- users → notifications (1:many)

## 4. RLS Policies
Not applicable — MySQL, no row-level security. Access control via Laravel Middleware + Policies.

## 5. Migrations Log
| Migration | Change | Reason | Date |
|---|---|---|---|
| create_users_table | Initial users schema — also creates the stock `password_reset_tokens` and `sessions` tables (Laravel default; `password_reset_tokens` backs the forgot-password flow) | Core auth | Early 2026 |
| create_user_roles_table | Role system | Multi-role support | Early 2026 |
| create_landlord_verifications_table | Verification pipeline + admin_notes | Identity verification module | Mid 2026 |
| add_liveness_passed_to_landlord_verifications_table | `liveness_passed` flag | Upload path removed — flags manual-capture fallback for admin review | July 2026 |
| add_handover_scheduling_to_reservations | 4 `handover_*` columns | Clock 1 anchored to a slot both parties agree on, not the tenant's frozen inquiry-time guess | July 2026 |
| create_properties_table | Property listings | Core listing module | Mid 2026 |
| create_property_media_table | Media storage | Cloudinary integration | Mid 2026 |
| create_amenities_table | Amenity master list | Property features | Mid 2026 |
| create_property_amenities_table | Pivot table | Many-to-many | Mid 2026 |
| create_reservations_table | Reservation state machine | Booking module | Mid 2026 |
| create_conversations_table | Chat conversations | Real-time messaging | Mid 2026 |
| create_messages_table | Chat messages | Real-time messaging | Mid 2026 |
| create_favorites_table | Tenant favorites | Browse enhancement | Mid 2026 |
| create_reviews_table | Tenant reviews | Trust/reputation | Mid 2026 |
| create_reports_table | User/property reports | Moderation | Mid 2026 |
| create_notifications_table | In-app notifications | User alerts | Mid 2026 |
| create_property_units_table | Multi-unit support | PM requirement for bedspace/room granularity | Mid 2026 |
| create_unit_media_table | Unit-level media | Unit photos separate from property | Mid 2026 |
| create_unit_amenities_table | Unit-level amenities | Joseph's table | Mid 2026 |
| create_rental_businesses_table | Business entity layer | Landlord business management | Mid 2026 |
| create_complaints_table | Complaints module | Modules 12.1–12.4 | Mid 2026 |
| create_payments_table | PayMongo integration | Payment processing | Mid 2026 |
| create_tenant_ratings_table | Landlord rates tenants | Tenant accountability | Mid 2026 |
| add_vacated_at_to_property_units_table | Occupancy tracking | Track when a unit was vacated | July 2026 |
| add_unit_type_floor_deposit_description_to_property_units | **Misnamed — adds none of those columns.** Body is one `ALTER TABLE property_units MODIFY COLUMN availability_status` adding the `Maintenance` member | Filename describes an intent that was never written; see the note under `property_units` | July 2026 |
| add_caption_to_unit_media_table | Photo captions | Optional per-photo caption shown to tenants | July 2026 |
| add_unit_type_floor_security_deposit_to_property_units | Actually adds the three columns the July 18 filename promised | Unit creation was throwing `SQLSTATE[42S22]`; sizes match the validation the controllers already enforced | July 27 2026 |
| add_expo_push_token_to_users_table | Device push registration | Mobile client push notifications | July 27 2026 |
| make_rental_businesses_columns_nullable | `business_name`/`contact_number`/`business_address` NOT NULL → nullable | Matches validation that was already `nullable`; fixes an `SQLSTATE[HY000] 1364` crash on a landlord's first profile save with any field left blank | July 27 2026 |
| create_occupancy_snapshots_table | Daily occupancy history | Fed the occupancy trend chart; write-only since the chart's removal July 26 2026 — kept because the history can't be rebuilt later | July 2026 |
| create_occupancy_activities_table | Unit status-change log | Feeds Recent Activities feed | July 2026 |
| add_link_to_notifications_table | Per-notification destination URL | Notifications had no target except a conversation; every non-message type dead-ended at the index | July 2026 |
| add_walk_in_fields_to_users_table | `is_walk_in`, `created_by_landlord_id`; `email` made nullable (raw `ALTER`) | Walk-in tenants entered by landlords; many have only a phone | July 24 2026 |
| add_rent_terms_to_reservations_table | `agreed_monthly_rent`, `rent_due_day` | Rent ledger inputs; both nullable with fallbacks | July 24 2026 |
| add_manual_recording_to_payments_table | Widened `payment_method` + `payment_type` enums (raw `ALTER`); added `recorded_by`, `reference_no`, `payment_notes` + `(reservation_id, billing_period)` index | Landlord-recorded offline rent; the escrow only ever covered the initial payment | July 24 2026 |
| create_rent_reminders_table | Idempotency guard for the nightly rent-reminder command | Reminders need a persisted per-milestone guard so a missed/double run can't gap or spam | July 24 2026 |
| add_locality_to_properties_table | `city_municipality` (NOT NULL, backfilled), `barangay` (nullable); repointed the 8 escrow/walk-in fixture rows off their placeholder Butuan City coordinate first | Cebu-only validation needed a structured locality, not just free-text `address` | Aug 2026 |
| add_scope_and_category_to_amenities_table | `scope` ENUM('property','unit','both') DEFAULT 'both', `category` VARCHAR(50) nullable | Property-level and unit-level amenities needed to be distinguishable | Aug 2026 |
| promote_building_amenities_to_properties | Moves every `unit_amenities` row whose amenity is `scope = 'property'` up to `property_amenities` (deduped per property), deletes the unit-side row. Invokes `AmenitySeeder` itself first so `scope` is populated before it reads it. **`down()` is a no-op** — collapsing several units' tags onto one property is lossy, not reversible | Without this, `property_amenities` would launch empty and ~half of `unit_amenities`'s 130 rows (the building-level ones landlords had already entered) would go from attached-but-invisible to silently dropped on the next unit edit | Aug 2026 |
| add_publication_status_to_properties_table | `publication_status` ENUM('Draft','Published','Unpublished','Suspended') DEFAULT 'Published'. Every existing row backfills to `Published` — publication was never a concept before this column | Split "is this legitimate" from "should it be live right now", closing a real hole: the admin report flow's "delist property" action reused `verification_status = 'Rejected'`, which the next landlord edit + admin re-approval cycle silently undid | Aug 2026 |
| create_property_documents_table | Proof of ownership / tax declaration / permits per property, private-disk file storage, admin verify/reject/request workflow | Admins were approving listings on the landlord's word alone — nothing proved the right to rent out *this* property. Second upload path (after `landlord_verifications`) off the public Cloudinary flow | Aug 2026 |

### Seeders
- `AmenitySeeder` — 33 common amenities (idempotent via `updateOrCreate` on unique `amenity_name`, so re-seeding never shifts an `amenity_id`); runs before `PropertySeeder` in `DatabaseSeeder`. Also assigns `scope`/`category` per amenity (Aug 2026) — see the `amenities` table notes above.
- `Amenity` model exposes a `name` accessor aliasing `amenity_name` (views use `$amenity->name`).
