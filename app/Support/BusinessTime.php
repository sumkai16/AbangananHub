<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "Today" as a landlord or tenant in the Philippines means it. app.timezone stays UTC so stored
 * timestamps do not shift, which makes plain today()/now() a day behind from midnight to 08:00 Manila.
 */
class BusinessTime
{
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    public static function now(): Carbon
    {
        return now(config('rentals.timezone'));
    }
}
