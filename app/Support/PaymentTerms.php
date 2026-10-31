<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Reservation;
use Illuminate\Http\Request;

/**
 * The tick-box a tenant must check before paying or reporting a payment (<x-payment-agree>). It is checked on the
 * server, not only in the browser, and every acceptance is written to the audit log with the route it applied to, so
 * "they agreed to this" can be shown if a payment is disputed. Web only: the mobile API has its own flow.
 */
class PaymentTerms
{
    /** Routes the wording differs for. */
    public const ROUTES = ['online', 'qr', 'cash'];

    /** Refuse the request unless the box was ticked. */
    public static function require(Request $request): void
    {
        $request->validate(
            ['accept_terms' => ['accepted']],
            ['accept_terms.accepted' => 'Tick the box to confirm you understand how this payment works.'],
        );
    }

    /** Leave a trace of what was agreed to, for which reservation and by which route. */
    public static function record(Reservation $reservation, string $route, string $what): void
    {
        AuditLog::record(
            'payment.terms_accepted',
            "Tenant agreed to the {$route} payment terms ({$what}) on reservation #{$reservation->reservation_id}",
            $reservation,
            null,
            ['route' => $route, 'what' => $what, 'terms_version' => config('rentals.terms_version')],
        );
    }
}
