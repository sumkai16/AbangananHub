<?php

namespace App\Support;

/**
 * The one place that answers "can tenants pay online right now?" so the buttons, the checkout
 * controllers (web and API) and the consent copy can never disagree. Admin-editable through
 * System Settings; off by default, in which case tenants pay the landlord directly and report it.
 *
 * Switching it off never strands money already in flight: the webhook, the reconcile fallback, the
 * move-in deadline command, confirmMoveIn and the admin release/payout screens do not read this.
 */
class Payments
{
    public static function online(): bool
    {
        return (bool) config('rentals.online_payments_enabled', false);
    }

    /** The reason shown (and returned as a 409) when someone tries to start a checkout while it is off. */
    public const OFFLINE_MESSAGE = 'Online payment is not available right now. Pay your landlord directly with the QR they shared, then tap "I paid".';
}
