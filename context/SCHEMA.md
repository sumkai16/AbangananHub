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
| profile_picture | VARCHAR(255) | NULLABLE | Cloudinary URL |
| account_status | ENUM('active','suspended','inactive') | DEFAULT 'active' | Normalized to lowercase July 21, 2026 — was `ENUM('Active','Suspended')` with no `inactive` member, which silently didn't match the lowercase values the Users admin UI had been writing/reading (see RULES.md → Concurrency & State Transitions and migration `2026_07_21_000001_normalize_users_account_status`) |
| email | VARCHAR(255) | UNIQUE, **NULLABLE** | Was NOT NULL until July 24 2026; made nullable for walk-in tenants who often have only a phone number. MySQL allows many NULLs under a UNIQUE index, so real addresses stay unique. Anything rendering an avatar/name must use `?: '—'`, not assume a value |
| is_walk_in | BOOLEAN | DEFAULT false | A landlord-entered tenant, not a self-registered account. Cast to bool. Drives the **Walk-in** pill everywhere the user surfaces (landlord tenants, admin users, occupancy, exports) — the identity is landlord-asserted, never platform-verified |
| created_by_landlord_id | FK → users.user_id | NULLABLE, nullOnDelete | The landlord who added this walk-in. Scopes `User::walkInTenants()` |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

Walk-in tenants (added July 24 2026) are real `users` rows with a random unknowable password and `account_status='inactive'`, so the row can never be logged into — that is why they structurally cannot leave reviews or ratings. Keeping them in `users` (rather than a separate table) is what lets `reservations.tenant_id` stay NOT NULL so the ~20 views reading `$reservation->tenant->…` need no null-handling. Written by `Landlord\WalkInTenantController`.

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
| Column | Type | Constraints | Notes |
|---|---|---|---|
| business_id | BIGINT UNSIGNED | PK | `$primaryKey = 'business_id'` |
| landlord_id | FK → users.user_id | NOT NULL | |
| business_name | VARCHAR(255) | NOT NULL | |
| business_logo | VARCHAR(255) | NULLABLE | Cloudinary URL |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### properties
| Column | Type | Constraints | Notes |
|---|---|---|---|
| property_id | BIGINT UNSIGNED | PK | `$primaryKey = 'property_id'` |
| landlord_id | FK → users.user_id | NOT NULL | |
| business_id | FK → rental_businesses.business_id | NULLABLE | |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | NULLABLE | |
| property_type | ENUM('Bedspace','Room','Apartment','House') | NOT NULL | |
| address | VARCHAR(255) | NOT NULL | Text-searched for browse |
| latitude | DECIMAL(10,8) | NULLABLE | `parseFloat()` client-side |
| longitude | DECIMAL(11,8) | NULLABLE | |
| rental_fee | DECIMAL(10,2) | NOT NULL | Eloquent serializes as string |
| occupancy_limit | INT | NULLABLE | |
| availability_status | ENUM('Available','Reserved','Occupied') | DEFAULT 'Available' | |
| verification_status | ENUM('Pending','Approved','Rejected') | DEFAULT 'Pending' | Admin approval |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

### property_units
| Column | Type | Constraints | Notes |
|---|---|---|---|
| unit_id | BIGINT UNSIGNED | PK | `$primaryKey = 'unit_id'` |
| property_id | FK → properties.property_id | NOT NULL | |
| unit_label | VARCHAR(100) | NOT NULL | e.g. "Room A", "Bed 3" — column is `unit_label`, not `unit_name` |
| ~~unit_type~~ | — | **DOES NOT EXIST** | Verified absent July 24 2026 — see the note below the table |
| ~~floor~~ | — | **DOES NOT EXIST** | Verified absent July 24 2026 |
| description | TEXT | NULLABLE | |
| rental_fee | DECIMAL(10,2) | NOT NULL | |
| ~~security_deposit~~ | — | **DOES NOT EXIST** | Verified absent July 24 2026 |
| occupancy_limit | INT | NULLABLE | |
| availability_status | ENUM('Available','Reserved','Occupied','Maintenance') | DEFAULT 'Available' | Maintenance added for unit form |
| vacated_at | TIMESTAMP | NULLABLE | Occupancy tracking |
| verification_status | ENUM('Pending','Approved','Rejected') | DEFAULT 'Pending' | Admin approval; reset to Pending on material edit |
| rejection_reason | TEXT | NULLABLE | Admin rejection note |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

**`unit_type`, `floor` and `security_deposit` are not columns — this table documented them for months and they were never created** (resolved July 24 2026). The cause is a **misnamed migration**: `2026_07_18_022220_add_unit_type_floor_deposit_description_to_property_units` promises all four in its filename, but its body contains a single `ALTER TABLE ... MODIFY COLUMN availability_status` adding the `Maintenance` enum member and nothing else. It is recorded in `migrations` as run (batch 1), so `migrate` reports nothing outstanding and `migrate:fresh` reproduces the gap exactly.

Consequences, all live: `PropertyUnit::$fillable` declares all three, `Landlord\PropertyUnitController::store()/update()` validate and write them, so **creating a unit throws `SQLSTATE[42S22] Unknown column 'unit_type'`** (see ARCHITECTURE.md). Reads fail silently instead — a missing attribute returns null — so `agreements/show` has a "Security deposit" row that can never render, `OccupancyController` reports null deposits, the units CSV exports blanks, and `properties/show`'s unit payload sends `deposit: null` to the cost breakdown. Existing units came from seeders, which is why nothing looked broken.

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

### property_amenities (pivot)
| Column | Type | Constraints | Notes |
|---|---|---|---|
| property_id | FK → properties.property_id | | Composite key |
| amenity_id | FK → amenities.amenity_id | | `belongsToMany` needs all 4 args |

**Empty, and nothing writes to it** (confirmed July 24 2026 — 0 rows vs 130 in `unit_amenities`). Amenities were a property-level concept before the multi-unit model; no landlord form has ever attached one to a property. `properties/show` derives its amenity list from `units.amenities` instead (DESIGN.md §6e), so the `Property::amenities()` relation is now unused by every view — don't eager-load it, and don't read it expecting data.

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
| payment_method | ENUM('GCash','Cash','Bank Transfer','Maya','Check','Other') | NOT NULL | Widened July 24 2026 — was `ENUM('GCash')` only. Escrow still uses GCash; the rest are for landlord-recorded offline payments |
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

Index `payments_reservation_period_index` on `(reservation_id, billing_period)` — the rent ledger's per-period lookup.

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

Written daily by the `occupancy:snapshot` command (scheduled 23:55); feeds the occupancy trend chart. `updateOrCreate` on (landlord_id, date) so re-running is idempotent.

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

## 3. Relationships
- users → user_roles (1:many — a user can have multiple roles)
- users → landlord_verifications (1:many — resubmission possible after rejection)
- users → rental_businesses (1:many — one landlord, multiple businesses)
- rental_businesses → properties (1:many)
- users → properties (1:many — via landlord_id)
- properties → property_units (1:many — units are the atomic rentable thing)
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
| create_occupancy_snapshots_table | Daily occupancy history | Feeds occupancy trend chart | July 2026 |
| create_occupancy_activities_table | Unit status-change log | Feeds Recent Activities feed | July 2026 |
| add_link_to_notifications_table | Per-notification destination URL | Notifications had no target except a conversation; every non-message type dead-ended at the index | July 2026 |
| add_walk_in_fields_to_users_table | `is_walk_in`, `created_by_landlord_id`; `email` made nullable (raw `ALTER`) | Walk-in tenants entered by landlords; many have only a phone | July 24 2026 |
| add_rent_terms_to_reservations_table | `agreed_monthly_rent`, `rent_due_day` | Rent ledger inputs; both nullable with fallbacks | July 24 2026 |
| add_manual_recording_to_payments_table | Widened `payment_method` + `payment_type` enums (raw `ALTER`); added `recorded_by`, `reference_no`, `payment_notes` + `(reservation_id, billing_period)` index | Landlord-recorded offline rent; the escrow only ever covered the initial payment | July 24 2026 |
| create_rent_reminders_table | Idempotency guard for the nightly rent-reminder command | Reminders need a persisted per-milestone guard so a missed/double run can't gap or spam | July 24 2026 |

### Seeders
- `AmenitySeeder` — 33 common amenities (idempotent via `firstOrCreate` on unique `amenity_name`); runs before `PropertySeeder` in `DatabaseSeeder`. The amenities table is otherwise empty.
- `Amenity` model exposes a `name` accessor aliasing `amenity_name` (views use `$amenity->name`).
