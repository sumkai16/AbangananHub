<?php

use App\Models\AiSearchLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Times below run on config('rentals.timezone') (Manila); app.timezone stays UTC.
// Move-in escrow clocks — backfills turnover deadlines, sends confirmation
// reminders, and processes both clock expiries. Runs before the occupancy
// snapshot so units it marks Occupied are counted the same night.
Schedule::command('reservations:process-move-in-deadlines')->dailyAt('23:00')->timezone(config('rentals.timezone'));

// Daily occupancy snapshot. Deliberately has no reader: the trend chart it fed
// was removed in July 2026, but occupancy history cannot be reconstructed after
// the fact, so the write continues to keep that option open (see ARCHITECTURE.md).
// Runs via cron/Supervisor on the VPS; locally use `php artisan schedule:work`.
Schedule::command('occupancy:snapshot')->dailyAt('23:55')->timezone(config('rentals.timezone'));

// Rent reminders — notifies landlords (and platform tenants) about upcoming
// and overdue monthly rent, read off the ledger. Morning delivery reads better
// than the nightly escrow run. Idempotent via the rent_reminders guard.
Schedule::command('reservations:process-rent-reminders')->dailyAt('08:00')->timezone(config('rentals.timezone'));

// AI search logs are kept 30 days (App\Models\AiSearchLog::prunable()).
Schedule::command('model:prune', ['--model' => [AiSearchLog::class]])->dailyAt('03:00')->timezone(config('rentals.timezone'));
