<?php

namespace App\Services;

use App\Models\PaymentClaim;
use App\Models\Reservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The number on the landlord sidebar's Payments item: tenancies that need the landlord's attention for money.
 *
 * That is a tenancy whose rent is overdue (the same tenancies the Payments page marks Overdue and counts in "Remind
 * overdue", read from the same RentLedger so the two numbers cannot disagree) or one with a payment report waiting
 * for an answer. A tenancy that is both counts once. Rent that is owed but not due yet (Upcoming) is not counted:
 * nobody has to act on it, and the number would say 7 over a list that shows 5 red rows.
 *
 * The ledger runs per tenancy and the sidebar renders on every landlord page, so the number is cached for a few
 * minutes. Anything that changes a payment, a report or a tenancy's status clears it (PaymentObserver,
 * ReservationObserver, PaymentClaim), so a recorded payment shows at once and only a date rolling over can lag.
 */
class LandlordRentDueCount
{
    private const MINUTES = 10;

    public static function for(int $landlordId): int
    {
        return (int) Cache::remember(self::key($landlordId), now()->addMinutes(self::MINUTES), fn () => self::compute($landlordId));
    }

    public static function forget(int $landlordId): void
    {
        Cache::forget(self::key($landlordId));
    }

    /**
     * Clear the cached number for whoever owns this reservation. A cache hiccup must never break the money action
     * that called this, so it logs and carries on.
     */
    public static function forgetForReservation(int $reservationId): void
    {
        try {
            $landlordId = self::landlordIdOf($reservationId);

            if ($landlordId) {
                self::forget($landlordId);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not clear the rent-due count', ['reservation_id' => $reservationId, 'error' => $e->getMessage()]);
        }
    }

    public static function landlordIdOf(int $reservationId): ?int
    {
        $id = DB::table('reservations')
            ->join('properties', 'properties.property_id', '=', 'reservations.property_id')
            ->where('reservations.reservation_id', $reservationId)
            ->value('properties.landlord_id');

        return $id ? (int) $id : null;
    }

    private static function key(int $landlordId): string
    {
        return "landlord:{$landlordId}:rent-due";
    }

    private static function compute(int $landlordId): int
    {
        // Everything the ledger reads is loaded up front, so the loop below runs no query per tenancy.
        $overdue = Reservation::whereIn('rental_status', ['Occupied', 'Completed'])
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->with(['property', 'unit', 'payments', 'depositCharges', 'lateFeeWaivers', 'renewals'])
            ->get()
            ->filter(fn (Reservation $r) => RentLedger::for($r)->summary()['paymentStatus'] === 'overdue')
            ->pluck('reservation_id');

        $waiting = PaymentClaim::where('status', 'Pending')
            ->whereHas('reservation.property', fn ($q) => $q->where('landlord_id', $landlordId))
            ->pluck('reservation_id');

        return $overdue->merge($waiting)->unique()->count();
    }
}
