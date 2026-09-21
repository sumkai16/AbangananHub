# Rent & Payments page redesign (Phase A+B)

## Context

The analyst's spec redesigns the landlord "Rent & Payments" page from an all-time/lifetime summary into a current-month, action-first dashboard: four month-scoped cards, a table with real per-tenant status derived from billing-period math (not a vague "Standing" label), urgency-based sorting, and a richer Open Ledger with a genuine payment-transaction history. The full spec also asks for two much larger, financially-sensitive subsystems — automatic multi-period advance-payment allocation for *ongoing* rent payments, and void/reverse instead of hard-delete — which the user explicitly deferred to a later round after reviewing the scope. **This plan covers only the dashboard/table redesign (A) and the Open Ledger transaction history (B).**

Three research passes (verified, not assumed) confirmed the current implementation and — crucially — that most of the hard status logic this spec asks for **already exists**:

- **Current page**: `app/Http/Controllers/Landlord/PaymentController.php:40-101` + `resources/views/landlord/payments/index.blade.php`. Loads all Occupied/Completed reservations with `payments` eager-loaded (one query, no N+1), then builds one `RentLedger::for($reservation)` per row — already the right, reusable pattern for a per-tenant table.
- **`app/Services/RentLedger.php`** already computes, per tenancy: `periods()` (one row per month: `due_on`, `expected`, `paid`, `balance`, `status` of paid/overdue/partial/due) and `summary()` (`outstanding`, `overdueCount`, `overdueAmount`, `nextDue`, `oldestOverdue`, `prepaidCount`, `prepaidThrough`, `monthlyRent`, `collected`). This already covers nearly every number the spec's cards and table need — no changes to `RentLedger` itself are needed, only new code that reads its output.
- **Open Ledger** (`TenancyController::show` → `landlord/tenancies/show.blade.php`) already shows a billing-schedule table (`Period | Due on | Expected | Paid | Balance | Status`) — this already matches spec section 14 almost exactly. It also has an "Other charges" table (non-Monthly payments only) that the spec's real Payment Transaction History table should replace, since the new table is a strict superset.
- **Payments are never hard-deleted** anywhere in the codebase today (confirmed via full grep) — so this redesign doesn't need to worry about missing historical rows.

## Approach

### A. Dashboard cards + table redesign

**`app/Http/Controllers/Landlord/PaymentController.php` — `index()`**

Reuse the existing loop that already builds one `RentLedger` per reservation — no new queries. For each reservation, additionally:

1. Find that reservation's **current-month period** from `$ledger->periods()`.
2. Accumulate portfolio totals: `dueThisMonth`, `collectedThisMonth`, `outstanding`/`overdueAmount` (unchanged from `summary()`), `overdueTenancies` count.
3. Compute a single **table status** per row via a new `RentLedger::statusLabel()` method, in priority order: Overdue → Partial → Paid Ahead → Paid → Upcoming (matches spec §10/§12).
4. Compute the row's **Due Date** via a new `RentLedger::nextDueDate()` method — for a Paid Ahead tenant this resolves to the real next unpaid due date, never "nothing due" on a ₱0 balance (spec §9).
5. **Sort** rows by `[statusRank, dueDate]` — Overdue(0) → Partial(1) → Upcoming(2) → Paid(3) → Paid Ahead(4), oldest-due-first within Overdue (spec §12).

**`resources/views/landlord/payments/index.blade.php`**

- Four cards: Due This Month, Collected This Month (with % collected, divide-by-zero guarded), Outstanding, Overdue.
- Table columns: Tenant | Unit | Monthly Rent | Due Date | Paid | Balance | Status | Action — 5-state Status badge replaces the old 3-state Standing pill.
- Filter bar: status filter options become all/overdue/partial/upcoming/paid/paid_ahead + due_this_month (spec §13).

### B. Payment Transaction History in Open Ledger

**`app/Http/Controllers/Landlord/TenancyController.php` — `show()`**

Added a `transactions` collection: the reservation's already-loaded `payments`, sorted by `paid_at` descending, each mapped to `{ payment, applied_to }` — `applied_to` is `"{billing_period month} Rent"` for Monthly payments, otherwise the payment type. Also added `nextDueDate` from `RentLedger::nextDueDate()`.

**`resources/views/landlord/tenancies/show.blade.php`**

- Replaced the "Other charges" table with a **Payment Transactions** table (Date | Reference | Amount | Method | Applied To) — a strict superset of the old table.
- Added Due Day and Next Due Date to the side Unit card's tenant-info `<dl>`.
- Billing-schedule table (`Period | Due on | Expected | Paid | Balance | Status`) left as-is — already matched spec §14.

## Deferred (recorded for the follow-up round, not built now)

- **C — Automatic advance-payment allocation for ongoing rent payments**: generalize the existing `MoveInPaymentBreakdown` (used today only at walk-in move-in) so recording a payment against an *already-occupied* tenancy can also split one lump sum across multiple future billing periods, instead of today's one-payment-to-one-`billing_period` limit in `record-payment-modal.blade.php` / `PaymentController::store()`.
- **D — Void/reverse a payment**: user confirmed the design direction — reuse the existing `status = 'Refunded'` enum value (no migration needed) with the reason appended to the existing `payment_notes` field, rather than adding dedicated `voided_at`/`voided_by`/`void_reason` columns. Ledger sums (`RentLedger`) will need to exclude `Refunded` from "collected"/"paid" the same way it already excludes `Pending`.

## Verification

- `php artisan view:cache` after each batch of Blade edits — passed.
- `php -l` on all touched PHP files (`PaymentController.php`, `TenancyController.php`, `RentLedger.php`) — all clean.
- `curl` the payments and tenancy-show routes — both return `302` (login redirect), consistent with every other auth-gated landlord route checked this session.
- No browser automation available in this sandbox — the actual card numbers, sort order, and status badges need a real visual check by the user against known seed data.

## Status: Phase A and B complete, verified, ready for user visual review. Phase C and D untouched, awaiting explicit go-ahead.
