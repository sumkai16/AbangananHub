# Walk-in tenant: future move-in should reserve, not occupy

## Context

`WalkInTenantController::createWalkIn()` always writes `rental_status = 'Occupied'` and
`availability_status = 'Occupied'` the moment a landlord records a walk-in tenant — even when the
tenant's actual move-in date is weeks or months away (`target_move_in_date` is validated up to a
year out). That's wrong: a unit reserved for next month reads as occupied today, which pollutes
occupancy counts, rent-roll totals, and tenant lists. The gap is already acknowledged in the
codebase's own comments (`WalkInScenarios.php` scenario #10) and worked around with a fragile
date-inference filter (`TenantController`'s `moving_in_soon`, which guesses "not really occupied
yet" by comparing `target_move_in_date` to `now()` instead of having a real status for it).

This plan gives walk-ins a real third state — `Reserved` — used only when the move-in date is in
the future, plus a single **"Confirm move-in"** action the landlord clicks once the tenant actually
moves in. Two decisions already confirmed with the user:
- **Not date-gated** — the Confirm button is available any time the reservation is `Reserved`, not
  locked until the move-in date arrives.
- **Forward-fix only** — no backfill migration for existing rows already stuck at `Occupied` with a
  future move-in date.

Platform-pipeline reservations (Inquiry → Negotiation → Agreement → Escrow → Handover → Occupied)
are untouched. This only changes how a **walk-in** reservation gets created and how it later flips
to `Occupied`.

## Status vocabulary (no schema migration needed)

- `reservations.rental_status` is a free-text `string` column — adding `'Reserved'` as a value
  needs no migration.
- `property_units.availability_status` is a MySQL `enum('Available','Reserved','Occupied','Maintenance')`
  — `'Reserved'` **already exists** as a legal value (it's already used while a platform reservation
  is mid-pipeline), so reusing it for a future-dated walk-in needs no migration either.

## Backend changes

**1. `app/Http/Controllers/Landlord/WalkInTenantController.php` — `createWalkIn()`**
Compute once: `$movesInNow = Carbon::parse($data['move_in_date'])->lte(Carbon::today());` — the same
`<=` today comparison `StoreWalkInTenantRequest` already uses for making `initial_amount`
conditional, so it can't drift from that rule. Use it for both:
- `'rental_status' => $movesInNow ? 'Occupied' : 'Reserved',`
- `$unit->update(['availability_status' => $movesInNow ? 'Occupied' : 'Reserved']);`

Also branch the two success-flash strings in `store()` ("...unit is now marked occupied" vs
"...unit is reserved until they move in").

**2. `app/Http/Controllers/Api/Landlord/WalkInTenantController.php`**
Identical two-line change, same comparison. `ReservationResource` is a plain passthrough — no
change needed there.

**3. `app/Models/Reservation.php` — new `confirmWalkInMoveIn()` method**
Copy the shape of `markOccupied()`, not the escrow-release `confirmMoveIn()`:
```php
public function confirmWalkInMoveIn(): bool
{
    if ($this->rental_status !== 'Reserved') {
        return false;
    }
    $this->rental_status = 'Occupied';
    $this->save();
    if ($this->unit) {
        $this->unit->availability_status = 'Occupied';
        $this->unit->save();
    }
    return true;
}
```
Deliberately touches only these two fields — no `keys_turned_over_at`, `handover_*`,
`move_in_deadline_at`, no `postSystemMessage`. A walk-in has no conversation and no held payment to
release. `TERMINAL_STATUSES` stays unchanged — `Reserved` is not terminal, it still holds the unit.

**4. `app/Policies/ReservationPolicy.php` — new `confirmWalkInMoveIn` ability**
```php
public function confirmWalkInMoveIn(User $user, Reservation $reservation): bool
{
    return $reservation->property
        && $reservation->property->landlord_id === $user->user_id
        && $reservation->rental_status === 'Reserved';
}
```

**5. `app/Http/Controllers/Landlord/ReservationController.php` — new action**
Copy `markTurnedOver()`'s pattern (Gate::authorize, `lockForUpdate()` transaction) but skip the
notification/system-message call:
```php
public function confirmWalkInMoveIn(Reservation $reservation)
{
    Gate::authorize('confirmWalkInMoveIn', $reservation);
    $confirmed = DB::transaction(function () use ($reservation) {
        $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
        return $locked->confirmWalkInMoveIn();
    });
    return $confirmed
        ? back()->with('success', 'Move-in confirmed. The unit is now marked occupied.')
        : back()->with('error', 'This reservation can no longer be confirmed as moved in.');
}
```
Also add `'Reserved'` to `VALID_STATUSES` and to the `statusCounts()` array in `index()` so the
status tab/filter works.

**6. `app/Models/PropertyUnit.php` — `activeReservation()` relationship**
Add `'Reserved'` to its `whereIn(['Rental Agreement Signed', 'Occupied'])`. Not cosmetic: two guards
(`Api\Landlord\UnitWriteController` and `Landlord\PropertyUnitController`) rely on this relation
being non-null to block a landlord from manually editing a unit's status out from under an active
reservation. Without this, a `Reserved` unit could be hand-edited back to `Available` while its
walk-in reservation still says `Reserved`, desyncing the two.

**7. Route** — `routes/web.php`, next to `reservations.markTurnedOver`:
```php
Route::post('/reservations/{reservation}/confirm-walk-in-move-in', [ReservationController::class, 'confirmWalkInMoveIn'])->name('reservations.confirmWalkInMoveIn');
```

## Existing logic that already "just works" once the status exists (verified, no change needed)

- `DashboardController`'s `$reservedUnits`, `$rentRoll`, `rentThisMonth()` — already key off
  `availability_status = 'Reserved'` / `rental_status = 'Occupied'` respectively.
- `AnalyticsController::$occupancyBreakdown` (unit-status donut) — already has a Reserved bucket.
- `Reservation::cancel()`/`ReservationPolicy::cancel()` — guard already permits cancelling a
  `Reserved` reservation (only blocks `Occupied`/terminal), so "landlord changed their mind before
  move-in" already works for free — just needs the button surfaced (below).
- `tenancies/show.blade.php`'s status-pill `match()` — its `default` arm already renders `Reserved`
  in a reasonable color.
- `RentLedger::periods()` — already returns an empty collection for a tenancy that hasn't started,
  by design.
- `PaymentController`'s payment-list scope (`['Occupied','Completed']`) — **deliberately left
  unchanged**. A walk-in's initial deposit is still visible on its own tenancy page regardless of
  status (`viewTenancy` isn't status-restricted); adding `Reserved` to the cross-tenant Payments
  list would show a $0-ledger row with a floating deposit and no period to attach it to, which is
  worse than just not listing it until move-in is confirmed.

## Places that need `'Reserved'` added (the actual blast radius)

| File | Change |
|---|---|
| `TenantController::STATUS_GROUPS` | add `'Reserved'` to the `'pending'` bucket |
| `TenantController`'s `moving_in_soon` filter (2 call sites) | replace `where('rental_status','Occupied')->where('target_move_in_date','>',now())` with `where('rental_status','Reserved')` — same card label, real data instead of inference |
| `AnalyticsController::$reservationBreakdown` | fold `'Reserved'` into the existing `'In progress'` slice (smallest change — avoids adding a new donut color/legend entry for what should be low-volume data) |
| `reservations/index.blade.php` — status tabs, `$statusStyles` | add a `'Reserved'` tab + amber pill color (same amber as `Under Negotiation`/`Pending Rental Agreement`) |
| `reservations/index.blade.php` — desktop row actions + mobile card actions | **new `@elseif($reservation->rental_status === 'Reserved')` branch** with a "Confirm move-in" button and a "Cancel" button (both currently render nothing for this status) |
| `reservations/index.blade.php` — modal `$modalData` + template | add `confirm_walk_in_move_in_url` field and a `<template x-if="selected.rental_status === 'Reserved'">` block with the Confirm button, mirroring the turnover block already there |
| `tenancies/show.blade.php` | wrap the existing "Not moved in yet" fallback card in `@if($reservation->rental_status === 'Reserved')` and add the Confirm move-in button there too, since `store()` redirects here right after creating the walk-in |
| `walk-in/create.blade.php` | branch the intro copy, sidebar warning, submit button label ("...& occupy unit" vs "...& reserve unit"), and the `confirmMessage` Alpine getter on the existing `paymentRequired` boolean (already the exact `<=` today check needed — no new date logic) |

## Verification

1. **Scenario A (regression) — move-in = today:** create a walk-in via the UI with today's date,
   confirm via `php artisan tinker` that `rental_status` and the unit's `availability_status` are
   both `Occupied`, and that the tenancy page shows the normal active-tenancy view.
2. **Scenario B — move-in = future:** create a walk-in ~10 days out, leaving the optional payment
   unchecked. Confirm via tinker both fields read `Reserved`. Confirm it does **not** count toward
   the dashboard's occupied-units/rent-roll, does show under the reservations page's new `Reserved`
   tab with working Confirm/Cancel buttons (row, card, and modal), and that the tenancy page shows
   the new "not moved in yet" card with its Confirm button.
3. Click **Confirm move-in** and re-check via tinker that both fields flip to `Occupied`, and that
   dashboard/analytics counts now include it.
4. Regression: create a second future walk-in and click **Cancel** instead — confirm
   `rental_status = Cancelled` and the unit returns to `Available`.

## Critical files
- `app/Http/Controllers/Landlord/WalkInTenantController.php`
- `app/Http/Controllers/Api/Landlord/WalkInTenantController.php`
- `app/Models/Reservation.php`
- `app/Models/PropertyUnit.php`
- `app/Policies/ReservationPolicy.php`
- `app/Http/Controllers/Landlord/ReservationController.php`
- `app/Http/Controllers/Landlord/TenantController.php`
- `app/Http/Controllers/Landlord/AnalyticsController.php`
- `resources/views/landlord/reservations/index.blade.php`
- `resources/views/landlord/tenancies/show.blade.php`
- `resources/views/landlord/tenants/walk-in/create.blade.php`
- `routes/web.php`
