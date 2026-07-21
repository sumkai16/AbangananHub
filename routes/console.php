<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily occupancy snapshot — feeds the occupancy trend chart.
// Runs via cron/Supervisor on the VPS; locally use `php artisan schedule:work`.
Schedule::command('occupancy:snapshot')->dailyAt('23:55');
