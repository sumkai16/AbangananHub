<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Move-in escrow clocks — backfills turnover deadlines, sends confirmation
// reminders, and processes both clock expiries. Runs before the occupancy
// snapshot so units it marks Occupied are counted the same night.
Schedule::command('reservations:process-move-in-deadlines')->dailyAt('23:00');

// Daily occupancy snapshot — feeds the occupancy trend chart.
// Runs via cron/Supervisor on the VPS; locally use `php artisan schedule:work`.
Schedule::command('occupancy:snapshot')->dailyAt('23:55');

// Rent reminders — notifies landlords (and platform tenants) about upcoming
// and overdue monthly rent, read off the ledger. Morning delivery reads better
// than the nightly escrow run. Idempotent via the rent_reminders guard.
Schedule::command('reservations:process-rent-reminders')->dailyAt('08:00');
