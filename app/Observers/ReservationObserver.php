<?php

namespace App\Observers;

use App\Events\HandoverScheduleUpdated;
use App\Events\ReservationStatusUpdated;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

/**
 * Broadcasts every rental_status transition and raises the matching
 * notifications for both parties.
 *
 * Reservation has seven separate transition methods (advanceToNegotiation,
 * advanceToPendingAgreement, signAgreement, markOccupied, confirmMoveIn,
 * reject, cancel) and they all end in save(). Hooking the save instead of
 * each method means no transition — present or future — can change the
 * status without the other party being told. Same reasoning as
 * PropertyUnitObserver, which catches availability_status the same way.
 */
class ReservationObserver
{
    public function created(Reservation $reservation): void
    {
        if (! $reservation->conversation_id) {
            return;
        }

        $landlordId = $reservation->property?->landlord_id;
        $tenant = $reservation->tenant;
        $unitLabel = $reservation->unit?->unit_label ?? $reservation->property?->title;

        Notification::notify(
            $landlordId,
            'reservation',
            'New inquiry received',
            trim(($tenant?->first_name ?? 'A tenant') . ' inquired about ' . $unitLabel . '.'),
            $this->threadLink($reservation),
            $reservation->conversation_id,
        );
    }

    public function updated(Reservation $reservation): void
    {
        // A reservation created outside the inquiry flow has no thread to
        // push into; nothing is listening.
        if (! $reservation->conversation_id) {
            return;
        }

        // Scheduling moves neither rental_status nor payment status, so none
        // of the events the inbox refetches on would fire. Hooked here for the
        // same reason the status broadcast is: no call site, present or
        // future, can change the slot without the other party's open panel
        // being told.
        if ($reservation->wasChanged(['handover_at', 'handover_confirmed_at'])) {
            $this->broadcastQuietly(fn () => HandoverScheduleUpdated::dispatch($reservation));
        }

        if (! $reservation->wasChanged('rental_status')) {
            return;
        }

        $this->broadcastQuietly(fn () => ReservationStatusUpdated::dispatch(
            $reservation,
            $reservation->getOriginal('rental_status'),
        ));

        $this->cancelViewingsIfEnded($reservation);

        $this->notifyTransition($reservation);
    }

    /**
     * A rejected, cancelled or completed reservation can't still have a
     * viewing on the landlord's calendar. Hooked here for the same reason as
     * the broadcast: every path that ends a reservation goes through save().
     * The status change's own notification already tells both parties, so
     * no separate viewing notice.
     */
    private function cancelViewingsIfEnded(Reservation $reservation): void
    {
        if (! in_array($reservation->rental_status, Reservation::TERMINAL_STATUSES, true)) {
            return;
        }

        $reservation->viewings()->active()->update([
            'status'       => 'Cancelled',
            'responded_at' => now(),
        ]);
    }

    private function notifyTransition(Reservation $reservation): void
    {
        $tenantId = $reservation->tenant_id;
        $landlordId = $reservation->property?->landlord_id;
        $tenantName = $reservation->tenant?->first_name ?? 'The tenant';
        $unitLabel = $reservation->unit?->unit_label ?? $reservation->property?->title;
        $thread = $this->threadLink($reservation);
        $conversationId = $reservation->conversation_id;

        switch ($reservation->rental_status) {
            case 'Under Negotiation':
                Notification::notify($tenantId, 'reservation', 'Inquiry accepted',
                    "Your inquiry for {$unitLabel} was accepted. You can now discuss the terms.",
                    $thread, $conversationId);
                break;

            case 'Pending Rental Agreement':
                Notification::notify($tenantId, 'agreement', 'Rental agreement ready',
                    "The landlord sent the rental agreement for {$unitLabel}. Review and sign it to continue.",
                    route('agreements.show', $reservation), $conversationId);
                break;

            case 'Rental Agreement Signed':
                Notification::notify($landlordId, 'agreement', 'Agreement signed',
                    "{$tenantName} signed the rental agreement for {$unitLabel}. Waiting for payment.",
                    $thread, $conversationId);
                break;

            case 'Occupied':
                Notification::notify($landlordId, 'payment', 'Move-in confirmed — payment released',
                    "{$tenantName} confirmed move-in for {$unitLabel}. The held payment has been released to you.",
                    $thread, $conversationId);
                Notification::notify($tenantId, 'payment', 'Move-in confirmed',
                    "You confirmed move-in for {$unitLabel}. Your payment has been released to the landlord.",
                    route('agreements.show', $reservation), $conversationId);
                break;

            case 'Rejected':
                $reason = $reservation->rejection_reason
                    ? ' Reason: ' . $reservation->rejection_reason
                    : '';
                Notification::notify($tenantId, 'reservation', 'Inquiry declined',
                    "Your inquiry for {$unitLabel} was declined." . $reason,
                    $thread, $conversationId);
                break;

            case 'Cancelled':
                // Notify whoever did not press the button. Falls back to
                // telling both when there is no authenticated actor (CLI,
                // scheduled job), which is better than telling neither.
                $actorId = auth()->id();

                if ($actorId !== $tenantId) {
                    Notification::notify($tenantId, 'reservation', 'Reservation cancelled',
                        "Your reservation for {$unitLabel} was cancelled.", $thread, $conversationId);
                }

                if ($actorId !== $landlordId) {
                    Notification::notify($landlordId, 'reservation', 'Reservation cancelled',
                        "{$tenantName} cancelled the reservation for {$unitLabel}.", $thread, $conversationId);
                }
                break;
        }
    }

    private function threadLink(Reservation $reservation): string
    {
        return route('conversations.index', ['active' => $reservation->conversation_id]);
    }

    /**
     * Both events here implement ShouldBroadcastNow, so they reach out to
     * Reverb synchronously from inside whatever transaction triggered the
     * save() (e.g. AgreementController::sign()). A stopped/unreachable
     * Reverb throws BroadcastException here, which would otherwise abort
     * and roll back the entire caller action just because nobody's
     * listening for the live update — the rental_status row is already
     * written and is the change of record; the broadcast is a nice-to-have
     * real-time nudge on top of it. Same pattern as Notification::notify().
     */
    private function broadcastQuietly(\Closure $dispatch): void
    {
        try {
            $dispatch();
        } catch (BroadcastException $e) {
            Log::warning('Reservation event broadcast failed, continuing without it', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
