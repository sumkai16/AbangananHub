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

    /**
     * The landlord who owns the property can open a tenancy's detail page and
     * its rent ledger.
     *
     * Deliberately not restricted to Occupied: a landlord still needs to read
     * the ledger of a tenancy they have already ended, which is where the
     * record of what was collected lives.
     */
    public function viewTenancy(User $user, Reservation $reservation): bool
    {
        return $reservation->property
            && $reservation->property->landlord_id === $user->user_id;
    }

    /**
     * The tenant on this tenancy can open their own read-only rent ledger.
     *
     * Same shape as viewTenancy but scoped to the tenant instead of the
     * landlord — deliberately not restricted to Occupied, for the same reason:
     * a tenant should still be able to read what they paid after move-out.
     */
    public function viewOwnTenancy(User $user, Reservation $reservation): bool
    {
        return $reservation->tenant_id === $user->user_id;
    }

    /**
     * A tenant may pay rent online only while the tenancy is actually running
     * — there is nothing to bill against once it has ended.
     */
    public function payRent(User $user, Reservation $reservation): bool
    {
        return $reservation->tenant_id === $user->user_id
            && $reservation->rental_status === 'Occupied';
    }

    /**
     * Recording a payment writes a money row that only a void can undo,
     * never a delete, so it is owner-only and refuses once the tenancy is
     * over — a closed ledger should not gain new entries.
     */
    public function recordPayment(User $user, Reservation $reservation): bool
    {
        return $this->viewTenancy($user, $reservation)
            && $reservation->rental_status === 'Occupied';
    }

    /**
     * Voiding removes a wrong entry rather than adding a new one, so —
     * unlike recordPayment — it is deliberately NOT restricted to
     * 'Occupied'. A wrong figure in a closed ledger is still a wrong
     * financial record, and the landlord who owns the property is the only
     * person who can say so.
     */
    public function voidPayment(User $user, Reservation $reservation): bool
    {
        return $this->viewTenancy($user, $reservation);
    }

    /**
     * Ending a tenancy hands the unit back to the available pool, so only the
     * owner may do it and only while it is actually running.
     */
    public function endTenancy(User $user, Reservation $reservation): bool
    {
        return $this->viewTenancy($user, $reservation)
            && $reservation->rental_status === 'Occupied';
    }

    /**
     * Scheduling the handover is symmetric — whoever is ready puts up a slot.
     * Both parties to this reservation qualify; the model enforces the rest
     * (signed, paid, keys not yet turned over, not disputed).
     */
    public function scheduleHandover(User $user, Reservation $reservation): bool
    {
        return $reservation->rental_status === 'Rental Agreement Signed'
            && (
                $reservation->tenant_id === $user->user_id
                || $reservation->property?->landlord_id === $user->user_id
            );
    }
}