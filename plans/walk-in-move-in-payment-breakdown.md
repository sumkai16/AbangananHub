# Walk-in Move-In Payment Breakdown & Advance Rent

Rework the Initial payment step of **Add Walk-in Tenant** so the landlord sees what the move-in
money is *for*, so month one actually settles in the rent ledger, and so an overpayment becomes
advance rent against real future billing periods.

Origin: analyst feedback (Aug 2026) on `landlord/tenants/walk-in/create`. Roughly half the spec is
adopted; the parts that fight this feature's purpose are deliberately not — see §8.

---

## 1. The bug this actually fixes

`WalkInTenantController::recordInitialPayment()` writes **one** `Payment` row with
`payment_type = 'Initial'` (or `'Deposit'`).

`RentLedger::monthlyPaymentsFor()` only counts rows with `payment_type = 'Monthly'`, and `'Initial'`
sits in `RentLedger::NON_MONTHLY_TYPES`.

**So a walk-in tenant who hands over rent + deposit on day one still shows their move-in month as
Due, then Overdue.** The money lands in `otherCharges()` as an opaque lump that settles nothing. This
is present in both the web and API controllers today and is the strongest reason to do this work at
all — the analyst's document circles it without naming it.

---

## 2. Core design decision: advance rent needs no schema change

Advance rent is **not** a new `payment_type`. It is a set of `Monthly` payment rows whose
`billing_period` falls in future months — which is exactly the shape `RentLedger` already reads.

- No migration on `payments`.
- No new enum member (`payment_type` stays `Initial | Monthly | Deposit | Utility | Other`).
- Prepaid months settle through the same code path as any other month's rent.

A single `Advance` row, as the analyst proposed, could not produce their own §4 table: with no
`billing_period` it would land in `otherCharges()` and settle nothing — the exact bug in §1, rebuilt.

**One migration is still needed**, but only to widen the enum? No — verified against
`2026_07_24_000003_add_manual_recording_to_payments_table`, `Monthly` and `Deposit` are both already
members. **This change ships with zero migrations.**

---

## 3. Allocation algorithm

Authoritative on the server; mirrored in Alpine purely for live display.

Inputs: `amountReceived`, `rent` (`agreed_monthly_rent ?? unit->rental_fee`),
`deposit` (`unit->security_deposit ?? 0`), `moveInMonth` (`startOfMonth` of `move_in_date`).

```
requiredMoveIn = rent + deposit

1. depositSlice = min(remaining, deposit)          -> Deposit row
2. loop month = moveInMonth, moveInMonth+1, ... while remaining > 0:
       slice = min(remaining, rent)                -> Monthly row, billing_period = month
3. rows with amount 0 are never written
```

**Deposit is satisfied first.** A shortfall then surfaces as an unpaid *rent* balance, which the
ledger keeps showing every month until it is settled. A half-paid `Deposit` row would instead sit in
"Deposits & other payments" looking like a completed deposit unless you manually compare it against
the unit.

Worked examples (rent ₱2,000, deposit ₱2,000, move-in Aug 2026):

| Received | Rows written |
|---|---|
| ₱4,000 | `Deposit ₱2,000` · `Monthly ₱2,000` (Aug) |
| ₱8,000 | `Deposit ₱2,000` · `Monthly ₱2,000` (Aug) · `Monthly ₱2,000` (Sep) · `Monthly ₱2,000` (Oct) |
| ₱7,000 | `Deposit ₱2,000` · `Monthly ₱2,000` (Aug) · `Monthly ₱2,000` (Sep) · `Monthly ₱1,000` (Oct, partial) |
| ₱3,000 | `Deposit ₱2,000` · `Monthly ₱1,000` (Aug, partial) |

Every row carries `status = 'Paid'`, `recorded_by = landlord`, and the same
`payment_method` / `paid_at` / `reference_no` as entered — they are one collection event, split by
what it was for.

---

## 4. `RentLedger` changes

`lastBillablePeriod()` currently caps at `now()->startOfMonth()` on purpose ("rent that hasn't come
due yet isn't something a landlord is chasing"). That stays true for *unpaid* months. It must not
stay true for months the tenant has already paid for.

1. **Extend the window forward** to the latest `billing_period` among settled `Monthly` payments,
   when that is beyond the existing end. A tenancy with no advance payment gains no periods and
   behaves exactly as today.
2. **Each period gains `'is_future' => bool`** — true when the period's month is after the current
   month.
3. **`summary()` excludes future periods** from `outstanding`, `overdueCount`, `overdueAmount` and
   `nextDue`. This is what keeps `Landlord\PaymentController::index` honest: you cannot be *behind*
   on November's rent in August, and the collections page exists to answer "who do I chase".
   Adds `prepaidCount` and `prepaidThrough` for display.
4. **`unsettledPeriods()` excludes future periods.** It feeds
   `Tenant\PaymentController` / `Api\Tenant\PaymentController` (`->first()` is what the tenant pays
   online) and the landlord's record-payment modal. "Unsettled" must keep meaning "owed now".

**Verify, don't assume:** `ProcessRentReminders` reads the ledger to find the oldest unpaid period.
Confirm it goes through `unsettledPeriods()` (or the same future-exclusion) before this ships — a
prepaid tenancy must not start receiving rent reminders for a month it has already paid, and a
future partial must not trigger one early. This is a nightly job that notifies real people; it gets
checked, not guessed.

---

## 5. Files

**New**
- `app/Support/MoveInPaymentBreakdown.php` — pure value object implementing §3. No DB, no request
  state; takes the four inputs and returns the required total, the shortfall, and the ordered rows
  to write. One place the web controller, the API controller and the test fixtures all agree on.
- `app/Http/Controllers/Concerns/RecordsMoveInPayments.php` — the trait both walk-in controllers use
  to turn a breakdown into `Payment` rows inside the caller's existing transaction. The two
  controllers are already near-duplicates; this stops the split logic being written twice.

**Modified**
- `app/Services/RentLedger.php` — §4.
- `app/Http/Requests/Landlord/StoreWalkInTenantRequest.php`
  - `reference_no` → `required_with:initial_amount` **unless** `payment_method` is `Cash`; forced to
    `null` when Cash in `prepareForValidation()`.
  - Drop `initial_type` entirely (see §6).
- `app/Http/Controllers/Landlord/WalkInTenantController.php` — `recordInitialPayment()` becomes the
  trait call. Stays inside the existing `DB::transaction()` + `lockForUpdate()`.
- `app/Http/Controllers/Api/Landlord/WalkInTenantController.php` — same, same transaction.
- `resources/views/landlord/tenants/walk-in/create.blade.php` — §6.
- `resources/views/landlord/tenancies/show.blade.php` — `Paid · Advance` pill on future paid
  periods; a line under the ledger header reading "Paid in advance through Oct 2026".
- `resources/views/tenant/tenancy/show.blade.php` — same two, tenant wording.
- `app/Console/Commands/WalkInScenarios.php` — one new scenario: a walk-in with two months prepaid,
  plus one short-paid, so both new ledger states are browsable.

**Docs** (per `keep-context-docs-updated`)
- `context/SCHEMA.md` — under `payments`: advance rent is future-dated `Monthly` rows, not a type.
- `context/ARCHITECTURE.md` — decision-log entry: why the ledger window extends forward only over
  paid months, and why `outstanding` excludes future periods.

---

## 6. The form (`walk-in/create.blade.php`)

The unit payload in the `@php` block gains `deposit` (`(float) $unit->security_deposit`) so Alpine
can compute the breakdown without a round-trip.

**Removed:** the "What it was for" select (`initial_type`). Once the system allocates rent /
deposit / advance from one amount, asking the landlord to also label the lump is asking the same
question twice and lets the two answers disagree.

**Added above the Amount field** — a read-only requirement panel, visible as soon as a unit is
picked:

```
Monthly rent                    ₱2,000.00
Security deposit                ₱2,000.00
─────────────────────────────────────────
Required move-in payment        ₱4,000.00
```

**Added below the Amount field** — a live allocation, once an amount is entered:

```
Amount received                 ₱8,000.00
  Rent — Aug 2026               ₱2,000.00
  Security deposit              ₱2,000.00
  Advance rent (Sep, Oct)       ₱4,000.00
```

**Short payment warns, never blocks** (decided):

```
⚠ Short by ₱1,000.00 — this will show as a partial Aug 2026 period.
```

Amber `#FBBF24` treatment per DESIGN.md §3, matching the existing "unit will be marked Occupied"
notice in the summary rail. The form still submits. The `data-confirm` message on the form gains
the shortfall sentence so it appears in the confirm modal too — the last screen before the write.

**Reference no.** is `x-show`n only when `payment_method !== 'Cash'`, with the required asterisk and
`aria-label` following it. Needs `payment_method` bound into the Alpine root; `<x-styled-select>`
already supports `x-model`, as `rent_due_day` in this same form does.

The summary rail's "Collected now" row gains the same three-way split so the allocation is visible
at the point of submission, not only mid-form.

---

## 7. Manual test checklist

Fixtures: `php artisan walkin:scenarios` (additive, `--clean` teardown, refuses in production).
Servers are already up — `127.0.0.1:8000`, Reverb on `8080`.

1. **Exact payment.** Rent 2000 / deposit 2000, pay ₱4,000 → tenancy page: Aug 2026 reads **Paid**
   (this is the §1 bug — it reads Overdue today), deposit appears under "Deposits & other payments".
2. **Overpayment.** Pay ₱8,000 → Sep and Oct render as `Paid · Advance`; header reads "Paid in
   advance through Oct 2026".
3. **Overpayment, non-multiple.** Pay ₱7,000 → Oct shows partial; **check `landlord/payments`
   still shows ₱0 outstanding and ₱0 overdue** for this tenancy. This is the regression that
   matters most.
4. **Short payment.** Pay ₱3,000 → amber warning in form and confirm modal, save succeeds, Aug 2026
   reads partial with a ₱1,000 balance, and the tenancy *does* appear on the collections page.
5. **No payment.** Leave "Record one now" unticked → saves exactly as today, no rows written.
6. **Cash.** Reference field hidden, saves with `reference_no = NULL`.
7. **GCash.** Reference field shown and required; submitting blank shows an inline `@error` beside
   the field (not the flash modal — RULES.md § Error Handling).
8. **Pre-existing walk-in** (from before this change) → unchanged, still shows its lump `Initial`
   row and an unpaid move-in month. Expected; see §8.
9. **Tenant side.** Log in as a *platform* tenant with a prepaid month and confirm
   `tenant/tenancy/show` shows the advance months and offers no online payment for them.
10. **375px.** The requirement panel and allocation list are Landlord surfaces — validate at 375px
    first per DESIGN.md §0b, then scale up.

---

## 8. Deliberately not doing

- **Blocking the move-in when payment is short or absent** (analyst §5, §7). Walk-in exists to
  record a tenancy that already happened offline — the controller's own docblock says so. Refusing
  the record because the landlord collected ₱3,000, or took the deposit last week and rent today,
  makes the app refuse to record reality; the landlord types a fake number to get past the gate and
  the ledger becomes fiction. It is also bypassable by setting move-in to yesterday, so it is not a
  control. Replaced with the §6 warning.
- **Removing "Other Required Charges"** (analyst, opening line). No such field exists in this
  codebase.
- **Treating the security deposit as conditional** ("if the selected unit requires one"). It has
  been application-layer required since Aug 20 2026 and backfilled to one month's rent, so the
  conditional is dead. `?? 0` is kept only as a guard, not as a supported state.
- **Backfilling existing lump `Initial` rows** (decided). An old row does not record how much was
  rent versus deposit; any split would be invented data on a money table. New walk-ins get the
  correct shape; old ones are left exactly as they are.
- **Rendering unpaid future months.** The window only ever extends over months that carry a
  payment. Speculative future debt stays out of the ledger, out of `outstanding`, and out of the
  collections page.

---

## 9. Order of work

Per RULES.md § Build Order, confirming output at each step.

1. `MoveInPaymentBreakdown` + `RecordsMoveInPayments` trait.
2. `RentLedger` window/`is_future`/`summary`/`unsettledPeriods`; verify `ProcessRentReminders`.
3. Both walk-in controllers + the Form Request.
4. `walk-in/create.blade.php`.
5. Both tenancy show views.
6. `WalkInScenarios` fixtures.
7. Context docs.

No migrations. No commit until manual verification in the browser (`test-before-commit`).
