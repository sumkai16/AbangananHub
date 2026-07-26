# Admin Audit Logs & System Settings

Plan date: 2026-07-26. Build order: **Audit Logs first, System Settings second.**

## Context — why only these two

The admin sidebar carried six `Soon` entries. Investigation found three were redundant and one blocked:

| Item | Finding | Outcome |
|---|---|---|
| Messages | SVG path byte-identical to the built **Conversations** entry; `admin.conversations.index`/`show` already exist | Removed from sidebar |
| Inquiries | `Inquiry` is `STATUSES[0]` in `Admin\ReservationController`; the index already renders status tabs with counts, reachable at `?status=Inquiry` | Removed from sidebar |
| Reports (2nd, under Reports & Analytics) | Abuse reports already live under Content & Reviews (`admin.reports.index`); Analytics already exports properties/reservations/users via `report-analytics.export` | Removed from sidebar |
| Rental Businesses | Table + model + `UpdateRentalBusinessRequest` exist, but **0 rows**, **no landlord controller or route to create one**, and no property↔business FK (SCHEMA.md confirms link is only `landlord_id`) | **Parked** — stays greyed out pending a product decision |
| System Settings | No settings table exists | **Build (2nd)** |
| Audit Logs | No log table exists; admin has destructive, untraceable actions | **Build (1st)** |

---

# Feature 1 — Audit Logs ✅ SHIPPED July 26 2026

Built as planned. All four design decisions held; no deviations. Verified: 409-on-repeat writes exactly
one row, rollback leaves zero phantom rows, audit rows survive actor deletion with `actor_id` nulled,
all four filters isolate correctly. `settings.update` is present in `ACTION_LABELS` but intentionally
unwired until Feature 2.

Two small scope additions made during the build, both to satisfy D1's atomicity requirement on actions
that had no transaction at all: `UserController::store` and `::destroy` are now wrapped in
`DB::transaction()` so the audit row and the mutation commit together.


## Problem
Admin can force-cancel a reservation, force-reject one, approve/reject verifications, approve/reject listings and units, release escrowed money, suspend and delete users, and hide reviews. **None of it is recorded.** There is no way to answer "who released this payment, when, and why."

## Precedent to follow
`OccupancyActivity` (model + `PropertyUnitObserver`) is the established activity-log pattern: custom PK, `actor_id` from `Auth::id()`, indexed on `(owner, created_at)`. Audit logs follow the same shape, with three deliberate differences noted below.

## Design decisions

### D1 — Explicit controller-level writes, not an observer *(recommended)*
`OccupancyActivity` uses an observer because it tracks one column on one model. An audit log needs to record **intent**, which a model event cannot see:
- *why* (`rejection_reason`, admin note) — an observer only sees the column diff
- *that it was an admin override* — `forceCancel` and a tenant cancelling produce the same status write
- actions with no model diff at all (a hard delete)

So: an `AuditLog::record(...)` helper called explicitly from each admin action.

**Writes go inside the existing `DB::transaction()` + `lockForUpdate()` block** (RULES.md § Concurrency). This matters: a rolled-back action must not leave a phantom log row, and a 409 on the idempotency guard must not log a second time.

Tradeoff accepted: a future admin action can forget to log. Mitigated by keeping the call adjacent to the `->update()` it describes, and by the finite, enumerated action list below.

### D2 — `actor_id` is `onDelete('set null')`, plus a denormalized actor snapshot
Every other FK to `users.user_id` in this schema is `onDelete('cascade')`. **An audit log must not cascade** — deleting a user would erase exactly the history that proves what they did. So `set null`, *and* store `actor_name` + `actor_email` as plain strings captured at write time, so the row stays readable afterwards.

### D3 — Append-only: `created_at` only, no `updated_at`
An audit row is never edited. `const UPDATED_AT = null` on the model; migration declares only `created_at`. No update/delete routes are exposed.

### D4 — Polymorphic target
Actions span reservations, users, payments, properties, units, verifications, reviews, reports. `auditable_type` / `auditable_id` (Laravel morph) rather than nine nullable FK columns.

### D5 — Read-only admin UI
Index + filters only. No create, no edit, no delete — including no "clear logs" button. Retention/pruning is out of scope for this pass.

## Schema

`database/migrations/2026_07_26_000001_create_audit_logs_table.php`

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id('log_id');
    $table->unsignedBigInteger('actor_id')->nullable();   // set null on user delete — D2
    $table->string('actor_name');                          // denormalized snapshot — D2
    $table->string('actor_email');
    $table->string('action', 60);                          // 'reservation.force_cancel'
    $table->string('auditable_type')->nullable();          // morph target — D4
    $table->unsignedBigInteger('auditable_id')->nullable();
    $table->string('summary');                             // human sentence, rendered verbatim
    $table->text('reason')->nullable();                    // rejection reason / admin note
    $table->json('metadata')->nullable();                  // before/after, amounts
    $table->string('ip_address', 45)->nullable();          // 45 = IPv6
    $table->timestamp('created_at')->nullable();           // D3 — no updated_at

    $table->foreign('actor_id')->references('user_id')->on('users')->onDelete('set null');
    $table->index(['created_at']);
    $table->index(['action', 'created_at']);
    $table->index(['auditable_type', 'auditable_id']);
});
```

## Actions to instrument (the full enumerated list)

| Controller | Method | `action` |
|---|---|---|
| `Admin\ReservationController` | `forceCancel` | `reservation.force_cancel` |
| `Admin\ReservationController` | `forceReject` | `reservation.force_reject` |
| `Admin\VerificationController` | `approve` | `verification.approve` |
| `Admin\VerificationController` | `reject` | `verification.reject` |
| `Admin\ListingController` | `approve` | `listing.approve` |
| `Admin\ListingController` | `reject` | `listing.reject` |
| `Admin\PropertyUnitController` | `approve` | `unit.approve` |
| `Admin\PropertyUnitController` | `reject` | `unit.reject` |
| `Admin\PaymentController` | `release` | `payment.release` |
| `Admin\UserController` | `store` | `user.create` |
| `Admin\UserController` | `update` | `user.update` |
| `Admin\UserController` | `updateStatus` | `user.status_change` |
| `Admin\UserController` | `destroy` | `user.delete` |
| `Admin\ReviewController` | `toggleHidden` | `review.visibility_change` |
| `Admin\ReportController` | `resolve` | `report.resolve` |

`payment.release` is the highest-value row here — it moves money.

## Files

**New**
- `database/migrations/2026_07_26_000001_create_audit_logs_table.php`
- `app/Models/AuditLog.php` — `$primaryKey = 'log_id'`, `const UPDATED_AT = null`, `actor()` belongsTo, `auditable()` morphTo, static `record()` helper
- `app/Http/Controllers/Admin/AuditLogController.php` — `index` only
- `resources/views/admin/audit-logs/index.blade.php` — `<x-page-header>` + `<x-stat-card>` row + filter bar + table (§6d)

**Modified**
- `routes/web.php` — one GET route, admin-gated
- `resources/views/layouts/admin.blade.php` — Audit Logs becomes a live link
- the 9 admin controllers above
- `context/SCHEMA.md`, `context/ARCHITECTURE.md`

## UI
Follows the conventions settled today: `<x-page-header>` with icon slot, a `<x-stat-card>` row (total actions, actions today, distinct admins, destructive actions), then the §6d dense-table pattern — filter bar in its own flat card above a `<x-card flush>` table. Filters: search (actor/summary), action-type select, date range via `<x-date-picker>`. Destructive actions (`*.delete`, `*.force_*`, `payment.release`) carry a red pill; routine ones slate.

## Verification checklist (manual)
1. Force-cancel a reservation → one row, correct actor, reason captured.
2. Double-submit the same force-cancel → second request 409s, **no second row**.
3. Release a payment → row with amount in `metadata`.
4. Delete an admin who has log rows → rows survive, `actor_id` null, name/email still render.
5. Filters: by action type, by date range, by search term.
6. No create/edit/delete affordance anywhere in the UI.

---

# Feature 2 — System Settings

## Problem
The escrow clocks are business rules that currently require a code deploy to change: `move_in_confirmation_days`, `turnover_grace_days`, `turnover_grace_days_no_date`, `handover_max_extension_days`, `reminder_days_remaining`, plus the rent-ledger keys.

## Design decisions

### D6 — DB-backed override of `config()` at boot *(recommended)*
`config('rentals.*)` is read in **13 places** across `app/Models/Reservation.php`, `app/Services/RentLedger.php`, two console commands, and two controllers. Rewriting all 13 to call a settings service would be a large, risky diff and would violate KISS.

Instead: a `settings` table holding only **overrides**, loaded once in a service provider's `boot()` and merged over the existing config:

```php
// AppServiceProvider::boot()
foreach (Setting::allCached() as $key => $value) {
    config(["rentals.$key" => $value]);
}
```

`config/rentals.php` stays as the **defaults and the documentation** (it already carries good explanatory comments). All 13 call sites are untouched and keep working. An unset setting falls through to the file default.

Cached (`Cache::rememberForever`) and busted on write, so this costs one cache read per request, not a query.

**Caveat to respect:** this runs on console commands too, so `ProcessMoveInDeadlines` and `ProcessRentReminders` pick up admin changes automatically — which is the desired behaviour, and worth asserting in the manual test.

### D7 — Whitelisted keys, validated, typed
Not a free-form key/value editor. A `Setting::DEFINITIONS` map declares each editable key with its type, validation rule, label, and help text; the form renders from it and a Form Request validates against it. Prevents an admin setting a grace period to `-5` or `banana`.

### D8 — Every change writes an audit log row
Settings changes are exactly the kind of consequential, hard-to-trace action Feature 1 exists for. `settings.update` with before/after in `metadata`. **This is the dependency that makes Audit Logs the correct first build.**

## Schema

`database/migrations/2026_07_26_000002_create_settings_table.php`

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id('setting_id');
    $table->string('key', 100)->unique();
    $table->text('value');            // cast per DEFINITIONS type
    $table->timestamps();
});
```

## Files

**New**
- `database/migrations/2026_07_26_000002_create_settings_table.php`
- `app/Models/Setting.php` — `DEFINITIONS`, `allCached()`, cache busting on save
- `app/Http/Controllers/Admin/SettingController.php` — `edit` + `update`
- `app/Http/Requests/Admin/UpdateSettingsRequest.php` — rules derived from `DEFINITIONS`
- `resources/views/admin/settings/edit.blade.php`

**Modified**
- `app/Providers/AppServiceProvider.php` — config override in `boot()`
- `routes/web.php` — GET + PATCH, admin-gated
- `resources/views/layouts/admin.blade.php` — System Settings becomes a live link
- `context/SCHEMA.md`, `context/ARCHITECTURE.md`

## UI
`<x-page-header>` + grouped setting cards (Escrow clocks / Rent ledger), each field showing its label, current value, help text from `DEFINITIONS`, and the file default so an admin can see what they've diverged from. Single Save with `data-confirm` — these change money timing.

## Verification checklist (manual)
1. Change `move_in_confirmation_days` → new reservations use the new window.
2. Confirm `php artisan` commands see the override (D6 caveat).
3. Invalid input (negative, non-numeric) → inline validation error, no write.
4. Change a setting → audit log row with before/after.
5. Clear the setting → falls back to the `config/rentals.php` default.

---

## Out of scope
- Rental Businesses (parked — needs landlord-side CRUD and a product decision first)
- Audit log retention/pruning
- Exporting audit logs to CSV
- Settings beyond the `rentals` namespace
