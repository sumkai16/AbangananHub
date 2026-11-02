<?php

namespace App\Support;

use App\Models\Reservation;
use App\Models\User;

/**
 * The ways a tenant may pay a landlord, chosen by the landlord on Settings > Payments and applied to both the
 * move-in payment and monthly rent:
 *  - online: PayMongo checkout (Payments::onlineFor: the switch, GCash on file, accepts_online),
 *  - qr: the landlord's own QR, then an "I've paid" report,
 *  - cash: in person, recorded by the landlord.
 * Every tenant-facing choice (web, API) and the claim endpoint read this, never the columns directly.
 */
final class PaymentMethods
{
    public const NONE_LEFT = 'Keep at least one way for tenants to pay you. Online needs your GCash details saved first.';

    /** @return array{online: bool, qr: bool, cash: bool} */
    public static function forReservation(Reservation $reservation): array
    {
        $landlord = $reservation->property?->landlord;

        $methods = [
            'online' => Payments::onlineFor($reservation),
            'qr' => (bool) ($landlord?->accepts_qr_payments ?? true),
            'cash' => (bool) ($landlord?->accepts_cash_payments ?? true),
        ];

        // Settings refuses to save "nothing on", but online can drop out later (GCash removed, online payments
        // switched off by the admin). A tenant is never left with no way to pay: both direct ways come back.
        if (! in_array(true, $methods, true)) {
            $methods['qr'] = $methods['cash'] = true;
        }

        return $methods;
    }

    /** Whether a tenant's "I've paid" report with this method is one the landlord accepts. */
    public static function allowsClaim(Reservation $reservation, string $paymentMethod): bool
    {
        $methods = self::forReservation($reservation);

        return $paymentMethod === 'Cash' ? $methods['cash'] : $methods['qr'];
    }

    /** Whether online can be offered for this landlord at all: the switch is on and their GCash is saved. */
    public static function onlinePossibleFor(User $landlord): bool
    {
        return Payments::online() && $landlord->hasPayoutDestination();
    }
}
