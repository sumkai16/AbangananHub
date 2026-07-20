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
| account_status | ENUM('Active','Suspended') | DEFAULT 'Active' | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

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
| unit_type | VARCHAR(50) | NULLABLE | Free text (Bedspace, Room, Apartment, Studio, Dormitory) |
| floor | VARCHAR(50) | NULLABLE | e.g. "1st Floor" |
| description | TEXT | NULLABLE | |
| rental_fee | DECIMAL(10,2) | NOT NULL | |
| security_deposit | DECIMAL(10,2) | NULLABLE | |
| occupancy_limit | INT | NULLABLE | |
| availability_status | ENUM('Available','Reserved','Occupied','Maintenance') | DEFAULT 'Available' | Maintenance added for unit form |
| vacated_at | TIMESTAMP | NULLABLE | Occupancy tracking |
| verification_status | ENUM('Pending','Approved','Rejected') | DEFAULT 'Pending' | Admin approval; reset to Pending on material edit |
| rejection_reason | TEXT | NULLABLE | Admin rejection note |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

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
| reservation_status | ENUM('Pending','Approved','Rejected','Cancelled') | DEFAULT 'Pending' | |
| remarks | TEXT | NULLABLE | |
| created_at | TIMESTAMP | | |
| updated_at | TIMESTAMP | | |

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
| property_id | FK → properties.property_id | NULLABLE | |
| unit_id | FK → property_units.unit_id | NULLABLE | Unit-grain reviews |
| landlord_id | FK → users.user_id | NOT NULL | |
| rating | TINYINT | NOT NULL | 1–5 |
| review_comment | TEXT | NULLABLE | Audit: remove test comment "hahaha suled" before defense |
| created_at | TIMESTAMP | | |

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
| is_read | BOOLEAN | DEFAULT FALSE | |
| created_at | TIMESTAMP | | |

### payments
| Column | Type | Constraints | Notes |
|---|---|---|---|
| (schema per PayMongo implementation) | | | PayMongo sandbox, escrow simulated in app layer |

### tenant_ratings
| Column | Type | Constraints | Notes |
|---|---|---|---|
| (schema per implementation) | | | Landlord rates tenants |

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
- property_units → reviews (1:many — unit-grain)
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
| add_unit_type_floor_deposit_description_to_property_units | Richer unit fields | unit_type, floor, security_deposit | July 2026 |
| add_caption_to_unit_media_table | Photo captions | Optional per-photo caption shown to tenants | July 2026 |
| create_occupancy_snapshots_table | Daily occupancy history | Feeds occupancy trend chart | July 2026 |
| create_occupancy_activities_table | Unit status-change log | Feeds Recent Activities feed | July 2026 |

### Seeders
- `AmenitySeeder` — 33 common amenities (idempotent via `firstOrCreate` on unique `amenity_name`); runs before `PropertySeeder` in `DatabaseSeeder`. The amenities table is otherwise empty.
- `Amenity` model exposes a `name` accessor aliasing `amenity_name` (views use `$amenity->name`).
