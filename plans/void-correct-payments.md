# Void & Correct Payments — implementation plan

**Spec source:** `docs/NEEDS UPDATE.pdf` §18 ("Do Not Delete Financial Records"), the last unimplemented section of the analyst spec. `plans/rent-payments-page-redesign.md` §9 sketched this as Phase 6; this plan is the full version and supersedes that sketch (it agrees with it, and adds the parts it left open).

**One-line summary:** a landlord can mark a payment they recorded as **Voided** — the row survives untouched with a reason, an actor and a timestamp; it stops counting everywhere; and the ledger page offers to re-record a corrected replacement that points back at it.

Read §2 first. It answers the six design forks and states what was rejected and why.

**Status:** planned Aug 31 2026 — not yet implemented.

---

## 1. Why this is small

The investigation turned up three facts that shrink the work dramatically:

1. **`RentLedger` filters on a whitelist, in all four places it touches payments.** `otherCharges()`, `monthlyTransactions()`, `latestSettledMonthlyPeriod()` and `monthlyPaymentsFor()` each test `in_array($p->status, self::SETTLED_STATUSES, true)` where `SETTLED_STATUSES = ['Paid','Held','Released']`. A new `Voided` enum member falls out of all four with **zero changes to that class**. Same for `Landlord\AnalyticsController::EARNED_STATUSES` and its API twin, and for `Admin\PayoutController` (which filters on `payout_status`, never on `Paid`).
2. **A `voided_at` + `whereNull()` shape would be wrong here.** `RentLedger::__construct` reads `$reservation->payments` from the **already eager-loaded relation** (`with('payments')` in `Landlord\PaymentController::index()`/`export()` and `TenancyController::show()`), so a `whereNull` on the relation query would be silently bypassed on every real page. A global scope on `Payment` would fix that and then hide voided rows from the audit table that must display them. **The status enum is the only shape that works with the code as written.** This is the single most important decision in the plan.
3. **The payout inconsistency is structurally impossible.** Every landlord-recorded row (`recorded_by` non-null) is written by `Landlord\PaymentController::store()`, its API twin, or `Concerns\RecordsMoveInPayments` — none of which set `payout_status`, which stays null by design ("the platform never touched this money, so there is nothing for it to pay out"). Void is restricted to landlord-recorded rows, so a `Paid Out` row can never reach it. We still add an explicit guard, because that is cheap and it keeps the invariant true if a future path changes.

---

## 2. Decisions locked

### 2a. One mechanism — `Voided`. "Reverse" and "Correct" collapse into it.

The spec offers three verbs. They are one capability plus one workflow:

| Spec verb | What it becomes |
|---|---|
| **Void Payment** | The mechanism: `status = 'Voided'` + who/when/why. The row is preserved verbatim. |
| **Reverse Payment** | **Collapsed into Void.** A reversal in double-entry bookkeeping is a contra entry with a negative amount. `payments` is a single-entry table and `amount` is summed raw in six places (`Admin\PaymentController` cards, both `PayoutController`s, both `AnalyticsController`s, `RentLedger`). A negative row would poison every one of them and would also be printable as a receipt. Rejected. |
| **Correct Payment** | **A workflow over Void, not a second mechanism:** void the wrong row, then immediately re-record the right one, with the new row(s) carrying `replaces_payment_id` back to the voided one. |

Do not build three features. Build one column set and three words of UI copy.

### 2b. Per-row void. No batch/group column.

`Landlord\PaymentController::store()` can write N rows via `RentPaymentAllocator`, and `RecordsMoveInPayments` writes deposit + N monthly rows. Neither carries a group identifier — only an implicit `reservation_id` + `paid_at` + `reference_no` + `recorded_by`.

**Decision: the payment row is the unit of void.** Reasons:

- **There is no reliable existing group key to derive.** `paid_at` is a *date* from a date input, so two genuinely separate cash payments recorded on the same day for the same tenancy with no reference number are indistinguishable from one split entry. Merging them would void money the landlord never asked to void.
- **Backfilling a `payment_batch_id` onto existing rows means inferring it**, which RULES.md → Money-Moving Code explicitly bans ("Never invent a split when backfilling money… A wrong-looking old row beats a plausible fabricated one").
- **Each row is individually meaningful** — it settles one named billing month. The common correction ("I picked September, it was August") is a *single*-row fix, and the landlord frequently wants to keep the other months.

Cost: correcting a mistyped ₱30,000 that split three ways is three voids. Accepted for this phase.

**Escape hatch if manual testing says that hurts:** add a nullable `entry_group_id` written only by *new* entries (old rows stay null — honest, not invented), and offer "void the whole entry" only when it is present. Nothing in this plan blocks that later.

### 2c. Reason is an enum + an optional note

Precedent: `release_reason` is `ENUM('tenant_confirmed','auto_expiry','admin_manual')` — a named cause, not free text, because it is "the field a disputed payout is argued from months later" (SCHEMA.md). Same here. Six causes, plus a 255-char note that is **required only for `other`**:

```
wrong_amount   Wrong amount entered
wrong_month    Wrong billing month
wrong_tenancy  Recorded against the wrong tenant
duplicate      Duplicate entry
not_received   Payment did not clear
other          Other  (note required)
```

### 2d. A correction creates new row(s), linked by `replaces_payment_id` on the new row

Direction matters: a correction of one voided row can produce **several** new rows (the allocator splits), so the FK must live on the new row pointing back, not on the voided row pointing forward.

Set only through the "Void and record a correction" flow. Every other `store()` leaves it null. It is one column, one hidden input, one key in `$shared` — cheap enough to be worth the third clause of §18 ("be able to say what replaced it"). **If you are cutting scope, this is the one droppable piece**; void alone still satisfies §18's core requirement.

### 2e. No time limit on voiding. A `payout_status` guard instead.

A 30-day window would be arbitrary and would strand exactly the case §18 exists for — an error found at year-end reconciliation. The real hazard is not age, it is *"money already moved on the strength of this row"*, and `payout_status` names that precisely. Guard: `abort_if($locked->payout_status !== null, 409)`. Today that branch is unreachable (see §1.3); it is there so it stays unreachable.

### 2f. Void lives on `Landlord\PaymentController`, authorized by a new `voidPayment` gate

- **Controller:** extend `Landlord\PaymentController`. It is already the home of `store`/`receipt`/`export` on this exact model, and `void()` is ~50 lines. RULES.md → KISS: no service layer.
- **Gate:** a new `ReservationPolicy::voidPayment()` that delegates to `viewTenancy()` — deliberately **not** `recordPayment`, because `recordPayment` requires `rental_status === 'Occupied'` ("a closed ledger should not gain new entries"). A wrong number in a *closed* ledger is still a wrong financial record, and removing a false assertion is not the same as adding a new one. So: void works on `Completed` tenancies; recording (including the correction re-record) does not. State that in the docblock.

### 2g. Admin sees it, the tenant is told

- **Admin:** the void writes an `audit_logs` row (`payment.void`, in `DESTRUCTIVE_ACTIONS`), and `Admin\PaymentController::index` gains a `Voided` tab. This platform's thesis is admin-verified accountability; a landlord unilaterally erasing a rent credit is precisely the kind of act an admin arbitrating a dispute must be able to see. This is the **first landlord-side writer of `audit_logs`** — see §9 for the three view strings that say "Admin" and need to say "Actor".
- **Tenant:** a void increases what the tenant appears to owe, silently, on their own ledger page. Send one `Notification::notify()` — guarded by `$tenant && ! $tenant->is_walk_in`, the same guard `TenancyController::remind()` uses, since a walk-in has no account to notify.

---

## 3. Build Order

Per RULES.md. Confirm each layer before the next.

### Step 1 — Migration

`database/migrations/2026_08_31_000000_add_void_fields_to_payments_table.php`

```php
public function up(): void
{
    // Raw ALTER: MySQL enums can't be widened through the Blueprint. Same
    // approach as 2026_07_16_112509_update_payment_status_enum and
    // 2026_07_24_000003_add_manual_recording_to_payments_table.
    DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('Pending','Paid','Held','Released','Failed','Refunded','Voided') NOT NULL DEFAULT 'Pending'");

    Schema::table('payments', function (Blueprint $table) {
        $table->timestamp('voided_at')->nullable()->after('payout_reference');
        $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
        $table->foreign('voided_by')->references('user_id')->on('users')->nullOnDelete();

        // Named causes, not free text — same role release_reason plays for
        // releases: this is the field a disputed ledger is argued from.
        $table->enum('void_reason', [
            'wrong_amount', 'wrong_month', 'wrong_tenancy',
            'duplicate', 'not_received', 'other',
        ])->nullable()->after('voided_by');
        $table->string('void_note', 255)->nullable()->after('void_reason');

        // The correction that replaced a voided row. On the NEW row, not the
        // voided one: one void can be replaced by several rows when
        // RentPaymentAllocator splits the corrected amount across months.
        $table->unsignedBigInteger('replaces_payment_id')->nullable()->after('void_note');
        $table->foreign('replaces_payment_id')->references('payment_id')->on('payments')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('payments', function (Blueprint $table) {
        $table->dropForeign(['replaces_payment_id']);
        $table->dropForeign(['voided_by']);
        $table->dropColumn(['voided_at', 'voided_by', 'void_reason', 'void_note', 'replaces_payment_id']);
    });

    // Restore voided rows to Paid before narrowing the enum — MySQL would
    // otherwise blank or reject them. Dev rollback only; it resurrects money
    // rows, which is the correct behaviour for undoing this migration.
    DB::statement("UPDATE payments SET status = 'Paid' WHERE status = 'Voided'");
    DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('Pending','Paid','Held','Released','Failed','Refunded') NOT NULL DEFAULT 'Pending'");
}
```

No new index. Every query that reads these is already per-reservation and covered by `payments_reservation_period_index`.

### Step 2 — Model: `app/Models/Payment.php`

`$fillable` audit (RULES.md: mandatory after any column-adding migration) — append:

```php
'voided_at', 'voided_by', 'void_reason', 'void_note', 'replaces_payment_id',
```

`$casts` — add `'voided_at' => 'datetime'`.

Add:

```php
/** Named causes a recorded payment can be voided for. Keys are the enum members. */
public const VOID_REASONS = [
    'wrong_amount'  => 'Wrong amount entered',
    'wrong_month'   => 'Wrong billing month',
    'wrong_tenancy' => 'Recorded against the wrong tenant',
    'duplicate'     => 'Duplicate entry',
    'not_received'  => 'Payment did not clear',
    'other'         => 'Other',
];

public function isVoided(): bool { return $this->status === 'Voided'; }

/**
 * Only a landlord's own assertion can be voided, and only while the platform
 * still owes nothing against it. A PayMongo-settled payment is evidence, not
 * an assertion — it goes through a refund, which this app does not have.
 */
public function canBeVoided(): bool
{
    return $this->isManuallyRecorded()
        && $this->status === 'Paid'
        && $this->payout_status === null;
}

public function voidReasonLabel(): ?string
{
    return $this->void_reason ? (self::VOID_REASONS[$this->void_reason] ?? $this->void_reason) : null;
}

public function voider(): BelongsTo
{
    return $this->belongsTo(User::class, 'voided_by', 'user_id');
}

/** The voided row this payment was entered to replace, if it was a correction. */
public function replaces(): BelongsTo
{
    return $this->belongsTo(Payment::class, 'replaces_payment_id', 'payment_id');
}

public function replacements(): HasMany
{
    return $this->hasMany(Payment::class, 'replaces_payment_id', 'payment_id');
}
```

`canBeVoided()` is for the view. It is not the control — the controller re-asserts all three conditions under a lock.

### Step 3 — Service: `app/Services/RentLedger.php` (one method, nothing removed)

The four existing whitelist filters are **untouched** — a `Voided` row already falls out of them. Add one reader so the voided rows are still visible somewhere (otherwise they vanish from the UI entirely, which would defeat §18):

```php
/**
 * Payments struck from the ledger after being entered wrongly. They settle
 * nothing — every other reader here whitelists SETTLED_STATUSES, so a void
 * needs no guard added anywhere — but the rows are preserved and shown,
 * because a financial record that quietly loses a transaction is exactly
 * what "do not delete financial records" is about.
 */
public function voidedTransactions(): Collection
{
    return $this->payments
        ->filter(fn (Payment $p) => $p->status === 'Voided')
        ->sortByDesc(fn (Payment $p) => $p->voided_at ?? $p->paid_at ?? $p->created_at)
        ->values();
}
```

Also extend the `SETTLED_STATUSES` docblock with one sentence naming `Voided` as deliberately absent.

### Step 4 — Policy: `app/Policies/ReservationPolicy.php`

```php
/**
 * Voiding removes a wrong entry rather than adding a new one, so — unlike
 * recordPayment — it is deliberately NOT restricted to 'Occupied'. A wrong
 * figure in a closed ledger is still a wrong financial record, and the
 * landlord who owns the property is the only person who can say so.
 */
public function voidPayment(User $user, Reservation $reservation): bool
{
    return $this->viewTenancy($user, $reservation);
}
```

Also fix the now-false docblock on `recordPayment` ("writes a money row that nothing in the app can reverse") → "…that only a void can undo, never a delete".

### Step 5 — Controller: `app/Http/Controllers/Landlord/PaymentController.php`

**5a. New method `void()`**, placed after `store()`:

```php
/**
 * Strike a recorded payment from the ledger without deleting it.
 *
 * Nothing is erased: the row keeps its amount, month, date and reference and
 * gains who voided it, when, and why. `Voided` is outside
 * RentLedger::SETTLED_STATUSES and AnalyticsController::EARNED_STATUSES, so
 * the month it settled reopens and the money stops counting as revenue with
 * no further change to either.
 *
 * Landlord-recorded rows only. A PayMongo-settled payment is evidence, not
 * an assertion — a landlord cannot declare that it did not happen.
 */
public function void(Request $request, Payment $payment)
{
    $payment->loadMissing(['reservation.property', 'reservation.tenant']);
    $reservation = $payment->reservation;

    abort_unless($reservation !== null, 404);
    Gate::authorize('voidPayment', $reservation);

    $data = $request->validate([
        'void_reason' => ['required', Rule::in(array_keys(Payment::VOID_REASONS))],
        'void_note'   => ['required_if:void_reason,other', 'nullable', 'string', 'max:255'],
        'correct'     => ['nullable', 'boolean'],
    ], [
        'void_note.required_if' => 'Say what went wrong with this payment.',
    ]);

    $voided = DB::transaction(function () use ($payment, $data, $reservation) {
        // Money row: lock and re-assert every precondition inside the
        // transaction, per RULES.md → Concurrency. A double-submit hits the
        // status check on the second pass and 409s without logging twice.
        $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

        abort_unless($locked->isManuallyRecorded(), 403, 'Only a payment you recorded yourself can be voided.');
        abort_unless($locked->status === 'Paid', 409, 'This payment has already been voided, or was settled through the platform.');
        // Unreachable today — landlord-recorded rows never carry a payout
        // status — and here so that stays true if a future path changes it.
        abort_if($locked->payout_status !== null, 409, 'This payment has already been paid out and can no longer be voided.');

        $locked->update([
            'status'      => 'Voided',
            'voided_at'   => now(),
            'voided_by'   => Auth::id(),
            'void_reason' => $data['void_reason'],
            'void_note'   => $data['void_note'] ?? null,
        ]);

        AuditLog::record(
            'payment.void',
            '₱' . number_format((float) $locked->amount, 2) . ' payment voided on '
                . ($reservation->property->title ?? 'a tenancy'),
            $locked,
            Payment::VOID_REASONS[$data['void_reason']]
                . (! empty($data['void_note']) ? ' — ' . $data['void_note'] : ''),
            [
                'reservation_id' => $locked->reservation_id,
                'amount'         => (float) $locked->amount,
                'payment_type'   => $locked->payment_type,
                'billing_period' => $locked->billing_period?->toDateString(),
                'paid_at'        => $locked->paid_at?->toDateString(),
            ],
        );

        return $locked;
    });

    $this->notifyTenantOfVoid($voided, $reservation);

    $redirect = back()->with('success', 'Payment voided. The original entry stays on record.');

    // Hands the record-payment modal everything it needs to reopen prefilled
    // with the corrected figures, and to link the replacement back to this row.
    if ($request->boolean('correct')) {
        $redirect->with('correct_payment', [
            'payment_id'     => $voided->payment_id,
            'payment_type'   => $voided->payment_type,
            'amount'         => (float) $voided->amount,
            'billing_period' => $voided->billing_period?->toDateString(),
        ]);
    }

    return $redirect;
}
```

Plus a small private helper (mirrors `TenancyController::notifyTenancyEnded`'s guard):

```php
/**
 * A void raises what the tenant appears to owe, on a page they can open. A
 * walk-in has no account to tell — the landlord's own record is the only
 * channel there, same limitation ProcessRentReminders already lives with.
 */
private function notifyTenantOfVoid(Payment $payment, Reservation $reservation): void
{
    $tenant = $reservation->tenant;

    if (! $tenant || $tenant->is_walk_in) {
        return;
    }

    Notification::notify(
        $tenant->user_id,
        'payment',
        'A recorded payment was corrected',
        'Your landlord voided a ₱' . number_format((float) $payment->amount, 2)
            . ' entry on your rent ledger (' . strtolower($payment->voidReasonLabel()) . '). Check your balance.',
        route('tenant.tenancy.show', $reservation),
    );
}
```

New imports: `App\Models\AuditLog`, `App\Models\Notification`.

**5b. `store()` accepts an optional `replaces_payment_id`.** Add to the validation array:

```php
'replaces_payment_id' => ['nullable', 'integer'],
```

and inside the transaction, before `$shared` is built:

```php
// Scoped to this reservation and to an actually-voided row: without the
// scope this is an IDOR that lets one landlord point a payment at another
// landlord's row. `exists:` alone would not catch that.
$replaces = ! empty($data['replaces_payment_id'])
    ? Payment::where('payment_id', $data['replaces_payment_id'])
        ->where('reservation_id', $locked->reservation_id)
        ->where('status', 'Voided')
        ->value('payment_id')
    : null;
```

then add `'replaces_payment_id' => $replaces,` to `$shared` (so every split row carries it — all of them replace that one voided entry).

**5c. `receipt()` refuses a voided payment.** After the existing `isManuallyRecorded()` check:

```php
abort_if($payment->isVoided(), 404);
```

Otherwise a landlord can print a branded receipt for a payment the ledger says never counted.

### Step 6 — Route: `routes/web.php`

Immediately after the receipt route (currently line 204), inside the same landlord group:

```php
Route::post('/payments/{payment}/void', [App\Http\Controllers\Landlord\PaymentController::class, 'void'])->name('payments.void');
```

Full name **`landlord.payments.void`**. POST, not DELETE — it is emphatically not a delete, and `admin.payments.release` (the closest analogue) is POST too. Run `php artisan route:clear` before trusting it.

Also add `'payments.voider'` to the eager-load array in `Landlord\TenancyController::show()` (`Model::preventLazyLoading` is on in dev) and pass the new collection:

```php
'voidedTransactions' => $ledger->voidedTransactions(),
```

### Step 7 — Blade

**7a. New partial: `resources/views/landlord/tenancies/partials/void-payment-modal.blade.php`**

Model it directly on `record-payment-modal.blade.php`: two-flag Alpine (`show` for visibility so the leave transition plays), `<template x-teleport="body">`, backdrop `bg-[#0F172A]/40 backdrop-blur-sm`, panel enter `300ms ease-[cubic-bezier(0.34,1.56,0.64,1)]` with `motion-reduce:` variants, leave `200ms ease-in`. Include it next to the record-payment modal at the bottom of `show.blade.php`.

```blade
<div x-data="{
        show: false,
        payment: { id: null, action: '', amount: 0, label: '' },
        reason: 'wrong_amount',
        note: '',
        correct: false,
        open(detail) {
            this.payment = detail; this.reason = 'wrong_amount';
            this.note = ''; this.correct = false; this.show = true;
        },
        close() { this.show = false; },
     }"
     x-on:open-void-payment.window="open($event.detail)"
     x-on:keydown.escape.window="close()">
```

Body:
- Headline `Void this payment`, sub-line built from `payment.label` + `payment.amount`.
- A grey callout: *"The entry stays on your records with the reason you give. It stops counting toward rent collected, and the month it settled reopens."*
- Reason: `<x-styled-select name="void_reason" x-model="reason" :options="\App\Models\Payment::VOID_REASONS" selected="wrong_amount" …>` — the same component the record modal uses.
- Note: `<input name="void_note" x-model="note" maxlength="255">`, wrapped in `<div x-show="reason === 'other'" x-cloak>`, `:required="reason === 'other'"`.
- Checkbox `<input type="checkbox" name="correct" value="1" x-model="correct">` labelled **"Record a corrected payment straight after"** — this is the "Correct Payment" verb.
- Form: `<form method="POST" :action="payment.action">@csrf` … submit button `x-text="correct ? 'Void and correct' : 'Void payment'"`, in the destructive red family (`bg-[#DC2626]`, `hover:brightness-95`).
- `@error('void_reason')` / `@error('void_note')` inline beside their fields (RULES.md: validation stays inline), and reopen the modal on a failed submit the same way the record modal does — `show: {{ $errors->hasAny(['void_reason','void_note']) ? 'true' : 'false' }}`.

Because the reason is a required *choice*, this cannot be a `data-confirm` form — that pattern is yes/no only. This modal **is** the confirmation step; do not also wrap the submit in `data-confirm`.

**7b. `resources/views/landlord/tenancies/show.blade.php` — three edits**

1. **Rent payments table (~line 259–287).** Bump `min-w-[640px]` → `min-w-[760px]`, add a sixth `<th>` (`<span class="sr-only">Actions</span>`) and per-row cell:

```blade
<td class="px-4 py-3.5 text-right whitespace-nowrap">
    @if($txn->canBeVoided())
        <button type="button"
            @click="$dispatch('open-void-payment', {
                id: {{ $txn->payment_id }},
                action: @js(route('landlord.payments.void', $txn)),
                amount: {{ (float) $txn->amount }},
                label: @js(($txn->billing_period?->format('M Y') ?? '') . ' rent, recorded ' . ($txn->paid_at?->format('M d, Y') ?? ''))
            })"
            class="text-[12.5px] font-semibold text-[#64748B] hover:text-[#DC2626] transition-colors duration-200 cursor-pointer">
            Void
        </button>
    @endif
</td>
```

`@js()` hex-escapes quotes, so it is safe inside a double-quoted Alpine attribute (RULES.md → Blade Compile Traps). Never interpolate a raw string there. Passing the route through the dispatch detail keeps the named route and avoids URL string surgery in JS.

2. **Deposits & other payments table (~line 299–336).** Same treatment — deposits get mistyped too. Label: `@js($charge->payment_type . ', recorded ' . ...)`.

3. **New card, immediately after the "Deposits & other payments" card (~line 337), before the `</div>` that closes the ledger column:**

```blade
@if($voidedTransactions->isNotEmpty())
    <x-card flush>
        {{-- Kept on the page deliberately: a voided entry is removed from the
             arithmetic, never from the record. --}}
        <div class="px-5 sm:px-6 py-4 border-b border-[#E2E8F0]">
            <h2 class="text-[15px] font-bold text-[#1F2937]">Voided entries</h2>
            <p class="text-[12px] text-[#64748B] mt-0.5">Corrections made to this ledger. These do not count toward anything.</p>
        </div>
        …table: Date | Reference | Amount | Applied to | Reason | Voided
    </x-card>
@endif
```

Row styling: amount `line-through text-[#94A3B8]`; a neutral pill `border-[#E2E8F0] bg-[#F7FCFC] text-[#64748B]` reading `Voided` (never red — this is a resolved correction, not an alert); reason cell shows `$p->voidReasonLabel()` with `$p->void_note` as a `text-[11px] text-[#94A3B8]` sub-line; "Voided" cell shows `$p->voided_at?->format('M d, Y')` and `by {{ $p->voider?->first_name }}`. If `$p->replacements->isNotEmpty()`, add a `Corrected` sub-line.

**7c. `record-payment-modal.blade.php` — prefill for the correction flow**

In the `@php` block at the top:

```php
$correction = session('correct_payment');
```

In `x-data`, change three initializers (leave the existing fallbacks as the `??` right-hand side):

```
show: {{ $errors->hasAny($recordPaymentFields) || $correction ? 'true' : 'false' }},
type: @js($correction['payment_type'] ?? 'Monthly'),
amount: @js($correction ? number_format($correction['amount'], 2, '.', '') : ($summary['monthlyRent'] > 0 ? number_format($summary['monthlyRent'], 2, '.', '') : '')),
period: @js($correction['billing_period'] ?? ($defaultPeriod ? $defaultPeriod['period']->toDateString() : '')),
```

Inside the `<form>`, after `@csrf`:

```blade
{{-- Present only on the correction flow. old() keeps the link alive across a
     failed re-submit, when the flashed session value is already gone. --}}
<input type="hidden" name="replaces_payment_id"
       value="{{ old('replaces_payment_id', $correction['payment_id'] ?? '') }}">
```

And **change the footnote copy** (~line 262), which is now false:

> ~~"Nothing in the app can reverse a recorded payment, so check the amount before saving."~~
> → "Recorded as received by you, not held in escrow by AbangananHub. If you enter something wrong, you can void it afterwards from this ledger — the original entry stays on record."

Also add a small teal banner at the top of the panel when `$correction` is set: *"Recording a correction for the ₱X entry you just voided."*

---

## 8. Admin visibility

`app/Http/Controllers/Admin/PaymentController.php`:
- `STATUSES` → `['All', 'Held', 'Released', 'Paid', 'Voided', 'Pending']`
- `$counts['Voided'] = Payment::where('status', 'Voided')->count();`
- Leave `$sums` alone — a voided total is not money and does not belong on a summary card.

`resources/views/admin/payments/index.blade.php`:
- Add `'Voided'` to the tab array (line 60).
- Add an `@elseif ($payment->status === 'Voided')` badge branch beside the existing four (line ~146), neutral slate, and a sub-line showing `voided_at` + `voidReasonLabel()` in the same place `Released {{ … }}` renders (line ~178). Without this the generic `@else` fallback renders a bare word with no context.

The three summary cards and the `Held`-default landing tab are unchanged.

---

## 9. Audit log wiring

`app/Models/AuditLog.php`:
- `ACTION_LABELS` → add `'payment.void' => 'Payment voided',`
- `DESTRUCTIVE_ACTIONS` → add `'payment.void'` (it changes money and overrides a recorded fact — same class as `payment.release`).

`resources/views/admin/audit-logs/index.blade.php` — this is the **first non-admin actor** to appear in the trail, and three strings assume otherwise:
- line ~35 `label="Admins Involved"` → `"Actors Involved"`
- line ~120 `<th>Admin</th>` → `<th>Actor</th>`
- line ~110 empty-state "Admin actions will be recorded here as they happen." → "Actions will be recorded here as they happen."

No controller change — `AuditLogController::index` filters on action and text, not on role.

---

## 10. Things not to break

- **`RentLedger`'s four whitelist filters.** Do not "add a void guard" to them — they already exclude `Voided`, and adding `!== 'Voided'` checks is dead code that suggests the whitelist is not trusted.
- **`summary()`'s existing keys.** 13 consumers including the mobile API. A void changes the *numbers* those keys return (correctly); it must not change their meaning.
- **`PaymentObserver` will now fire on a landlord-recorded row for the first time ever.** `store()` only ever `create`s, and the observer hooks `updated`, so this is the first status change these rows have seen. `PaymentStatusUpdated::broadcastOn()` is null-safe on `conversation_id` (walk-ins have none) and `derivedStage()` returns `rental_status` for an Occupied tenancy, so the payload is harmless — but the broadcast is `ShouldBroadcastNow`, i.e. synchronous, so **Reverb must be running for the void to succeed in dev**, same as every other payment status change. Do not "fix" this by bypassing the observer.
- **Never `delete()` a payment.** There is still no destroy route anywhere and this plan adds none. That is the whole point of §18.
- **`replaces_payment_id` must be re-scoped server-side** to the same reservation and to a `Voided` row. A bare `exists:payments,payment_id` is an IDOR.
- **API parity is explicitly deferred** (see §12) — but note `Api\Landlord\TenancyController` reads `otherCharges()`, so voided rows drop out of the mobile payloads automatically. No drift, no work.

---

## 11. Manual test checklist

No automated tests (RULES.md → Testing). Fixtures via `php artisan walkin:scenarios`; extend it if the states below are missing. Run `php artisan migrate` then `route:clear` first, and have Reverb running.

**Setup:** one landlord, one property, tenants A (₱10,000/mo, one month recorded correctly), B (₱30,000 recorded in one entry, split Aug/Sep/Oct by the allocator), C (a walk-in with a move-in lump split into deposit + rent), D (an **ended / Completed** tenancy with a recorded payment), E (a **platform** tenant whose rent came through PayMongo — `recorded_by` null).

**Void mechanics**
- [ ] A's ledger: "Void" appears on the recorded rent row and on any deposit row.
- [ ] E's PayMongo-settled row shows **no** Void button; POSTing to its void route by hand returns **403**.
- [ ] Voiding A's rent row: row leaves "Rent payments", appears under "Voided entries" struck through with the reason, the voider's name and the date.
- [ ] A's month flips back to Overdue/Due, `Total Unpaid` and `Overdue` rise by ₱10,000, `Collected` falls by ₱10,000.
- [ ] `landlord/payments` index: A's row status and the four summary cards move by the same amounts. Reload — figures are stable, not drifting.
- [ ] Landlord Analytics revenue drops by exactly ₱10,000 (the voided row leaves `EARNED_STATUSES`).
- [ ] `void_reason = Other` with an empty note → inline error under the note field, modal stays open, nothing voided.
- [ ] Double-submit the same void (back button, or two tabs) → second attempt 409s; **exactly one** `audit_logs` row exists for it.
- [ ] `landlord/payments/{id}/receipt` for the voided payment returns **404**.

**Correction flow**
- [ ] Tick "Record a corrected payment straight after" → after the void, the record-payment modal opens **already filled** with the voided type/amount/month and shows the correction banner.
- [ ] Change the amount to the right one and save. The new row appears in "Rent payments"; the voided row's entry shows `Corrected`; the month settles.
- [ ] Submit the correction with an invalid amount → errors render inline, the modal reopens, and the replacement link survives (check `replaces_payment_id` is still set on the retry).

**Multi-row entries (per-row void — §2b)**
- [ ] B's ₱30,000 entry shows as three rows; voiding the September row leaves August and October intact and reopens **only** September.
- [ ] Voiding all three individually returns B to fully unpaid with three separate "Voided entries" rows.
- [ ] C (walk-in): voiding the deposit row leaves the monthly rows settled; the deposit leaves `Collected` but `Total Unpaid` does not change (a deposit is not a debt).

**Permissions, edges, audit**
- [ ] Log in as a **second landlord** and POST to the first landlord's void route → **403**.
- [ ] D (Completed tenancy): Void **works**; "Record payment" is still absent (`recordPayment` requires Occupied). Ticking "correct" on D redirects with the modal unavailable — confirm this degrades gracefully and the void still succeeded.
- [ ] Admin → Payments: a `Voided` tab exists with the right count; the row's badge and reason render; the `Held`/`Released`/`Paid` cards are unchanged.
- [ ] Admin → Audit Logs: a `Payment voided` entry appears, badged destructive, with the reason and the **landlord's** name; the column header reads "Actor", not "Admin".
- [ ] Tenant side (platform tenant, not walk-in): a "A recorded payment was corrected" notification arrives and links to their tenancy page; the voided payment is gone from their ledger and their balance rose accordingly.
- [ ] Walk-in tenant: **no** notification attempted, no error in `laravel.log`.
- [ ] 375px: both new modals and the "Voided entries" table are usable, no body-level horizontal scroll.
- [ ] `prefers-reduced-motion`: the void modal fades without moving.
- [ ] Debugbar query count on `landlord/tenancies/{id}` has not jumped (the `payments.voider` eager load is one extra query, not N).

---

## 12. Out of scope (state it, don't drift into it)

- **API parity.** `Api\Landlord\PaymentController` gains no `void` endpoint this phase — the mobile client is unbuilt and covers no money-write paths beyond `store()`. Voided rows already drop out of every API read for free.
- **A batch/group void** — deliberate, §2b, with the escape hatch documented there.
- **Refunds.** There is still no refund action anywhere in the app (ARCHITECTURE.md, Phase 1 limitation). Voiding is not a refund and the copy must never suggest it moves money back.
- **Tenant-visible void history.** The tenant is *notified*, but their ledger page does not grow a "Voided entries" table. Add it only if a tester asks.
- **Admin ability to void.** Landlord-only. An admin overriding a landlord's own financial record is a bigger governance question than a capstone needs.

## 13. Docs to update when it lands

- `context/SCHEMA.md` → `payments`: add the five columns, add `Voided` to the `status` enum row with a note that it is outside `SETTLED_STATUSES`/`EARNED_STATUSES` **by design, not by a guard**, and add the migration to the migration table. (While in there: `payout_status` is documented as an ENUM but the migration creates it as a `string` — worth a one-line correction, separate commit.)
- `context/ARCHITECTURE.md` → one paragraph under the rent-ledger section: void is the only correction path, per-row, landlord-only, audit-logged.
- `plans/rent-payments-page-redesign.md` → mark Phase 6 done and point at this file.

Commit split (RULES.md → Git Discipline): `feat: void recorded payments (migration + model)` / `feat: void payment action and correction flow` / `feat(admin): voided payments tab and audit action` / `docs: schema + architecture notes for payment voiding`.
