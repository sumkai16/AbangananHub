<?php

namespace App\Support;

use App\Models\User;

/**
 * The landlord's acceptance of the money-handling terms (see <x-payment-consent>).
 *
 * Asked once per account and payment route: the wording differs between online (held in escrow) and
 * direct payment, so switching the route asks again. Every agreement still records its own
 * `landlord_tc_accepted_at`, which is when the standing acceptance applied to that agreement.
 */
class LandlordTerms
{
    public static function mode(): string
    {
        return Payments::online() ? 'online' : 'direct';
    }

    public static function needed(User $landlord): bool
    {
        return $landlord->landlord_terms_accepted_at === null
            || $landlord->landlord_terms_payment_mode !== self::mode();
    }

    public static function record(User $landlord): void
    {
        $landlord->forceFill([
            'landlord_terms_accepted_at' => now(),
            'landlord_terms_payment_mode' => self::mode(),
        ])->save();
    }
}
