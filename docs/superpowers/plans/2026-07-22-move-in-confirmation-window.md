# Move-In Confirmation Window — Implementation Plan (Phase 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Put deadlines on held escrow deposits so a tenant's confirmation is no longer the only thing that can move the money.

**Architecture:** Two clocks stored in a single `move_in_deadline_at` column — which clock is running is implied by whether `keys_turned_over_at` is set. A nightly Artisan command backfills Clock 1 deadlines, sends Clock 2 reminders, and processes both expiries. All money-moving transitions follow the existing `lockForUpdate()` pattern from `Tenant\AgreementController::confirmMoveIn`.

**Tech Stack:** Laravel 12, MySQL, Blade, Alpine.js, Tailwind, Laravel Reverb (broadcasting), PayMongo (payments).

## Global Constraints

Copied from `context/RULES.md` — these apply to **every** task below.

- **No automated test suite.** Verification is manual: `migrate:fresh --seed`, `route:list`, tinker, and browser checks. See "Verification Approach" below.
- **Build order is layer-by-layer:** migration (+ `$fillable` audit) → model → controller → routes → Blade. Confirm output at each layer before the next.
- **Claude provides commit message text only.** Axcee commits via VSCode source control. Never run `git commit`.
- **Concurrency:** any action that flips a status and does something consequential (moves money, posts a system message) wraps check + write in `DB::transaction()` with `lockForUpdate()`.
- **Broadcasts fire after commit**, never inside the transaction.
- **Notifications go through `Notification::notify()`** — never `Notification::create()` directly (the helper broadcasts; direct creation silently doesn't).
- **Flash messages are complete human sentences** routed through the global modal. Never add a `@if(session('success'))` banner to a view.
- **Authorization:** every mutating action needs a Policy/Gate or an explicit ownership check. "Is logged in" is not authorization.
- **Eager-load every relation the view touches.** `Model::preventLazyLoading` is on in dev and will throw.
- Naming: DB columns `snake_case`, routes dot-notation named, `Gate::authorize()` not `$this->authorize()`.
- Models with custom PKs need explicit `belongsTo` FK + local key arguments.
- Branch: `axci`.

## Verification Approach

`context/RULES.md` → Testing states: *"Manual testing for capstone scope (no automated test suite)."* The repo has no factories beyond `UserFactory` and only Laravel's stub tests. This plan therefore uses **manual verification steps with exact tinker commands and expected output** in place of the TDD cycle.

This is a deliberate deviation from the writing-plans default, made because project rules override skill defaults. See "Recommended Follow-Up" at the end — the money paths are the one place I'd argue for real tests.

**Standard checkpoint before any view work:** `php artisan migrate:fresh --seed` then `php artisan route:list`.

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_07_22_000001_add_move_in_deadline_fields.php` | Create — 5 columns on `reservations`, 1 on `payments` |
| `config/rentals.php` | Create — the three tunable durations plus reminder thresholds |
| `app/Models/Reservation.php` | Modify — deadline computation, turnover, dispute transitions |
| `app/Models/Payment.php` | Modify — `release_reason` fillable |
| `app/Http/Controllers/Landlord/ReservationController.php` | Modify — `markTurnedOver` action |
| `app/Http/Controllers/Tenant/AgreementController.php` | Modify — `release_reason` on confirm, new `disputeMoveIn` action |
| `app/Policies/ReservationPolicy.php` | Modify — `markTurnedOver` ability |
| `app/Console/Commands/ProcessMoveInDeadlines.php` | Create — nightly clock processing |
| `routes/console.php` | Modify — schedule registration |
| `routes/web.php` | Modify — two new routes |
| `resources/views/agreements/show.blade.php` | Modify — countdown, notices, dispute button |
| `resources/views/landlord/reservations/index.blade.php` | Modify — turnover button |
| `resources/views/admin/reservations/index.blade.php` | Modify — disputed filter |

---

### Task 1: Schema and config foundation

**Files:**
- Create: `database/migrations/2026_07_22_000001_add_move_in_deadline_fields.php`
- Create: `config/rentals.php`
- Modify: `app/Models/Reservation.php` (`$fillable`, `casts()`)
- Modify: `app/Models/Payment.php` (`$fillable`)

**Interfaces:**
- Consumes: nothing
- Produces: columns `reservations.keys_turned_over_at`, `.move_in_deadline_at`, `.move_in_disputed_at`, `.move_in_dispute_reason`, `.move_in_last_reminder_on`; `payments.release_reason`; config keys `rentals.move_in_confirmation_days` (int 7), `rentals.turnover_grace_days` (int 7), `rentals.turnover_grace_days_no_date` (int 14), `rentals.reminder_days_remaining` (array `[4, 1, 0]`)

**Note — one column beyond the spec.** The spec lists four `reservations` columns. This adds a fifth, `move_in_last_reminder_on`. Without it, re-running the nightly command on the same day re-sends every reminder, because "days remaining" alone is not an idempotent guard. Flag this to Axcee for a spec amendment.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('keys_turned_over_at')->nullable()->after('tenant_confirmed_move_in_at');
            $table->timestamp('move_in_deadline_at')->nullable()->after('keys_turned_over_at');
            $table->timestamp('move_in_disputed_at')->nullable()->after('move_in_deadline_at');
            $table->text('move_in_dispute_reason')->nullable()->after('move_in_disputed_at');
            $table->date('move_in_last_reminder_on')->nullable()->after('move_in_dispute_reason');

            // The nightly command scans on these two together.
            $table->index(['move_in_deadline_at', 'move_in_disputed_at'], 'reservations_move_in_deadline_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('release_reason', ['tenant_confirmed', 'auto_expiry', 'admin_manual'])
                ->nullable()
                ->after('released_by');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_move_in_deadline_index');
            $table->dropColumn([
                'keys_turned_over_at',
                'move_in_deadline_at',
                'move_in_disputed_at',
                'move_in_dispute_reason',
                'move_in_last_reminder_on',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('release_reason');
        });
    }
};
```

- [ ] **Step 2: Create `config/rentals.php`**

```php
<?php

return [
    /*
     * Clock 2 — days the tenant has to confirm move-in after keys are turned over.
     * Expiry releases the held deposit to the landlord.
     */
    'move_in_confirmation_days' => 7,

    /*
     * Clock 1 — days past the agreed move-in date before an un-turned-over
     * reservation is escalated to admin review.
     */
    'turnover_grace_days' => 7,

    /*
     * Clock 1 fallback when the reservation has no target_move_in_date
     * (the column is nullable). Counted from the payment date instead.
     */
    'turnover_grace_days_no_date' => 14,

    /*
     * Days-remaining thresholds that trigger a Clock 2 reminder.
     * [4, 1, 0] on a 7-day window = day 3, day 6, and the morning of expiry.
     */
    'reminder_days_remaining' => [4, 1, 0],
];
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: `INFO  Running migrations.` followed by `2026_07_22_000001_add_move_in_deadline_fields ... DONE`

- [ ] **Step 4: Audit `$fillable` and `casts()` on `Reservation`**

In `app/Models/Reservation.php`, add to `$fillable` after `'tenant_confirmed_move_in_at',`:

```php
        'keys_turned_over_at',
        'move_in_deadline_at',
        'move_in_disputed_at',
        'move_in_dispute_reason',
        'move_in_last_reminder_on',
```

And add to the array returned by `casts()`:

```php
            'keys_turned_over_at' => 'datetime',
            'move_in_deadline_at' => 'datetime',
            'move_in_disputed_at' => 'datetime',
            'move_in_last_reminder_on' => 'date',
```

- [ ] **Step 5: Audit `$fillable` on `Payment`**

In `app/Models/Payment.php`, add `'release_reason',` to `$fillable` immediately after `'released_by',`.

- [ ] **Step 6: Verify the columns are readable and writable**

Run: `php artisan tinker`

```php
$r = App\Models\Reservation::first();
$r->keys_turned_over_at;              // null
$r->move_in_deadline_at;              // null
config('rentals.move_in_confirmation_days');   // 7
config('rentals.reminder_days_remaining');     // [4, 1, 0]
```

Expected: no `Undefined property` or `Column not found` errors; config returns `7` and `[4, 1, 0]`.

- [ ] **Step 7: Commit**

Provide this message text to Axcee (do not run git):

```
feat: add move-in deadline columns and rental timing config

Schema and config groundwork for the escrow confirmation window. No
behaviour change yet — nothing reads or writes these columns until the
deadline logic lands.

Adds move_in_last_reminder_on beyond the four columns in the spec: without
a per-day guard, re-running the nightly command re-sends every reminder.
```

---

### Task 2: Deadline computation and state transitions on the model

**Files:**
- Modify: `app/Models/Reservation.php`

**Interfaces:**
- Consumes: columns and config keys from Task 1
- Produces:
  - `heldPayment(): ?Payment`
  - `isTurnoverClock(): bool` — true when Clock 1 is the active clock
  - `computeTurnoverDeadline(): ?Carbon` — Clock 1 target, null if no held payment
  - `markKeysTurnedOver(): bool` — sets `keys_turned_over_at` + Clock 2 deadline
  - `disputeMoveIn(string $reason): bool` — freezes the clock
  - `daysUntilMoveInDeadline(): ?int`
  - `confirmMoveIn()` — modified to write `release_reason`

- [ ] **Step 1: Add the helper methods**

Add to `app/Models/Reservation.php`, directly above the `// ─── Relationships ───` divider. Note `use Illuminate\Support\Carbon;` at the top of the file.

```php
    /**
     * The single held payment for this reservation, if any.
     *
     * Both clocks exist only while money is held — no held payment means
     * nothing to protect and no deadline to run.
     */
    public function heldPayment(): ?Payment
    {
        return $this->payments()->where('status', 'Held')->first();
    }

    /**
     * Which clock is running.
     *
     * There is one deadline column and two clocks; turnover is the switch
     * between them. Before turnover the deadline belongs to the landlord
     * (Clock 1), after it to the tenant (Clock 2).
     */
    public function isTurnoverClock(): bool
    {
        return $this->keys_turned_over_at === null;
    }

    /**
     * Clock 1's deadline: how long the landlord has to turn over the keys.
     *
     * Derived from the move-in date both parties negotiated rather than a flat
     * timer — a deposit for a move-in next week and one for a move-in in two
     * months cannot share a countdown.
     *
     * max() guards a deposit paid after an optimistic target date already
     * slipped, which would otherwise produce an already-expired deadline and
     * escalate on the first nightly run.
     */
    public function computeTurnoverDeadline(): ?Carbon
    {
        $payment = $this->heldPayment();

        if (! $payment || ! $payment->paid_at) {
            return null;
        }

        if (! $this->target_move_in_date) {
            return $payment->paid_at->copy()
                ->addDays(config('rentals.turnover_grace_days_no_date'));
        }

        $start = $this->target_move_in_date->greaterThan($payment->paid_at)
            ? $this->target_move_in_date->copy()
            : $payment->paid_at->copy();

        return $start->addDays(config('rentals.turnover_grace_days'));
    }

    /**
     * Landlord asserts the keys changed hands. Starts Clock 2.
     *
     * Deliberately moves no money: this is a self-interested claim by the party
     * who gets paid, so it is the weaker of the two turnover assertions. The
     * tenant's confirmation is what releases the deposit.
     */
    public function markKeysTurnedOver(): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }

        if ($this->keys_turned_over_at !== null) {
            return false;
        }

        if (! $this->heldPayment()) {
            return false;
        }

        $this->keys_turned_over_at = now();
        $this->move_in_deadline_at = now()->addDays(config('rentals.move_in_confirmation_days'));
        $this->move_in_last_reminder_on = null;

        return $this->save();
    }

    /**
     * Tenant asserts the keys never arrived. Freezes the clock for admin review.
     *
     * Freezes rather than pauses — no arithmetic on remaining time. A human
     * resolves it, so partial-time bookkeeping would be a bug farm for a case
     * that never runs unattended.
     */
    public function disputeMoveIn(string $reason): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }

        if ($this->move_in_disputed_at !== null) {
            return false;
        }

        $this->move_in_disputed_at = now();
        $this->move_in_dispute_reason = $reason;
        $this->move_in_deadline_at = null;

        return $this->save();
    }

    /**
     * Whole days left on whichever clock is running. Negative once overdue.
     */
    public function daysUntilMoveInDeadline(): ?int
    {
        if (! $this->move_in_deadline_at || $this->move_in_disputed_at) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays(
            $this->move_in_deadline_at->copy()->startOfDay(),
            false
        );
    }
```

- [ ] **Step 2: Record the release reason in `confirmMoveIn()`**

In the existing `confirmMoveIn()` method, change the `$heldPayment->update([...])` call to:

```php
    $heldPayment->update([
        'status'         => 'Released',
        'released_at'    => now(),
        // Null = released by the platform, not an admin. release_reason is what
        // distinguishes a tenant confirming from a timer firing — released_by is
        // null for both, so it cannot carry that distinction on its own.
        'released_by'    => null,
        'release_reason' => 'tenant_confirmed',
    ]);
```

Also clear the deadline so no stale countdown renders. Add immediately after `$this->tenant_confirmed_move_in_at = now();`:

```php
    $this->move_in_deadline_at = null;
```

- [ ] **Step 2b: Do NOT add a turnover precondition to `confirmMoveIn()`**

`confirmMoveIn()` requires only `rental_status === 'Rental Agreement Signed'` and
a held payment. It deliberately does **not** require `keys_turned_over_at`.

This is load-bearing, not an oversight. It is what lets the tenant assert turnover
themselves — a landlord who hands over the keys but forgets to press the button is
still paid, because the tenant's own confirmation carries the transaction. Adding a
`if (! $this->keys_turned_over_at) return false;` guard would look like tightening
the state machine and would silently break the two-sided turnover assertion the
whole design rests on, stranding honest landlords behind a button they forgot.

Verify this path explicitly in tinker:

```php
$r->update(['keys_turned_over_at' => null, 'move_in_deadline_at' => null]);
$r->payments()->first()->update(['status' => 'Held', 'paid_at' => now()]);
$r->confirmMoveIn();          // true — works with no turnover ever marked
$r->refresh()->rental_status; // 'Occupied'
```

Expected: `true`. A `false` here means a turnover guard crept in.

- [ ] **Step 3: Verify Clock 1 computation against a real reservation**

Run: `php artisan tinker`

```php
$r = App\Models\Reservation::whereHas('payments', fn($q) => $q->where('status','Held'))->first();
// If null, create one first:
//   $r = App\Models\Reservation::where('rental_status','Rental Agreement Signed')->first();
//   $r->payments()->first()->update(['status' => 'Held', 'paid_at' => now()]);
$r->computeTurnoverDeadline()->toDateTimeString();
$r->target_move_in_date?->toDateString();
```

Expected: the deadline is exactly 7 days after `target_move_in_date` when that date is in the future, or 7 days after `paid_at` when the target date has already passed. With no target date, 14 days after `paid_at`.

- [ ] **Step 4: Verify the turnover transition**

Still in tinker:

```php
$r->markKeysTurnedOver();          // true
$r->refresh();
$r->keys_turned_over_at;           // now
$r->daysUntilMoveInDeadline();     // 7
$r->isTurnoverClock();             // false — Clock 2 is running
$r->markKeysTurnedOver();          // false — not repeatable
```

Expected: exactly those values. A second call returning `true` means the idempotency guard is broken.

- [ ] **Step 5: Verify dispute freezes the clock**

```php
$r->disputeMoveIn('Landlord has not responded.');   // true
$r->refresh();
$r->daysUntilMoveInDeadline();     // null — frozen
$r->disputeMoveIn('again');        // false
```

Then reset for later tasks: `$r->update(['move_in_disputed_at' => null, 'move_in_dispute_reason' => null]);`

- [ ] **Step 6: Commit**

```
feat: add move-in deadline computation and turnover transitions

Clock 1 (landlord turnover) derives from the negotiated move-in date plus a
grace period, falling back to a flat window when no date was agreed. Clock 2
(tenant confirmation) is a static 7 days from turnover. One deadline column
serves both; keys_turned_over_at is the switch.

confirmMoveIn now records release_reason. released_by is null for both a
tenant confirmation and a timer expiry, so it could not distinguish them —
which is exactly the distinction a disputed payout needs months later.
```

---

### Task 3: Landlord marks turnover

**Files:**
- Modify: `app/Policies/ReservationPolicy.php`
- Modify: `app/Http/Controllers/Landlord/ReservationController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/landlord/reservations/index.blade.php`

**Interfaces:**
- Consumes: `Reservation::markKeysTurnedOver()` from Task 2
- Produces: route `landlord.reservations.markTurnedOver` (POST `/landlord/reservations/{reservation}/turned-over`)

- [ ] **Step 1: Add the policy ability**

Add to `app/Policies/ReservationPolicy.php`. Match the surrounding style — read the existing landlord abilities in that file first and mirror how they resolve the landlord.

```php
    /**
     * Only the landlord who owns the property can assert turnover, and only
     * while the agreement is signed but unconfirmed.
     */
    public function markTurnedOver(User $user, Reservation $reservation): bool
    {
        return $reservation->property
            && $reservation->property->landlord_id === $user->user_id
            && $reservation->rental_status === 'Rental Agreement Signed';
    }
```

- [ ] **Step 2: Add the controller action**

Add to `app/Http/Controllers/Landlord/ReservationController.php`. Ensure `use App\Models\Notification;`, `use Illuminate\Support\Facades\DB;`, and `use Illuminate\Support\Facades\Gate;` are imported.

```php
    /**
     * Landlord asserts the keys changed hands, starting the tenant's clock.
     *
     * Locked despite moving no money: it starts a countdown that ends in a
     * payout, and a double-click would otherwise post the system message twice.
     * See RULES.md → Concurrency & State Transitions.
     */
    public function markTurnedOver(Reservation $reservation)
    {
        Gate::authorize('markTurnedOver', $reservation);

        $marked = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->markKeysTurnedOver()) {
                return false;
            }

            $days = config('rentals.move_in_confirmation_days');

            $locked->postSystemMessage(
                "The landlord marked the keys as turned over. {$locked->tenant->name} has {$days} days to confirm move-in, after which the deposit is released automatically."
            );

            Notification::notify(
                $locked->tenant_id,
                'move_in_confirmation_due',
                'Confirm your move-in',
                "Your landlord marked the keys as turned over. Confirm your move-in within {$days} days to release your deposit.",
                route('agreements.show', $locked),
                $locked->conversation_id,
            );

            return true;
        });

        if (! $marked) {
            return back()->with('error', 'Turnover cannot be marked for this reservation right now.');
        }

        return back()->with('success', 'Keys marked as turned over. The tenant has been asked to confirm their move-in.');
    }
```

- [ ] **Step 3: Register the route**

In `routes/web.php`, inside the landlord group alongside the existing `reservations.advanceAgreement` route (around line 110):

```php
        Route::post('/reservations/{reservation}/turned-over', [App\Http\Controllers\Landlord\ReservationController::class, 'markTurnedOver'])->name('reservations.markTurnedOver');
```

- [ ] **Step 4: Verify the route resolves**

Run: `php artisan route:clear && php artisan route:list --name=markTurnedOver`
Expected: one row, `POST landlord/reservations/{reservation}/turned-over ... landlord.reservations.markTurnedOver`

- [ ] **Step 5: Add the button to the landlord reservations view**

In `resources/views/landlord/reservations/index.blade.php`, inside the per-reservation action area. Read the surrounding markup first and match its button classes exactly rather than inventing new ones.

```blade
@if ($reservation->rental_status === 'Rental Agreement Signed' && ! $reservation->keys_turned_over_at)
    <form action="{{ route('landlord.reservations.markTurnedOver', $reservation) }}" method="POST">
        @csrf
        <button type="submit"
                class="{{-- match the existing primary action button classes in this file --}}">
            Mark keys turned over
        </button>
    </form>
@elseif ($reservation->keys_turned_over_at)
    <p class="text-sm text-gray-500">
        Keys turned over {{ $reservation->keys_turned_over_at->diffForHumans() }}.
        Awaiting tenant confirmation.
    </p>
@endif
```

- [ ] **Step 6: Confirm the controller eager-loads what this adds**

The view now reads `$reservation->keys_turned_over_at` (a column, not a relation) so no new eager load is needed. Confirm the index action already loads `property` and `tenant` — `preventLazyLoading` throws in dev if not.

Run: `php artisan tinker --execute="echo 'check index() with() call manually'"`, then load the page in the browser.

- [ ] **Step 7: Manual browser check**

1. Log in as a landlord with a `Rental Agreement Signed` reservation that has a `Held` payment.
2. The "Mark keys turned over" button appears.
3. Click it. Expect the success modal (not an inline banner) and the button replaced by the "Keys turned over …" line.
4. Open the linked conversation — the system message is present for **both** parties without a reload on the other side.
5. Log in as that tenant — the notification appears in the dropdown.

- [ ] **Step 8: Commit**

```
feat: let landlords mark keys as turned over

Starts the tenant's confirmation clock and notifies them. Moves no money —
this is the self-interested party asserting the handover, so it is the weaker
of the two turnover signals; the tenant's confirmation is what releases the
deposit.

Locked despite not touching payments: it starts a countdown ending in a
payout, and an unlocked double-click would post the system message twice.
```

---

### Task 4: Tenant dispute action

**Files:**
- Modify: `app/Http/Controllers/Tenant/AgreementController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/agreements/show.blade.php`

**Interfaces:**
- Consumes: `Reservation::disputeMoveIn(string $reason)` from Task 2
- Produces: route `agreements.disputeMoveIn` (POST `/reservations/{reservation}/dispute-move-in`)

- [ ] **Step 1: Add the controller action**

Add to `app/Http/Controllers/Tenant/AgreementController.php`. Add `use App\Models\Notification;` to the imports.

```php
    /**
     * Tenant asserts the keys never arrived, freezing the clock for admin review.
     *
     * This is the counterweight to a landlord being able to start the tenant's
     * countdown unilaterally. Without it, a turnover marked but never performed
     * would release the deposit on a timer.
     */
    public function disputeMoveIn(Request $request, Reservation $reservation)
    {
        Gate::authorize('sign', $reservation);

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ], [
            'reason.required' => 'Please tell us what happened so an admin can help.',
            'reason.min'      => 'Please give us a little more detail — at least 10 characters.',
        ]);

        $disputed = DB::transaction(function () use ($reservation, $validated) {
            $locked = Reservation::whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->disputeMoveIn($validated['reason'])) {
                return false;
            }

            $locked->postSystemMessage(
                $locked->tenant->name . ' reported an issue with the move-in. The deposit is on hold pending review by an administrator.'
            );

            Notification::notify(
                $locked->property?->landlord_id,
                'move_in_disputed',
                'Move-in issue reported',
                $locked->tenant->name . ' reported that the move-in has not happened. An administrator will review this.',
                route('landlord.reservations.index'),
                $locked->conversation_id,
            );

            return true;
        });

        if (! $disputed) {
            return back()->with('error', 'This move-in cannot be disputed right now.');
        }

        return back()->with('success', 'Thanks — your deposit is on hold and an administrator will review this shortly.');
    }
```

- [ ] **Step 2: Register the route**

In `routes/web.php`, immediately after the existing `agreements.confirmMoveIn` route (line 81):

```php
        Route::post('/reservations/{reservation}/dispute-move-in', [AgreementController::class, 'disputeMoveIn'])->name('agreements.disputeMoveIn');
```

- [ ] **Step 3: Verify the route**

Run: `php artisan route:clear && php artisan route:list --name=disputeMoveIn`
Expected: one `POST` row named `agreements.disputeMoveIn`.

- [ ] **Step 4: Add the dispute UI**

In `resources/views/agreements/show.blade.php`, near the existing confirm-move-in form (around line 261). Use the two-flag Alpine modal pattern and teleport it, per RULES.md → Modals & Overlays.

```blade
@if ($reservation->rental_status === 'Rental Agreement Signed' && ! $reservation->move_in_disputed_at)
    <div x-data="{ show: false }">
        <button type="button" @click="show = true"
                class="text-sm text-gray-600 underline hover:text-gray-900">
            I haven't received the keys
        </button>

        <template x-teleport="body">
            <div x-show="show" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-end="opacity-0">
                <div @click.outside="show = false"
                     class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
                     x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0">
                    <h3 class="text-lg font-semibold text-gray-900">Report a move-in issue</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Your deposit stays on hold and an administrator will review this. Your landlord will be notified.
                    </p>

                    <form action="{{ route('agreements.disputeMoveIn', $reservation) }}" method="POST" class="mt-4">
                        @csrf
                        <textarea name="reason" rows="4" required minlength="10"
                                  class="w-full rounded-lg border-gray-300 text-sm"
                                  placeholder="Tell us what happened — for example, the landlord hasn't turned over the keys."></textarea>
                        @error('reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="show = false"
                                    class="rounded-lg px-4 py-2 text-sm text-gray-600">Cancel</button>
                            <button type="submit"
                                    class="rounded-lg bg-[#FF8A65] px-4 py-2 text-sm font-medium text-white">
                                Submit report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
@elseif ($reservation->move_in_disputed_at)
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
        <p class="text-sm font-medium text-amber-900">Move-in issue under review</p>
        <p class="mt-1 text-sm text-amber-800">
            Reported {{ $reservation->move_in_disputed_at->diffForHumans() }}. Your deposit is on hold
            and the countdown is paused while an administrator reviews this.
        </p>
    </div>
@endif
```

- [ ] **Step 5: Manual browser check**

1. As the tenant on a signed agreement, click "I haven't received the keys". The modal animates in and is not clipped by its parent card.
2. Submit with 3 characters — the validation error renders inline under the textarea, **not** in the flash modal.
3. Submit a valid reason — success modal, and the amber "under review" panel replaces the button.
4. As the landlord, confirm the notification arrived.
5. Reload the tenant page — the button does not come back.

- [ ] **Step 6: Commit**

```
feat: let tenants report a move-in that never happened

Freezes the confirmation clock and routes the reservation to admin review
with the deposit still held. This is the counterweight to a landlord being
able to start the tenant's countdown unilaterally — without it, a turnover
marked but never performed would release the deposit on a timer.

Freezes rather than pauses: a human resolves these, so tracking partial time
remaining would be bookkeeping that never runs unattended.
```

---

### Task 5: Nightly deadline processing command

**Files:**
- Create: `app/Console/Commands/ProcessMoveInDeadlines.php`
- Modify: `routes/console.php`

**Interfaces:**
- Consumes: `computeTurnoverDeadline()`, `heldPayment()`, `isTurnoverClock()`, `daysUntilMoveInDeadline()` from Task 2
- Produces: command `reservations:process-move-in-deadlines`

This is the task that moves money without a human present. It gets the most careful locking.

- [ ] **Step 1: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Events\PaymentStatusUpdated;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessMoveInDeadlines extends Command
{
    protected $signature = 'reservations:process-move-in-deadlines';

    protected $description = 'Backfill turnover deadlines, send move-in reminders, and process expired escrow clocks';

    public function handle(): int
    {
        $this->backfillTurnoverDeadlines();
        $this->sendConfirmationReminders();
        $this->escalateOverdueTurnovers();
        $this->releaseExpiredConfirmations();

        return self::SUCCESS;
    }

    /**
     * Clock 1 starts when the deposit is held, but nothing in the payment path
     * writes the deadline — deriving it here keeps the PayMongo webhook out of
     * this feature entirely.
     */
    protected function backfillTurnoverDeadlines(): void
    {
        $candidates = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNull('move_in_deadline_at')
            ->whereNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->whereHas('payments', fn ($q) => $q->where('status', 'Held'))
            ->with('payments')
            ->get();

        $count = 0;

        foreach ($candidates as $reservation) {
            $deadline = $reservation->computeTurnoverDeadline();

            if (! $deadline) {
                continue;
            }

            $reservation->update(['move_in_deadline_at' => $deadline]);
            $count++;
        }

        $this->info("Backfilled {$count} turnover deadline(s).");
    }

    /**
     * Clock 2 reminders. Guarded by move_in_last_reminder_on so a same-day
     * re-run cannot re-notify — days-remaining alone is not idempotent.
     */
    protected function sendConfirmationReminders(): void
    {
        $thresholds = config('rentals.reminder_days_remaining');

        $reservations = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNotNull('keys_turned_over_at')
            ->whereNotNull('move_in_deadline_at')
            ->whereNull('move_in_disputed_at')
            ->where(function ($q) {
                $q->whereNull('move_in_last_reminder_on')
                  ->orWhereDate('move_in_last_reminder_on', '<', today());
            })
            ->with(['tenant', 'property'])
            ->get();

        $count = 0;

        foreach ($reservations as $reservation) {
            $daysLeft = $reservation->daysUntilMoveInDeadline();

            if (! in_array($daysLeft, $thresholds, true)) {
                continue;
            }

            $message = $daysLeft === 0
                ? 'Today is the last day to confirm your move-in. After today your deposit is released to the landlord automatically.'
                : "You have {$daysLeft} day(s) left to confirm your move-in. After that your deposit is released to the landlord automatically.";

            Notification::notify(
                $reservation->tenant_id,
                'move_in_confirmation_reminder',
                'Confirm your move-in',
                $message,
                route('agreements.show', $reservation),
                $reservation->conversation_id,
            );

            $reservation->update(['move_in_last_reminder_on' => today()]);
            $count++;
        }

        $this->info("Sent {$count} confirmation reminder(s).");
    }

    /**
     * Clock 1 expiry — phase 1. Nobody has claimed the keys changed hands, so
     * this is escalated to an admin rather than refunded automatically.
     *
     * Reuses the dispute queue: one admin screen, one set of resolution
     * actions. A system-written reason distinguishes this from a tenant report.
     */
    protected function escalateOverdueTurnovers(): void
    {
        $overdue = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->whereNotNull('move_in_deadline_at')
            ->where('move_in_deadline_at', '<=', now())
            ->pluck('reservation_id');

        $count = 0;

        foreach ($overdue as $id) {
            $escalated = DB::transaction(function () use ($id) {
                $locked = Reservation::whereKey($id)->lockForUpdate()->first();

                if (! $locked || $locked->move_in_disputed_at || $locked->keys_turned_over_at) {
                    return false;
                }

                $locked->update([
                    'move_in_disputed_at'    => now(),
                    'move_in_dispute_reason' => 'Landlord did not turn over the keys by the deadline.',
                    'move_in_deadline_at'    => null,
                ]);

                $locked->postSystemMessage(
                    'The key turnover deadline passed without confirmation. The deposit is on hold pending review by an administrator.'
                );

                Notification::notify(
                    $locked->tenant_id,
                    'turnover_overdue',
                    'Move-in deadline passed',
                    'Your landlord did not confirm the key turnover in time. Your deposit is still held and an administrator will review this.',
                    route('agreements.show', $locked),
                    $locked->conversation_id,
                );

                Notification::notify(
                    $locked->property?->landlord_id,
                    'turnover_overdue',
                    'Key turnover deadline passed',
                    'The turnover deadline for this reservation has passed. An administrator will review the held deposit.',
                    route('landlord.reservations.index'),
                    $locked->conversation_id,
                );

                return true;
            });

            if ($escalated) {
                $count++;
            }
        }

        $this->info("Escalated {$count} overdue turnover(s) to admin review.");
    }

    /**
     * Clock 2 expiry — releases the deposit to the landlord.
     *
     * The only place in the app where money moves with no human present, so the
     * held payment is locked inside the same transaction as the status flip and
     * the broadcast is deferred until after commit.
     */
    protected function releaseExpiredConfirmations(): void
    {
        $expired = Reservation::query()
            ->where('rental_status', 'Rental Agreement Signed')
            ->whereNotNull('keys_turned_over_at')
            ->whereNull('move_in_disputed_at')
            ->whereNotNull('move_in_deadline_at')
            ->where('move_in_deadline_at', '<=', now())
            ->pluck('reservation_id');

        $released = [];

        foreach ($expired as $id) {
            $payment = DB::transaction(function () use ($id) {
                $locked = Reservation::whereKey($id)->lockForUpdate()->first();

                if (! $locked || $locked->move_in_disputed_at || ! $locked->keys_turned_over_at) {
                    return null;
                }

                $heldPayment = $locked->payments()
                    ->where('status', 'Held')
                    ->lockForUpdate()
                    ->first();

                if (! $heldPayment) {
                    return null;
                }

                // tenant_confirmed_move_in_at stays null on purpose. It means
                // what it says; conflating "confirmed" with "timed out" would
                // poison occupancy reporting.
                $locked->update([
                    'rental_status'       => 'Occupied',
                    'move_in_deadline_at' => null,
                ]);

                $heldPayment->update([
                    'status'         => 'Released',
                    'released_at'    => now(),
                    'released_by'    => null,
                    'release_reason' => 'auto_expiry',
                ]);

                if ($locked->unit) {
                    $locked->unit->update(['availability_status' => 'Occupied']);
                }

                $locked->postSystemMessage(
                    'The move-in confirmation window closed. The unit is marked occupied and the deposit has been released to the landlord.'
                );

                Notification::notify(
                    $locked->property?->landlord_id,
                    'payment_released',
                    'Deposit released',
                    'The tenant did not confirm within the confirmation window, so the deposit has been released to you automatically.',
                    route('landlord.reservations.index'),
                    $locked->conversation_id,
                );

                Notification::notify(
                    $locked->tenant_id,
                    'payment_released',
                    'Deposit released to your landlord',
                    'Your move-in confirmation window has closed, so your deposit was released to the landlord automatically.',
                    route('agreements.show', $locked),
                    $locked->conversation_id,
                );

                return $heldPayment;
            });

            if ($payment) {
                $released[] = $payment;
            }
        }

        // After commit — a payout announced inside the transaction could still
        // be rolled back underneath the broadcast.
        foreach ($released as $payment) {
            PaymentStatusUpdated::dispatch($payment->fresh());
        }

        $this->info('Released ' . count($released) . ' expired confirmation(s).');
    }
}
```

- [ ] **Step 2: Register the schedule**

In `routes/console.php`, after the existing `occupancy:snapshot` line:

```php
// Move-in escrow clocks — backfills turnover deadlines, sends confirmation
// reminders, and processes both clock expiries. Runs before the occupancy
// snapshot so units it marks Occupied are counted the same night.
Schedule::command('reservations:process-move-in-deadlines')->dailyAt('23:00');
```

- [ ] **Step 3: Verify the command registers**

Run: `php artisan list | Select-String "process-move-in"`
Expected: `reservations:process-move-in-deadlines  Backfill turnover deadlines, ...`

- [ ] **Step 4: Verify the backfill on a real row**

```powershell
php artisan tinker
```

```php
$r = App\Models\Reservation::where('rental_status','Rental Agreement Signed')->first();
$r->payments()->first()->update(['status'=>'Held','paid_at'=>now()->subDays(30)]);
$r->update(['move_in_deadline_at'=>null,'keys_turned_over_at'=>null,'move_in_disputed_at'=>null,'target_move_in_date'=>now()->subDays(20)]);
exit
```

Run: `php artisan reservations:process-move-in-deadlines`
Expected: `Backfilled 1 turnover deadline(s).` then `Escalated 0 overdue turnover(s)` — the deadline is 7 days after a date 20 days ago, so it is already past.

Run it a **second time**. Expected: `Backfilled 0`, `Escalated 1`. The escalation happens on the run after the backfill, which is correct — the row is escalated the same night only if the deadline was already stored.

- [ ] **Step 5: Verify the Clock 2 auto-release end to end**

```php
$r = App\Models\Reservation::where('rental_status','Rental Agreement Signed')->first();
$r->payments()->first()->update(['status'=>'Held','paid_at'=>now(),'released_at'=>null,'release_reason'=>null]);
$r->update([
  'keys_turned_over_at' => now()->subDays(8),
  'move_in_deadline_at' => now()->subDay(),
  'move_in_disputed_at' => null,
]);
```

Run: `php artisan reservations:process-move-in-deadlines`
Expected: `Released 1 expired confirmation(s).`

Then verify:

```php
$r->refresh();
$r->rental_status;                    // 'Occupied'
$r->tenant_confirmed_move_in_at;      // null — NOT set, this was a timeout
$r->move_in_deadline_at;              // null
$r->unit->availability_status;        // 'Occupied'
$p = $r->payments()->latest('payment_id')->first();
$p->status;                           // 'Released'
$p->release_reason;                   // 'auto_expiry'
$p->released_by;                      // null
```

If `tenant_confirmed_move_in_at` is populated, the implementation is wrong — that field must distinguish a real confirmation from a timeout.

- [ ] **Step 6: Verify idempotency**

Run the command a second time immediately.
Expected: `Released 0 expired confirmation(s).` A non-zero count means a released payment is being re-released.

- [ ] **Step 7: Verify reminders fire once per day**

```php
$r->update([
  'keys_turned_over_at'      => now()->subDays(6),
  'move_in_deadline_at'      => now()->addDay(),
  'move_in_last_reminder_on' => null,
  'rental_status'            => 'Rental Agreement Signed',
]);
$r->payments()->first()->update(['status'=>'Held','released_at'=>null,'release_reason'=>null]);
```

Run the command. Expected: `Sent 1 confirmation reminder(s).`
Run it again the same day. Expected: `Sent 0 confirmation reminder(s).`

- [ ] **Step 8: Commit**

```
feat: process move-in escrow deadlines nightly

Backfills Clock 1 deadlines from held payments, sends tenant reminders at 4,
1, and 0 days remaining, escalates overdue turnovers to admin review, and
releases deposits whose confirmation window closed.

The auto-release is the only place money moves with no human present, so the
held payment is locked inside the same transaction as the status flip and the
broadcast is deferred until after commit.

tenant_confirmed_move_in_at is deliberately left null on a timeout — it means
what it says, and conflating it with a real confirmation would poison
occupancy reporting.

Reminders are guarded by a per-day column rather than by days-remaining
alone, which is not idempotent across same-day re-runs.
```

---

### Task 6: Tenant countdown and payment notice

**Files:**
- Modify: `app/Http/Controllers/Tenant/AgreementController.php` (`show` eager loads)
- Modify: `resources/views/agreements/show.blade.php`

**Interfaces:**
- Consumes: `daysUntilMoveInDeadline()`, `isTurnoverClock()` from Task 2
- Produces: no new interfaces — presentation only

Per the spec, the countdown is what makes the auto-release defensible. If a deposit is ever released on a timer, we need to show the tenant was warned and could see the clock the whole time.

- [ ] **Step 1: Add the two-state notice block**

In `resources/views/agreements/show.blade.php`, immediately above the existing confirm-move-in form (around line 261):

```blade
@php
    $daysLeft = $reservation->daysUntilMoveInDeadline();
@endphp

@if ($reservation->rental_status === 'Rental Agreement Signed' && ! $reservation->move_in_disputed_at)
    @if ($reservation->isTurnoverClock())
        {{-- Clock 1: nothing for the tenant to do yet. No countdown — showing one
             here would imply a deadline the tenant can miss, and this one is the
             landlord's. --}}
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4">
            <p class="text-sm font-medium text-sky-900">Payment secured</p>
            <p class="mt-1 text-sm text-sky-800">
                Your deposit is held safely and is not released until you confirm your move-in.
                Your landlord will contact you to turn over the keys.
            </p>
        </div>
    @elseif ($daysLeft !== null)
        {{-- Clock 2: live countdown. --}}
        <div class="rounded-lg border p-4 {{ $daysLeft <= 1 ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50' }}">
            <p class="text-sm font-semibold {{ $daysLeft <= 1 ? 'text-red-900' : 'text-amber-900' }}">
                @if ($daysLeft <= 0)
                    Today is your last day to confirm your move-in
                @else
                    {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} left to confirm your move-in
                @endif
            </p>
            <p class="mt-1 text-sm {{ $daysLeft <= 1 ? 'text-red-800' : 'text-amber-800' }}">
                Confirming releases your deposit to the landlord. If you don't confirm by
                {{ $reservation->move_in_deadline_at->format('M j, Y') }}, it is released automatically.
            </p>
        </div>
    @endif
@endif
```

- [ ] **Step 2: Check the eager loads**

`show()` already loads `['property', 'property.landlord', 'tenant', 'unit', 'payments']`. The countdown reads only reservation columns, so no change is needed. Confirm by loading the page with `preventLazyLoading` active — it throws on a miss rather than failing quietly.

- [ ] **Step 3: Manual browser check across all three states**

Set up each state in tinker and reload the tenant's agreement page:

```php
// State A — Clock 1 (paid, no turnover): expect the blue "Payment secured" panel, no countdown
$r->update(['keys_turned_over_at'=>null,'move_in_deadline_at'=>now()->addDays(10),'move_in_disputed_at'=>null]);

// State B — Clock 2, comfortable: expect amber, "5 days left"
$r->update(['keys_turned_over_at'=>now()->subDays(2),'move_in_deadline_at'=>now()->addDays(5)]);

// State C — Clock 2, urgent: expect red, "1 day left"
$r->update(['move_in_deadline_at'=>now()->addDay()]);

// State D — last day: expect red, "Today is your last day..."
$r->update(['move_in_deadline_at'=>now()]);
```

Confirm the confirm-move-in button is present in B, C, and D, and that the dispute link from Task 4 is present throughout.

- [ ] **Step 4: Commit**

```
feat: show tenants a live move-in countdown and payment notice

Two states, deliberately distinct: while the landlord's turnover clock runs
the tenant sees a reassurance panel with no countdown, because that deadline
is not theirs to miss. Once keys are turned over it becomes a live countdown
that states plainly what happens if they do nothing.

This is what makes the auto-release defensible — if a deposit is released on
a timer, the tenant was reminded three times and could see the clock
throughout.
```

---

### Task 7: Admin review queue

**Files:**
- Modify: `app/Http/Controllers/Admin/ReservationController.php`
- Modify: `resources/views/admin/reservations/index.blade.php`

**Interfaces:**
- Consumes: `move_in_disputed_at`, `move_in_dispute_reason` from Task 1
- Produces: `?filter=disputed` query parameter on the admin reservations index

Both Clock 1 escalations and tenant disputes land here. Without this surface, phase 1 escalates into a queue nobody can see.

- [ ] **Step 1: Add the filter to the admin controller**

Read the existing `index()` method first and match how it builds filters and paginates. Add to the query builder:

```php
        // Both entry points to review land in move_in_disputed_at: a tenant
        // reporting a missing turnover, and the nightly job timing one out.
        if ($request->query('filter') === 'disputed') {
            $query->whereNotNull('move_in_disputed_at');
        }
```

Ensure the paginator carries the filter: `->withQueryString()` (required by RULES.md whenever filters are active).

- [ ] **Step 2: Add a count for the badge**

In the same `index()` method:

```php
        $disputedCount = Reservation::whereNotNull('move_in_disputed_at')->count();
```

Pass `$disputedCount` to the view alongside the existing data.

- [ ] **Step 3: Add the filter tab and reason column**

In `resources/views/admin/reservations/index.blade.php`, matching the existing filter-control markup in that file:

```blade
<a href="{{ route('admin.reservations.index', ['filter' => 'disputed']) }}"
   class="{{ request('filter') === 'disputed' ? 'font-semibold text-[#156F8C]' : 'text-gray-600' }}">
    Needs review
    @if ($disputedCount > 0)
        <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">{{ $disputedCount }}</span>
    @endif
</a>
```

And inside the row markup, so an admin can tell the two entry points apart without opening the record:

```blade
@if ($reservation->move_in_disputed_at)
    <p class="mt-1 text-xs text-red-700">
        Needs review — {{ $reservation->move_in_dispute_reason }}
        <span class="text-gray-500">({{ $reservation->move_in_disputed_at->diffForHumans() }})</span>
    </p>
@endif
```

- [ ] **Step 4: Manual browser check**

1. Trigger one tenant dispute (Task 4) and one nightly escalation (Task 5).
2. Load the admin reservations index — the "Needs review" badge shows 2.
3. Click the filter — only those two rows appear, and the URL keeps `?filter=disputed` across pagination.
4. Confirm the tenant-written reason and the system-written "Landlord did not turn over the keys by the deadline." are visibly different.
5. Confirm the existing admin release action on the Payments screen still works on a disputed reservation's held payment — that is the resolution path for phase 1.

- [ ] **Step 5: Commit**

```
docs: surface move-in reviews in the admin reservations queue

Both a tenant-reported dispute and a nightly turnover timeout write
move_in_disputed_at, so one filter covers both. The reason text distinguishes
them: tenant-written prose versus the system's fixed sentence.

Without this, phase 1 escalates into a queue nobody can see.
```

---

## Recommended Follow-Up

**Verify PayMongo refund support before planning phase 2.** Phase 2 (automatic Clock 1 refunds) depends entirely on refunds being issuable via API. If they are not, phase 1's admin escalation is permanent, and the tenant-facing promise has to stay "we step in and sort it out" rather than "you get refunded automatically."

**Consider automated tests for the money paths.** `context/RULES.md` sets manual testing as the project standard and this plan follows it. That said, `ProcessMoveInDeadlines::releaseExpiredConfirmations()` is the only code in the app that moves money with no human in the loop, and its correctness depends on conditions that are tedious to reproduce by hand every time something nearby changes. Three tests — releases when expired, does not release when disputed, does not double-release — would cover the cases that actually cost money if they regress. That needs `ReservationFactory`, `PropertyUnitFactory`, `PaymentFactory`, and a MySQL test database (the schema uses raw `ALTER TABLE ... MODIFY COLUMN` enum statements, so SQLite in-memory will not work). It is a real scope addition, which is why it is a recommendation rather than a task.

**Watch the escalation volume after launch.** If Clock 1 expiries are common rather than exceptional, the admin queue becomes the bottleneck and phase 2 stops being optional.
