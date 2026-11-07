<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the landlord's "needs action" rent notices honest.
 *
 * A "Rent overdue" notice is a to-do. Left unread after the tenant has paid, it is a to-do that is already done, and
 * the landlord cannot tell it from a live one. So whenever a tenancy's money changes this clears the sidebar's cached
 * count and, once the ledger shows nothing outstanding, marks that tenancy's unread rent_due notices read. A part
 * payment leaves them alone: something is still owed.
 */
class RentDueNotices
{
    public static function refresh(int $reservationId): void
    {
        // Runs after a payment or a status change was saved. A failure here must not undo or hide that.
        try {
            LandlordRentDueCount::forgetForReservation($reservationId);

            $reservation = Reservation::with(['property', 'unit', 'payments', 'depositCharges', 'lateFeeWaivers', 'renewals'])
                ->find($reservationId);

            if (! $reservation) {
                return;
            }

            if (RentLedger::for($reservation)->summary()['outstanding'] <= 0) {
                Notification::where('type', 'rent_due')
                    ->where('notifiable_type', Reservation::class)
                    ->where('notifiable_id', $reservationId)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not refresh rent-due notices', ['reservation_id' => $reservationId, 'error' => $e->getMessage()]);
        }
    }
}
