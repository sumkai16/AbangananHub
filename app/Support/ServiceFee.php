<?php

namespace App\Support;

/**
 * The fee a tenant pays on top of the rent for paying online. The one place the formula lives, so a checkout, its
 * receipt and the admin's setting can never disagree. Both parts come from System Settings and default to 0, in
 * which case there is no fee. Only the rent is charged a percentage: a deposit is the tenant's money coming back
 * to them in the end, and PayMongo's cost for it is small enough not to matter.
 */
class ServiceFee
{
    public static function for(float $rent): float
    {
        $percent = (float) config('rentals.online_payment_fee_percent', 0);
        $fixed = (float) config('rentals.online_payment_fee_fixed', 0);

        if ($rent <= 0 || ($percent <= 0 && $fixed <= 0)) {
            return 0.0;
        }

        return round($rent * $percent / 100 + $fixed, 2);
    }
}
