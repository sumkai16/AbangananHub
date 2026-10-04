# Security deposit charges (deductions)

## Context

Today `property_units.security_deposit` is collected as a `Deposit`-type payment and shown on the
tenancy pages as a single "Held, refundable at move-out" figure (`RentLedger::summary()['depositCollected']`,
`landlord/tenancies/show.blade.php:213`). There is no way for a landlord to record that some of that
held money is being claimed — e.g. a tenant damaged something, left the unit unclean, or skipped an
unpaid utility bill. The landlord currently has no in-app record of *why* a deposit was partially or
fully withheld, and the tenant has no visibility into it until move-out, if ever.

This adds a **deposit charge** ledger: a landlord can record a charge against a specific tenancy's
held deposit (amount + category + description), the tenant is notified immediately and can see the
running total on their own tenancy page, and a landlord can void a charge they entered by mistake
(mirroring the existing "void a payment" correction flow). Refunding the *remaining* deposit at
move-out is a separate, larger workflow and is explicitly out of scope here — this only adds the
"charge against it" side the user asked for.

Decisions already made with the user:
- **Void/correct is in scope** — same shape as `Payment::VOID_REASONS`/`PaymentController::void()`.
- **Charges are capped** at the deposit currently held — a charge cannot push the running total past
  `depositCollected`.

## Data model

**New migration** `database/migrations/2026_09_28_000002_create_deposit_charges_table.php`:

| Column | Type | Notes |
|---|---|---|
| deposit_charge_id | BIGINT UNSIGNED PK | |
| reservation_id | FK → reservations.reservation_id, cascade | |
| amount | DECIMAL(10,2) NOT NULL | |
| category | ENUM('Damage','Cleaning','Missing Item','Unpaid Utility','Other') NOT NULL | |
| description | TEXT NOT NULL | Always required — the tenant needs to know why they're being charged, regardless of category |
| charged_at | DATE NOT NULL, default today | Editable — landlord may be logging damage found a few days ago |
| charged_by | FK → users.user_id, nullOnDelete | The landlord who recorded it |
| voided_at | TIMESTAMP NULLABLE | |
| voided_by | FK → users.user_id, NULLABLE, nullOnDelete | |
| void_reason | ENUM('wrong_amount','wrong_tenancy','not_applicable','duplicate','other') NULLABLE | |
| void_note | VARCHAR(255) NULLABLE | Required only when void_reason = 'other' (`required_if`, same as `payments.void_note`) |
| created_at / updated_at | TIMESTAMP | |

Index on `(reservation_id, voided_at)`.

**New model** `app/Models/DepositCharge.php` — `$primaryKey = 'deposit_charge_id'`, casts
(`amount` → `decimal:2`, `charged_at` → `date`, `voided_at` → `datetime`), `CATEGORIES` and
`VOID_REASONS` constants (same shape as `Payment::VOID_REASONS`), `reservation()`, `charger()`,
`voider()` relations, `scopeActive()` (`whereNull('voided_at')`), `isVoided()`, `voidReasonLabel()`.

**`Reservation` model**: add `depositCharges()` → `hasMany(DepositCharge::class, 'reservation_id', 'reservation_id')->orderByDesc('charged_at')`.

**`RentLedger::summary()`** (`app/Services/RentLedger.php`, alongside the existing `depositCollected`
block at line ~206): add
- `depositCharged` — sum of `$this->reservation->depositCharges->where(fn ($c) => ! $c->isVoided())`
- `depositRemaining` — `max(0, $depositCollected - $depositCharged)`

This keeps all deposit arithmetic in the one place the codebase already treats as the source of
truth for this tenancy's money, rather than duplicating the sum in two view files.

## Authorization

`app/Policies/ReservationPolicy.php` — add, next to `voidPayment`:
- `recordDepositCharge(User $user, Reservation $reservation)` → same as `viewTenancy` (owner check,
  not restricted to Occupied — damage is often found around move-out).
- `voidDepositCharge(User $user, Reservation $reservation)` → same as `voidPayment` (owner check).

## Controller

**New** `app/Http/Controllers/Landlord/DepositChargeController.php`, mirroring
`Landlord\PaymentController::store()`/`void()`:

```
store(Request $request, Reservation $reservation)
```
- `Gate::authorize('recordDepositCharge', $reservation)`
- Validate: `amount` (required, numeric, min:1), `category` (required, in `DepositCharge::CATEGORIES`
  keys), `description` (required, string, max:500), `charged_at` (nullable, date, before_or_equal:today)
- `DB::transaction()`: lock the reservation (`Reservation::whereKey(...)->lockForUpdate()->firstOrFail()`),
  recompute `depositRemaining` via `RentLedger::for($locked)->summary()` under the lock, reject
  (`back()->withErrors(...)`) if `amount > depositRemaining`
- Create the `DepositCharge` row, `AuditLog::record('deposit_charge.create', ...)`, then notify the
  tenant (see below)

```
void(Request $request, DepositCharge $depositCharge)
```
- Load `reservation.property`, `Gate::authorize('voidDepositCharge', $depositCharge->reservation)`
- Validate `void_reason` (required, in keys), `void_note` (`required_if:void_reason,other`)
- Same lock-and-update shape as `PaymentController::void()`, `AuditLog::record('deposit_charge.void', ...)`,
  notify the tenant that the charge was reversed

**Notification** — reuse `Notification::notify()` (`app/Models/Notification.php`), type `'payment'`
(no new icon/tint mapping needed), guarded the same way `TenancyController::notifyTenancyEnded()`
guards against walk-ins (`! $tenant->is_walk_in`):
- On create: *"₱X was charged to your security deposit for {unit label}: {description}"*, linking to
  `tenant.tenancy.show`.
- On void: *"A ₱X security deposit charge was reversed."*

## Audit log

`app/Models/AuditLog.php` — add to `ACTION_LABELS`:
```
'deposit_charge.create' => 'Deposit charge recorded',
'deposit_charge.void'   => 'Deposit charge voided',
```
Add both to `DESTRUCTIVE_ACTIONS` — recording one changes what the tenant is owed back, same class
as `payment.release`/`payment.void` per that constant's own doc comment.

## Routes (`routes/web.php`, in the existing landlord `tenancies` group next to the payment routes)

```
Route::post('/tenancies/{reservation}/deposit-charges', [DepositChargeController::class, 'store'])->name('depositCharges.store');
Route::post('/deposit-charges/{depositCharge}/void', [DepositChargeController::class, 'void'])->name('depositCharges.void');
```
`{depositCharge}` binds on `deposit_charge_id` automatically (default route-key = primary key, same
as `{payment}` today — no `getRouteKeyName()` override needed).

## Views

**Landlord** — `resources/views/landlord/tenancies/show.blade.php`:
- `Landlord\TenancyController::show()` eager-loads `depositCharges.charger`, `depositCharges.voider`
  alongside the existing eager loads (line ~31), so nothing lazy-loads.
- New "Security deposit" `<x-card>`, placed after the summary tiles (after line ~228) and before the
  rent ledger table: Held / Charged / Remaining refundable stat row, then a table of charges (date,
  category, description, amount, recorded by, Void action) — same "cap to 6, Show all" pattern as the
  Payments card. Voided charges get their own small sub-list beneath, same treatment as the existing
  "Voided entries" card (struck-through, kept for the record).
- "+ Add charge" button opens a teleported modal (RULES.md → Modals & Overlays), same shape as the
  existing "Record payment" modal: amount, category select, description textarea, charged_at date.
  Disabled with an explanatory note when `depositRemaining <= 0` or the tenancy has no deposit at all.
- Void action opens a small reason+note modal, same shape as the existing payment-void modal.

**Tenant** — `resources/views/tenant/tenancy/show.blade.php`:
- `Tenant\TenancyController::show()` eager-loads `depositCharges` (active only is fine to load all and
  filter in-view via `scopeActive()` — small dataset).
- Read-only "Security deposit" card: Held / Charged / Remaining refundable, list of **active** charges
  only (date, category, description, amount) — no void action, and voided charges are omitted rather
  than shown struck-through, matching how the tenant page already has no "voided payments" section
  today (only the landlord side keeps that history visible).

## Verification

1. `php artisan migrate` — confirm `deposit_charges` table is created.
2. As a landlord with an Occupied tenancy that has a deposit collected: open its tenancy page, use
   "+ Add charge" with an amount under the remaining deposit → row appears, tiles update, tenant
   receives a notification linking to their tenancy page.
3. Attempt a charge whose amount exceeds the remaining deposit → rejected with a clear error, no row
   written.
4. Void the charge just created with a reason → it moves to the voided list, the deposit tiles
   recompute back to the pre-charge state, tenant gets a "reversed" notification.
5. As the tenant on that reservation: open the tenancy page, confirm the active charge (or its
   absence after voiding) and the Held/Charged/Remaining figures match the landlord's page.
6. Confirm the two new rows appear correctly in `/admin/audit-logs` with the red "destructive" badge.
