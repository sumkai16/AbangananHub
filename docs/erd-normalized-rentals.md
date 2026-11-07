# Normalized ERD — reservation / rental split

**Status: proposed design, not the current database.** The live app keeps everything in one
`reservations` table (see `context/SCHEMA.md` §`reservations`). This diagram splits it by
lifecycle stage. Paste the block into https://mermaid.live to render it.

```mermaid
erDiagram
    users ||--o{ user_roles : has
    users ||--o{ properties : "owns (landlord_id)"
    users ||--o{ reservations : "requests (tenant_id)"
    properties ||--o{ property_units : contains
    property_units ||--o{ reservations : "is requested in"

    reservations ||--o| rentals : "becomes"
    rentals ||--|| lease_agreements : "documented by"
    rentals ||--|| move_in_handovers : "starts with"
    rentals ||--o{ payments : "is paid by"
    rentals ||--o{ deposit_charges : "has"
    rentals ||--o{ rent_reminders : "triggers"
    rentals ||--o| tenant_ratings : "is rated by"
    reservations ||--o{ viewings : "schedules"

    reservations {
        bigint reservation_id PK
        bigint tenant_id FK
        bigint unit_id FK "property_id dropped, derivable via unit"
        date reservation_date
        varchar rental_status "Inquiry..Rejected/Cancelled"
        text rejection_reason
        text remarks
    }
    rentals {
        bigint rental_id PK
        bigint reservation_id FK "UNIQUE"
        decimal agreed_monthly_rent
        tinyint rent_due_day
        date target_move_in_date
        date target_move_out_date
        int occupants_count
        text agreement_terms_notes
        varchar status "Pending Agreement..Completed"
    }
    lease_agreements {
        bigint lease_id PK
        bigint rental_id FK "UNIQUE"
        json lease_snapshot
        varchar file_path
        varchar file_name
        timestamp uploaded_at
        timestamp agreed_at
        varchar agreed_ip
        timestamp landlord_tc_accepted_at
        timestamp tenant_tc_accepted_at
    }
    move_in_handovers {
        bigint handover_id PK
        bigint rental_id FK "UNIQUE"
        timestamp handover_at
        bigint proposed_by FK
        timestamp proposed_at
        timestamp confirmed_at
        timestamp keys_turned_over_at
        timestamp move_in_deadline_at
        timestamp move_in_disputed_at
        text move_in_dispute_reason
        date move_in_last_reminder_on
        timestamp tenant_confirmed_move_in_at
    }
    payments {
        bigint payment_id PK
        bigint rental_id FK
        varchar billing_period
        decimal amount
    }
    deposit_charges {
        bigint charge_id PK
        bigint rental_id FK
    }
    rent_reminders {
        bigint reminder_id PK
        bigint rental_id FK
    }
    tenant_ratings {
        bigint rating_id PK
        bigint rental_id FK "UNIQUE"
    }
    viewings {
        bigint viewing_id PK
        bigint reservation_id FK
        datetime scheduled_at
    }
```

## What changed from the live schema

| Change | Why |
|---|---|
| New `rentals` | Rent, dates and occupants only exist once a booking is accepted. A rejected reservation never gets a row. |
| New `lease_agreements`, `move_in_handovers` | Each is a stage with its own set of columns that are NULL on most `reservations` rows today. |
| `payments`, `deposit_charges`, `rent_reminders`, `tenant_ratings` point to `rentals` | They describe the tenancy, not the request. |
| `viewings` stays on `reservations` | A viewing happens before any rental exists. |
| Dropped `reservations.property_id` | `unit_id` already determines the property (3NF). |
| Dropped `reservations.duration_of_stay` | Dead column; derivable from the two dates. |

## Not shown

Only the tables touched by the split are drawn in full. `conversations`, `messages`, `reviews`,
`favorites`, `reports`, `notifications`, media and amenity tables are unchanged from
`context/SCHEMA.md` §3.
