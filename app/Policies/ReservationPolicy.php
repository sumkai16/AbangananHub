<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Tenant can cancel their own reservation, or landlord can cancel
     * a reservation on a property they own.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->user_id === $reservation->tenant_id
            || $user->user_id === $reservation->property->landlord_id;
    }

    /**
     * Landlord can reject a reservation on a property they own.
     */
    public function reject(User $user, Reservation $reservation): bool
    {
        return $user->user_id === $reservation->property->landlord_id;
    }

    /**
     * Landlord can advance the rental_status (Inquiry → Under Negotiation → Pending Rental Agreement).
     */
    public function advanceStatus(User $user, Reservation $reservation): bool
    {
        return $user->user_id === $reservation->property->landlord_id;
    }

    /**
     * Tenant can view the agreement for their own reservation.
     */
    public function viewAgreement(User $user, Reservation $reservation): bool
    {
        return $user->user_id === $reservation->tenant_id;
    }

    /**
     * Tenant can sign the agreement for their own reservation.
     */
    public function sign(User $user, Reservation $reservation): bool
    {
        return $this->viewAgreement($user, $reservation);
    }

    /**
     * Only the landlord who owns the property can assert turnover, and only
     * while the agreement is signed but unconfirmed.
     */
    public function markTurnedOver(User $user, Reservation $reservation): bool
    {
        return $reservation->property
            && $reservation->property->landlord_id === $user->user_id
            && $reservation->rental_status === 'Rental Agreement Signed';
    }
}