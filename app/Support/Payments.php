<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\UserPayoutAccount;

/**
 * The one place that answers "can tenants pay online right now?" so the buttons, the checkout
 * controllers (web and API) and the consent copy can never disagree. Admin-editable through
 * System Settings; off by default, in which case tenants pay the landlord directly and report it.
 *
 * Two questions, kept apart: `online()` is the platform-wide switch (copy that is true for everyone),
 * `onlineFor()` is whether *this* tenant may pay *this* landlord online. When it is on, the tenant picks
 * online or direct for each payment; direct is always available.
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

    /**
     * Whether online checkout is offered on this reservation: the switch is on and the landlord can be paid
     * (GCash details on file) and has not opted out. Every checkout gate and every "Pay online" button
     * reads this, never `online()` alone.
     */
    public static function onlineFor(Reservation $reservation): bool
    {
        if (! self::online()) {
            return false;
        }

        $landlordId = $reservation->property?->landlord_id;

        return $landlordId !== null && UserPayoutAccount::acceptsOnline($landlordId);
    }

    /** The reason shown (and returned as a 409) when someone tries to start a checkout while it is off. */
    public const OFFLINE_MESSAGE = 'Online payment is not available right now. Pay your landlord directly with the QR they shared, then tap "I paid".';
}
