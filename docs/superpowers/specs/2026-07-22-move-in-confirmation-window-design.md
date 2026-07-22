# Move-In Confirmation Window (Escrow Deadlines)

**Date:** 2026-07-22
**Status:** Approved design, pending implementation plan

## Problem

The escrow skeleton already exists. A tenant pays their deposit, the payment lands
on `Held`, and `Reservation::confirmMoveIn()` releases it to the landlord while
marking the unit `Occupied` — all in one transaction.

Nothing ever forces that confirmation to happen.

A `Held` payment can sit indefinitely. The landlord has turned over the keys and
has no assurance the money will ever arrive; the tenant has paid and faces no
deadline. The only resolution path today is an admin noticing and releasing the
payment by hand from the admin Payments screen.

This is not solved by adding a notice. A notice is text. The deadline is the
assurance.

## Design

Two deadlines that **expire in opposite directions**, so neither party profits by
doing nothing.

```
Tenant pays → payment = Held
  ⏱ Clock 1 (landlord): turn over keys and mark it
      ├─ landlord marks turnover → ⏱ Clock 2 (tenant): 7 days to confirm
      │     ├─ tenant confirms   → Released to landlord      (tenant_confirmed)
      │     ├─ tenant disputes   → frozen, admin queue
      │     └─ silence           → Released to landlord      (auto_expiry)
      ├─ tenant confirms first (landlord never marked) → Released (tenant_confirmed)
      └─ neither asserts         → auto-refund to tenant     (Refunded)
```

A landlord who stalls loses the deposit back to the tenant. A tenant who stalls
after moving in pays the landlord. Silence is never the winning move.

### Clock 1 — the landlord's deadline

```
deadline = max(target_move_in_date, paid_at) + config('rentals.turnover_grace_days')   // 7
no target_move_in_date:
deadline = paid_at + config('rentals.turnover_grace_days_no_date')                     // 14
```

Derived from the move-in date the two parties already negotiated rather than a
flat timer. A deposit paid for a move-in next week and one paid for a move-in two
months out cannot share a countdown. The landlord gets a grace period past *their
own committed date*, which makes the deadline defensible in a dispute — we are
enforcing their commitment, not an arbitrary platform rule.

`target_move_in_date` is nullable and validated as `nullable`
(`Api/Tenant/ReservationController.php:50`), hence the flat fallback.

The `max()` guards a deposit paid after an optimistic target date has already
slipped, which would otherwise start the clock already-expired and fire a refund
on the first nightly run.

**On expiry:** payment → `Refunded`, reservation → `Cancelled`, unit released.

### Clock 2 — the tenant's deadline

```
deadline = keys_turned_over_at + config('rentals.move_in_confirmation_days')   // 7
```

Static, unlike Clock 1. Once the tenant has keys, seven days to tap a button is
seven days — nothing about the situation changes it.

**On expiry:** payment → `Released` with `release_reason = auto_expiry`,
reservation → `Occupied`, unit → `Occupied`.

`tenant_confirmed_move_in_at` **stays null** on auto-release. It means what it
says. Conflating "confirmed" with "timed out" would poison occupancy reporting.

### Turnover

Turnover is one moment: the landlord hands over the keys and the tenant occupies
the place. Not an inspection, checklist, or ceremony — a single timestamp.

Because the landlord's handover and the tenant's move-in are the same real-world
event witnessed from two sides, **either party can assert it**:

| Asserter | Effect | Why |
|---|---|---|
| Landlord | Starts Clock 2. Moves no money. | Self-interested claim — weak evidence |
| Tenant | Releases the payment immediately. | Claim against their own financial interest — strongest evidence |
| Neither, by Clock 1 expiry | Refund the tenant. | If nobody says the tenant lives there, they don't |

This closes the forgotten-button failure. A landlord who hands over keys but never
marks it is still paid, because the tenant's own confirmation carries the payout.
The automatic refund can only fire when *neither* side claims a handover occurred.

### Dispute

The tenant can assert "I haven't received the keys." This **freezes** the
deadline — it does not pause and resume it. No arithmetic on remaining time; that
is a bug farm for a rare case that gets human review anyway.

Admin resolves by releasing, refunding, or restarting the clock with a fresh
turnover. Disputed payments stay `Held`; the admin queue is simply reservations
with a non-null `move_in_disputed_at`. No new payment status, no enum change, and
the existing admin Payments screen keeps working.

### Reminders and countdown

Reminders on Clock 2 at **day 3, day 6, and the morning of expiry**, via the
existing notification pipeline and `Reservation::postSystemMessage()` into the
conversation thread.

A **live countdown** stays visible on the tenant's reservation view for the whole
window — "3 days left to confirm move-in" — not buried in a dismissed
notification.

These are not decoration. If we auto-release someone's deposit, we must be able to
show they were warned three times. The reminders are most of what makes the
auto-release defensible.

Two prompts the tenant sees, which must not read as the same thing:

- After paying: *"Payment secured. The landlord will contact you to turn over the keys."* — no action, no countdown
- After turnover: *"Confirm your move-in — 7 days left. This releases your deposit to the landlord."* — action plus countdown

## Schema

**`reservations`** — four new columns:

| Column | Type | Purpose |
|---|---|---|
| `keys_turned_over_at` | nullable timestamp | Turnover assertion |
| `move_in_deadline_at` | nullable timestamp | Active deadline; null = no clock running |
| `move_in_disputed_at` | nullable timestamp | Non-null = frozen, in admin queue |
| `move_in_dispute_reason` | nullable text | Tenant's stated reason |

**`payments`** — one new column:

| Column | Type | Purpose |
|---|---|---|
| `release_reason` | nullable enum | `tenant_confirmed` / `auto_expiry` / `admin_manual` |

`release_reason` is required, not optional. `Reservation.php` currently documents
`released_by = null` as meaning "released by the platform on tenant confirmation,"
with an admin id meaning manual release. Auto-release on expiry is also a
platform release with a null actor, so it would silently collide with the
tenant-confirmed case — losing exactly the distinction needed when a tenant
disputes a payout months later. `released_by` keeps meaning "which human, if any."

No payment status enum change.

## Config

New `config/rentals.php`, so all three numbers are tunable in one place without a
code change:

```php
return [
    'move_in_confirmation_days'    => 7,   // Clock 2
    'turnover_grace_days'          => 7,   // Clock 1, past target_move_in_date
    'turnover_grace_days_no_date'  => 14,  // Clock 1, no target date
];
```

## Scheduled processing

A nightly command — `reservations:process-move-in-deadlines` — registered in
`routes/console.php` alongside the existing `occupancy:snapshot`. Daily
granularity is sufficient for windows measured in days.

Responsibilities:

1. Backfill `move_in_deadline_at` for reservations whose Clock 1 has started
2. Send Clock 2 reminders at day 3, day 6, and expiry morning
3. Process Clock 1 expiries → refund
4. Process Clock 2 expiries → release

Money-moving rows must be locked with `lockForUpdate()`, following the pattern
already established in `Reservation::confirmMoveIn()`. Broadcasts fire after the
transaction commits, never inside it — announcing a payout a rollback could still
undo.

## Explicitly out of scope

**Per-landlord or negotiable deadline lengths.** Clock 1's length is adversarial
in a way a move-in date is not: the landlord wants it long (more room to stall
while the tenant's money is locked), the tenant wants it short (faster exit if the
landlord goes quiet). Putting it on the negotiation table means arguing about a
deadline before anyone has moved in, and the landlord usually has the leverage to
win that argument — quietly gutting the protection. The config file gives us
tuning without a settings UI or a negotiation surface. Straightforward to add
later if real usage demands it.

**Turnover inspections, checklists, photos, or inventory.** Turnover is one
timestamp. If the product later needs a formal move-in inspection, that is its own
feature.

## Open risks

- **Clock 1 refunds are the highest-consequence automatic action here.** They
  cancel a signed agreement without human review. The two-sided turnover assertion
  narrows the trigger to "nobody claims a handover happened," but this deserves
  monitoring after launch and possibly an admin notification before the refund
  fires rather than after.
- **A landlord can start Clock 2 unilaterally** by marking a turnover that did not
  happen. The dispute button is the mitigation, which makes the countdown's
  visibility and the reminder cadence load-bearing rather than cosmetic.
