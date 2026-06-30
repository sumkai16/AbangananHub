<?php
namespace App\Policies;
use App\Models\Reservation;
use App\Models\User;
class ReservationPolicy
{
    /**
     * Tenant can cancel their own reservation, or landlord can cancel
     * a reservation on a property they own (e.g. tenant backed out by phone).
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->user_id === $reservation->tenant_id
            || $user->user_id === $reservation->property->landlord_id;
    }
    /**
     * Landlord can approve a reservation on a property they own.
     */
    public function approve(User $user, Reservation $reservation): bool
    {
        return $user->user_id === $reservation->property->landlord_id;
    }
    /**
     * Landlord can reject a reservation on a property they own.
     */
    public function reject(User $user, Reservation $reservation): bool
    {
        return $this->approve($user, $reservation);
    }
}