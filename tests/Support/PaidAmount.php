<?php

namespace Tests\Support;

use App\Models\Payment;

/**
 * The amount, in centavos, PayMongo reports as taken for a checkout session: what a real paid payload carries
 * at `payments[].attributes.amount`. The app refuses to settle a payload without it, so paid fixtures need it.
 */
class PaidAmount
{
    public static function centavos(string $sessionId): int
    {
        $payment = Payment::where('paymongo_checkout_session_id', $sessionId)->orderBy('payment_id')->first();

        // No row for the session (an unknown-session test): nothing to match, so any number will do.
        return $payment ? Payment::toCentavos($payment->checkoutTotal()) : 0;
    }
}
