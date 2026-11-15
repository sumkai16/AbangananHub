# Unpaid rent of an ended tenant: keep collecting, then write it off after 90 days

Status: implemented, pending manual verification.

## Why
"Stop collecting" (Nov 12) made the landlord decide by hand, so owed rent either stayed on Still owed forever or
disappeared the moment a button was pressed. Better: keep collecting by default, and let the balance move to
**Written off** by itself after a set time, with a warning first.

## Rules
- Clock: `target_move_out_date` + `config('rentals.write_off_after_days')` (env `RENT_WRITE_OFF_AFTER_DAYS`, default 90).
  One clock per rental, not one per unpaid month.
- Until then the balance counts as owed (Still owed, dashboard, badges). Reminders for ended leases stay off (unchanged).
- `config('rentals.write_off_warning_days')` (7) days before, the ledger says "Will be written off on <date>".
- Day N: the daily job `reservations:write-off-overdue-rent` (09:00 Manila) writes it off (`rent_write_off_source = auto`),
  records an audit log and notifies the landlord. Same effect as before: `RentLedger::summary()` reports 0 owed.
- Manual: **Write off now** replaces Stop collecting (`source = manual`).
- **Collect again** (was Resume collecting) clears the write-off and sets `rent_write_off_resumed_at`, so the job never
  writes that rental off a second time. The landlord records the payment as usual. There is no separate "Recovered" state.
- Deposit: shown next to what is owed. Loss = written-off amount minus deposit still held, never below 0. Nothing moves.

## Files
- Migration `2026_11_15_000100_add_write_off_source_to_reservations` (two nullable columns).
- `App\Services\RentWriteOff` (dueOn, apply, resume, loss), used by `TenancyController` and the command.
- `App\Console\Commands\WriteOffOverdueRent`, scheduled in `routes/console.php`.
- Views: `payments/ledger.blade.php`, `payments/partials/_ended-actions.blade.php`, `payments/index.blade.php`; labels in
  `PaymentController` and the ledger.
- Tests: `tests/Feature/Payments/RentWriteOffTest.php`; `RecordPaymentTest` keeps working (routes unchanged).

## Manual check
1. Ended rental, owed rent, move-out under 83 days ago: ledger says "Collecting", no warning.
2. 83 to 89 days: "Will be written off on <date>".
3. Run `php artisan reservations:write-off-overdue-rent --date=<move-out + 90>`: it shows Written off with the amount, the
   landlord gets a notification, Still owed no longer lists it.
4. Collect again, then run the command again: it stays collecting.
