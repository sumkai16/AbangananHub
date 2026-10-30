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
     * Only the landlord who owns the property, and only while the walk-in is
     * sitting in Reserved waiting for its move-in date. Not date-gated — the
     * landlord may confirm early or late, their own judgment is what starts
     * the tenancy for a walk-in, same as markTurnedOver() is an assertion
     * rather than a verified event.
     */
    public function confirmWalkInMoveIn(User $user, Reservation $reservation): bool
    {
        return $reservation->property
            && $reservation->property->landlord_id === $user->user_id
            && $reservation->rental_status === 'Reserved';
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
     * Either side of a tenancy can open its chat — the tenant, or the landlord who
     * owns the property. A tenancy that never started (still negotiating) has none.
     */
    public function viewTenancyChat(User $user, Reservation $reservation): bool
    {
        return in_array($reservation->rental_status, ['Occupied', 'Completed'], true)
            && ($reservation->tenant_id === $user->user_id
                || $reservation->property?->landlord_id === $user->user_id);
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
     * Deliberately NOT restricted to 'Occupied', same reasoning as
     * voidPayment — damage is often found around move-out, after the
     * tenancy has already been marked Completed.
     */
    public function recordDepositCharge(User $user, Reservation $reservation): bool
    {
        return $this->viewTenancy($user, $reservation);
    }

    public function voidDepositCharge(User $user, Reservation $reservation): bool
    {
        return $this->viewTenancy($user, $reservation) || $user->hasRole('Admin');
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

    /**
     * The lease: its tenant, or the landlord of its property. Unlike
     * viewAgreement (the tenant's signing page), the landlord needs this to
     * print a walk-in's lease and to check what an online tenant signed.
     */
    public function viewLease(User $user, Reservation $reservation): bool
    {
        return $reservation->tenant_id === $user->user_id
            || $reservation->property?->landlord_id === $user->user_id;
    }

    /**
     * Attaching the signed paper copy — landlord of the property only.
     */
    public function manageLease(User $user, Reservation $reservation): bool
    {
        return $reservation->property?->landlord_id === $user->user_id;
    }

    /**
     * The tenant's emergency contact and ID on file: the landlord of the property, once the tenant is
     * committed to the place (Reserved, Occupied) or has been (Completed). Not while still negotiating
     * or signing, and never to a landlord the tenant has no booking with, so a tenant who inquires
     * hands nothing over until there is a real tenancy.
     */
    public function viewTenantDetails(User $user, Reservation $reservation): bool
    {
        return $reservation->property?->landlord_id === $user->user_id
            && in_array($reservation->rental_status, ['Reserved', 'Occupied', 'Completed'], true);
    }

    /**
     * A walk-in cannot log in, so the landlord who recorded them keeps their details. Only that
     * landlord, and only while the tenancy is live; an online tenant edits their own in Account Settings.
     */
    public function editWalkInDetails(User $user, Reservation $reservation): bool
    {
        return $reservation->property?->landlord_id === $user->user_id
            && $reservation->tenant?->is_walk_in
            && $reservation->tenant->created_by_landlord_id === $user->user_id
            && in_array($reservation->rental_status, ['Reserved', 'Occupied'], true);
    }

    /**
     * Only the reservation's tenant asks to see the unit, and only between
     * the landlord accepting the inquiry and the agreement being signed.
     */
    public function requestViewing(User $user, Reservation $reservation): bool
    {
        return $reservation->tenant_id === $user->user_id
            && $reservation->canRequestViewing();
    }

    /**
     * The reservation's tenant reports a payment they made to the landlord: move-in money while the
     * agreement is signed, rent while the tenancy runs.
     */
    public function fileClaim(User $user, Reservation $reservation): bool
    {
        return $reservation->tenant_id === $user->user_id
            && in_array($reservation->rental_status, ['Rental Agreement Signed', 'Occupied'], true);
    }

    /**
     * The landlord of the property answers (confirms or rejects) a tenant's payment report, or records
     * the move-in money themselves. Not tied to a status here: the service re-checks it under a lock,
     * and a landlord should still be able to answer a report that arrived just before the tenancy ended.
     */
    public function decidePayment(User $user, Reservation $reservation): bool
    {
        return $reservation->property?->landlord_id === $user->user_id;
    }

    /** Recording move-in money by hand, only before move-in. */
    public function recordMoveInPayment(User $user, Reservation $reservation): bool
    {
        return $this->decidePayment($user, $reservation)
            && $reservation->rental_status === 'Rental Agreement Signed'
            && $reservation->move_in_paid_at === null;
    }

    /**
     * Either party confirms the move-in once the direct payment is settled. Nothing is held, so nothing
     * rides on who presses it (the same reasoning as confirmWalkInMoveIn()).
     */
    public function confirmDirectMoveIn(User $user, Reservation $reservation): bool
    {
        return $reservation->rental_status === 'Rental Agreement Signed'
            && $reservation->move_in_paid_at !== null
            && ($reservation->tenant_id === $user->user_id
                || $reservation->property?->landlord_id === $user->user_id);
    }
}
