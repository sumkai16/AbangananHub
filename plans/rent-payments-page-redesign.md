# Rent & Payments page redesign

Source: `docs/NEEDS UPDATE.pdf` (analyst spec, 19 sections) plus the two mockup screenshots the
analyst supplied on Aug 30 2026 — the four-card summary and the full page layout.

Target: `landlord/payments/index` and, for a few items, `landlord/tenancies/show` (the "Open Ledger"
destination).

**Read `§2 Decisions locked` first.** Three of the spec's numbers are ambiguous, the example figures
work under either reading, and picking the wrong one is not something the UI will reveal.

---

## 1. The one-line summary

The page today reports **all-time collections**. The analyst wants it to report **this month's
collection cycle**, with the tenants who need chasing at the top. The billing engine to do this
already exists (`RentLedger`); what is missing is a month-scoped read of it, four renamed columns,
a five-value status, and a tiered sort.

**No schema change is needed.** §17 of the spec assumes `billing_periods` and `payment_allocations`
tables. `RentLedger` already derives periods from move-in date + due day and settles them from
`payments.billing_period` — see its class docblock for why a stored schedule was rejected (it goes
stale the moment rent, the due day, or the move-out date changes).

---

## 2. Decisions locked

Agreed with Axcee on Aug 30 2026. These resolve ambiguities in the spec, not preferences.

### 2a. Advance payments count toward the month they settle, not the month cash arrived

A tenant paying ₱30,000 in August covering Aug/Sep/Oct adds **₱10,000** to August's
`COLLECTED THIS MONTH`, ₱10,000 to September's, ₱10,000 to October's.

Counting cash-in-hand instead would print "300% collected" and make the card swing wildly. Everything
in `RentLedger` is already keyed on `payments.billing_period`, so this is the free option.

### 2b. `DUE THIS MONTH` is this month's scheduled rent only — no carried-over arrears

Three reasons:

1. The card has to mean what it says.
2. It keeps the four cards non-overlapping: Due = expected, Collected = received, Total Unpaid =
   everything still owed, Overdue = the late part of that.
3. `collected ÷ due` is only meaningful over one month. Fold arrears into the denominator and a
   landlord who collects 100% of this month still reads "62%", and the number can never recover.

### 2c. `TOTAL UNPAID` and `OVERDUE` cover every unpaid month; the table row is scoped to the oldest unpaid month

The analyst suggested current-month-only everywhere. We diverge on the cards, and here is why: the
spec's own closing principle is that the page must answer *"How much is still unpaid?"* **without
opening a ledger**. A tenant three months behind whose total counts one month understates the debt
on the exact card that exists to prevent surprises. §12 also asks for "2 months behind" to sort
above "1 month behind", which only works if the page knows about arrears at all.

| Element | Scope |
|---|---|
| `DUE THIS MONTH` | Current month's scheduled rent |
| `COLLECTED THIS MONTH` | Rent settled against the current month |
| `TOTAL UNPAID` | **All** unpaid months to date |
| `OVERDUE` | **All** unpaid months past their due date — a strict subset of Total Unpaid |
| Table → Due Date | The **oldest unpaid** month's due date |
| Table → Paid | Paid against **that same month** |
| Table → Balance | **Total** unpaid across all months |

The row stays coherent: *"owes ₱30,000, the oldest piece was due Aug 5."* When Balance spans more
than one month, a grey `3 months` sub-line sits under it so the figure never looks like a
miscalculation next to Monthly Rent.

§11 (do not double-count) still holds — Overdue is computed from the same period set as Total Unpaid,
never added to it.

### 2d. Deposits and utilities are excluded from `COLLECTED THIS MONTH`

Rent only. The percentage is `collected ÷ rent due`; folding in an ₱18,000 security deposit would
print 140% in a move-in month. This is a change from today's `collected` figure, which deliberately
includes deposits because it answers a different question ("what has this tenant given me?"). Both
values will exist; they are not interchangeable.

### 2e. "Outstanding" is retired as a label — landlords see `TOTAL UNPAID`, tenants see `BALANCE`

This diverges from the spec, which keeps "Outstanding" in its §19 terminology table. Three reasons
to change it anyway — and it is the same kind of change §19 is already making elsewhere
(Standing → Status, Behind → Overdue):

1. **It is finance jargon.** A small landlord reading a phone should not have to decode the label on
   a headline number.
2. **It collides with `OVERDUE` sitting right beside it.** Same first letter, similar length, both
   rendered 11px uppercase — and they are the two cards most easily confused, because one contains
   the other.
3. **It does not say "all months."** That is the whole distinction §2c just locked in: cards 1 and 2
   are this month, card 3 is everything. "Total" carries that; "Outstanding" carries nothing.

Tenants get `BALANCE` instead. "Total Unpaid" aimed at the person who owes it reads as an accusation,
"Balance" is what their e-wallet and their utility bill already call it, and a tenant has one
tenancy, so "total" adds nothing.

**Scope: labels only.** `RentLedger::summary()['outstanding']` keeps its name. The key has 13
consumers including the mobile API, and renaming it is pure churn with drift risk for no user
benefit. Five user-facing strings change:

| File | Change |
|---|---|
| `landlord/payments/index.blade.php` | Card label → `Total Unpaid`; sub-line → `across 5 tenants` |
| `landlord/payments/index.blade.php` | Table header → `Balance` (already required by spec §19) |
| `landlord/tenancies/show.blade.php` | Summary tile → `Total Unpaid` |
| `tenant/tenancy/show.blade.php` | Summary tile → `Balance` |
| `Landlord\PaymentController::export()` | CSV header → `Total Unpaid` |

Worth telling the analyst, since it departs from their §19 table.

---

## 3. What already works — do not rebuild

| Spec § | Feature | Where |
|---|---|---|
| 8 | Advance rent settles future months, window extends forward | `RentLedger::lastBillablePeriod()`, `latestSettledMonthlyPeriod()` |
| 8 | One walk-in lump auto-splits into deposit + rent + advance months | `app/Support/MoveInPaymentBreakdown.php` |
| 9 | Next unpaid obligation computed | `RentLedger::summary()['nextDue']` (not displayed anywhere yet) |
| 10 | Per-month `paid / partial / overdue / due` with a grace window | `RentLedger::periodStatus()` |
| 11 | The unpaid total never double-counts Overdue | `RentLedger::summary()` |
| 14 | Billing-period ledger table (Period, Due Date, Amount Due, Paid, Balance, Status) | `landlord/tenancies/show.blade.php` |
| 17 | Driven by real property → unit → tenancy → payment data | `Landlord\PaymentController::index()` |
| 18 | Payments cannot be deleted | No destroy route exists |

Future-month colour and wording rules are already codified in `context/DESIGN.md` §6n. **Every new
surface added below must obey it** — branch on `is_future` before choosing a colour or a verb.

---

## 4. Data layer — `app/Services/RentLedger.php`

`RentLedger::summary()` has **13 consumers** (two web controllers, four API controllers,
`ProfileController`, `TenantController`, `ProcessRentReminders`, both tenancy pages). Adding keys is
safe; changing what an existing key means is not.

> **Rule for this phase: add new keys only. Do not touch `collected`, `outstanding`, `overdueAmount`,
> `nextDue`, `prepaidThrough` or any existing behaviour.**

### 4a. New keys on `summary()`

| Key | Meaning |
|---|---|
| `dueThisMonth` | `expected` of the current calendar month's period, else `0.0` if the tenancy is not billed this month (ended, or starts later) |
| `collectedThisMonth` | `paid` of the current calendar month's period. Monthly rent only — `otherCharges()` is not consulted |
| `paymentStatus` | One of `overdue / partial / upcoming / paid / paid_ahead` — the five-value row status the spec asks for |
| `nextDueDate` | A `Carbon`, always populated for an active tenancy (see 4c) |
| `oldestUnpaid` | The period whose Due Date, Paid and Balance the table row shows — `oldestOverdue ?? nextDue ?? current month` |
| `unpaidMonthCount` | How many months the Balance figure spans, for the `3 months` sub-line |

### 4b. `paymentStatus` derivation

Computed from the existing period collection, in this order — first match wins:

1. `overdue` — any billed period with `status === 'overdue'`
2. `partial` — any billed period with `status === 'partial'`
3. `paid_ahead` — `prepaidCount > 0` (at least one *fully covered* future month)
4. `paid` — every billed period settled, nothing prepaid
5. `upcoming` — nothing overdue or partial, current month unpaid and not yet due

Order matters: overdue outranks paid_ahead, because a tenant who prepaid December while still owing
August is behind, not ahead.

### 4c. `nextDueDate` for a paid-ahead tenant — the one genuinely new calculation

Today `nextDue` searches `$billed`, which excludes future periods. A tenant prepaid through October
has no unpaid billed period, so `nextDue` is `null` and the spec's `Next Due: November 5` cannot be
rendered — the November period is not in `periods()` at all, because the window stops at
`prepaidThrough`.

Fix: when `nextDue` is null and `prepaidThrough` is set, project one month past `prepaidThrough` at
`rentDueDay()`. Do **not** widen `periods()` to include it — that would put a speculative unpaid row
into the ledger table and into `outstanding`, which is exactly what `lastBillablePeriod()`'s comment
warns against.

Guard for an ended tenancy: if `target_move_out_date` has passed, `nextDueDate` is `null` and the
page shows `—`.

---

## 5. Controller — `app/Http/Controllers/Landlord/PaymentController.php`

### 5a. `index()` totals

```
dueThisMonth       = Σ rows.summary.dueThisMonth
collectedThisMonth = Σ rows.summary.collectedThisMonth
outstanding        = Σ rows.summary.outstanding          (unchanged)
overdue            = Σ rows.summary.overdueAmount        (unchanged)

duePaymentCount    = rows where dueThisMonth > 0                        → "8 payments due"
collectedPercent   = dueThisMonth > 0 ? round(collected/due*100) : null → "69% collected"
unpaidCount        = rows where outstanding > 0                         → "across 5 tenants"
overdueCount       = rows where paymentStatus === 'overdue'             → "2 overdue"
```

`unpaidCount` counts tenants with **any** unpaid balance, not just this month's — it labels the
Total Unpaid card, so its scope has to match the figure above it (§2c).

`collectedPercent === null` renders `No dues this month` (§2, Card 2).

The all-time `collected` total is dropped from this page. It stays available on the tenancy page,
where "what has this tenant given me?" is the right question.

### 5b. Tiered sort, replacing `sortByDesc(overdueAmount)`

```
overdue(0) → partial(1) → upcoming(2) → paid(3) → paid_ahead(4)
tie-break within a tier: oldest due date first
```

Sorting stays in PHP after the ledger runs, as it does today — status is derived, not a column.

### 5c. Filter

`$filters` becomes: `all / overdue / partial / upcoming / paid / paid_ahead`, label
`All Payment Status`, matched against `paymentStatus`. The optional `due_this_month` filter
(`dueThisMonth > 0`) is worth adding because today's "Due this month" option is **mislabelled** — it
maps to `standing === 'due'`, which means *has any balance at all*, not *due this month*.

`standingFor()` is deleted. Nothing else calls it.

### 5d. `export()`

Headers change with the table: `Paid` and `Total Unpaid` replace `Collected` and `Outstanding`, and
`Due Date` and `Status` are added. Same figures the table shows, so a landlord's CSV reconciles with
their screen.

---

## 6. View — `resources/views/landlord/payments/index.blade.php`

### 6a. Cards

Four `<x-stat-card>`, in spec order: Due This Month, Collected This Month, **Total Unpaid** (§2e),
Overdue. The existing `Behind` card is removed — its count becomes the Overdue card's `sub` line,
which is what the mockup shows.

`<x-stat-card>` already supports `percent` + `barColor`, so Card 2 gets a real progress bar under the
figure for free.

### 6b. Table

`Tenant | Unit | Monthly Rent | Due Date | Paid | Balance | Status | Open Ledger`

- Status is a coloured dot + label, per the mockup. Palette follows `DESIGN.md` §6n's existing
  families: red overdue, amber partial, emerald paid, slate upcoming, **teal** paid-ahead — never
  amber or red for paid-ahead, which is credit, not debt.
- `Open Ledger` becomes a plain text link, not the outlined pill button it is today.
- Balance carries the `3 months` sub-line when `unpaidMonthCount > 1`.
- Subtitle changes to *"Rent collection across all occupied units, with the most urgent payments
  shown first."*

### 6c. Mobile — needs a decision before this phase starts

`CLAUDE.md` → Device priority makes Landlord surfaces mobile-first at 375px. The table is
`min-w-[900px]` behind a horizontal scroll today, and we are **adding a ninth column**, which makes
it worse.

Recommendation: stacked cards below `sm`, table from `sm` up. No landlord index page does this yet
(they all scroll horizontally), so it sets a precedent and belongs in `DESIGN.md` §6d if adopted.

**Do not start Phase 3 until this is settled** — it changes the markup, not just the styling.

---

## 7. Advance payments for an existing tenant (§8, general case)

The gap: `MoveInPaymentBreakdown` splits the walk-in **move-in** lump, but the record-payment modal
takes one amount and one month. An occupied tenant handing over ₱30,000 against ₱10,000 rent gets
recorded entirely against August — August's balance goes to −₱20,000 and September and October stay
unpaid. Today the landlord must enter three separate payments and know to do so.

This is the only item on the list with a money-correctness consequence rather than a display one.

**Approach:** extract the rent-slicing loop into `app/Support/RentPaymentAllocator.php`, which takes
the unsettled periods (each already carrying its own `balance`) plus the amount, and fills them
oldest-first, continuing at full rent past the last known period. It must fill a **part-paid** first
month by its remaining balance, not by full rent — the case `MoveInPaymentBreakdown::allocate()`
never has to handle, and the reason it cannot simply be reused as-is.

`MoveInPaymentBreakdown` keeps its deposit-first logic and delegates the rent slicing to the new
class, so there is one implementation of the slicing rule rather than two that can drift.

Carry over the `MAX_ADVANCE_MONTHS = 60` ceiling — without it, ₱1,000,000 against ₱500 rent writes
2,000 payment rows from one form submission.

Modal change: when the amount exceeds what the selected month needs, show the split before the
landlord commits (the move-in form already does this in Alpine — mirror it), and write the rows in
one `DB::transaction`.

`context/RULES.md` → Money-Moving Code applies to this whole section.

---

## 8. Ledger page additions (§14, §15)

On `landlord/tenancies/show.blade.php`:

1. **Current payment status** in the unit info block. The pill in the header today is the *tenancy*
   status (Occupied/Completed) — a different thing.
2. **Next Due Date** in the same block.
3. **Payment transactions table for monthly rent.** Rent payments are currently only rolled up per
   period, with methods joined into one string. The spec wants the individual transactions:
   `Date | Reference | Amount | Method | Applied To`. `reference_no` is already stored on every
   payment and displayed nowhere.

Keep the existing billing-period table — the spec wants both, and is explicit that they answer
different questions ("what is owed" vs "what was paid").

---

## 9. Void / reverse a payment (§18) — done, see `plans/void-correct-payments.md`

Nothing can be deleted today, so the risk the analyst is worried about does not exist. What was
missing was a way to **correct** a mistyped payment.

Shipped Aug 31 2026, per the full plan in `plans/void-correct-payments.md`:

- Added `Voided` to the `payments.status` enum, plus `voided_at`, `voided_by`, `void_reason`,
  `void_note`, `replaces_payment_id`.
- `RentLedger::SETTLED_STATUSES` excludes it automatically, so a voided payment stops settling its
  period with no further change to that class.
- The row stays visible on the tenancy ledger under "Voided entries," struck through with its
  reason — the audit trail the spec asks for. A "void and correct" flow lets the landlord
  immediately re-record the right entry, linked back to the one it replaces.
- Landlord-recorded payments only. A PayMongo-settled payment must go through refund, not a
  landlord's assertion that it did not happen — enforced via `Payment::canBeVoided()`.
- Admin gets a `Voided` tab on `admin/payments` and every void writes a `payment.void` audit-log
  row — the first landlord-side actor in that trail.

---

## 10. Build order

Layer by layer, per `RULES.md` → Build Order. Each phase is independently shippable.

| Phase | Scope | Risk |
|---|---|---|
| **1** | `RentLedger` new keys (§4), no callers changed | Low — additive only |
| **2** | Controller totals, sort, filters, export (§5) | Low |
| **3** | Cards + table + mobile decision (§6) | Low, but blocked on §6c |
| **4** | `RentPaymentAllocator` + modal split (§7) | **Highest — writes money rows** |
| **5** | Ledger page: status, next due, transaction list (§8) | Low |
| **6** | Void payment: migration + action (§9) | Medium — schema change |

Phases 1–3 are the whole visible complaint and can land together. Phase 4 is the one to review
carefully. Phases 5–6 are additive and can wait.

---

## 11. Things not to break

- **`summary()`'s existing keys.** 13 consumers, including the tenant-facing tenancy page and the
  mobile API. Adding keys is safe; redefining `collected` is not.
- **`DESIGN.md` §6n.** Any new pill, figure, colour or sentence must branch on `is_future` first. The
  last pass got this wrong on three surfaces at once.
- **The record-payment modal's default period.** It deliberately falls back past future months; a
  default landing on a prepaid month invites a duplicate payment.
- **`unsettledPeriods()`.** Feeds the tenant's online rent payment (`Tenant\PaymentController` takes
  `->first()`). Do not widen it to future months.
- **API parity.** `Api/Landlord/PaymentController::index()` renders the same collections list. If the
  web page gains a five-value status, the API should return it too, or the mobile app drifts.

---

## 12. Manual test checklist

Axcee verifies in-browser. Fixtures via `php artisan walk-in:scenarios` — extend it if these
tenancies do not exist yet.

Set up five tenancies on one property:

| Tenant | Setup | Expected row |
|---|---|---|
| A | ₱10,000 rent, nothing paid, due day 5, today past the 5th | Overdue, Balance ₱10,000, Due Date this month's 5th |
| B | 3 months unpaid | Overdue, Balance ₱30,000 + `3 months`, Due Date = the **oldest** month's 5th, sorts above A |
| C | ₱2,000 paid of ₱3,000 | Partial |
| D | This month fully paid | Paid |
| E | ₱30,000 paid on ₱10,000 rent | **Paid Ahead**, Balance ₱0, `Next Due` = the month after the last covered one |

Then confirm:

- [ ] Card totals: Total Unpaid = A + B + C balances; Overdue ⊆ Total Unpaid, never larger
- [ ] No screen anywhere still says "Outstanding" — landlord cards, both tenancy pages, CSV export
- [ ] `DUE THIS MONTH` counts only this month's rent — B's older arrears must **not** appear in it
- [ ] `COLLECTED THIS MONTH` shows E as ₱10,000, not ₱30,000
- [ ] Percentage renders; with no billed tenancies it reads `No dues this month`, not `0%` or a crash
- [ ] Sort order top to bottom: B, A, C, D, E
- [ ] Each of the five status filters returns exactly its own rows
- [ ] E's row and pill use the **teal** family — no amber, no red, no red balance figure
- [ ] Record ₱25,000 against C: splits across C's remaining ₱1,000 then whole months forward, and
      the modal previews the split before submit
- [ ] CSV export figures match what is on screen
- [ ] 375px: the page is usable without a horizontal scrollbar on the body
- [ ] Tenant-side tenancy page and the mobile API still render unchanged

---

## 13. Out of scope

- `billing_periods` / `payment_allocations` tables (§17). Not needed — see §1.
- Admin payments page. Different audience, untouched.
- Rent reminders (`ProcessRentReminders`). Reads `summary()`; unaffected by additive keys.
