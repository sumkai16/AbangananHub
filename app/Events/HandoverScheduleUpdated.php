<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signals that the key-handover slot was proposed, changed or confirmed.
 *
 * Needed because scheduling moves neither rental_status nor payment status, so
 * neither ReservationStatusUpdated nor PaymentStatusUpdated fires — and those
 * are the only two events the inbox refetches its panel on. Without this, a
 * landlord proposing a time left the tenant looking at a stale strip until
 * they reloaded: the system message arrived live (postSystemMessage dispatches
 * MessageSent) while the controls that let them answer it did not.
 *
 * Carries no markup, for the same reason ReservationStatusUpdated doesn't: the
 * panel renders differently for each party — one sees "waiting for them to
 * confirm", the other sees a Confirm button — so there is no single payload
 * correct for both. Clients refetch their own render.
 */
class HandoverScheduleUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Reservation $reservation)
    {
    }

    public function broadcastOn(): array
    {
        if (! $this->reservation->conversation_id) {
            return [];
        }

        // Conversation channel only: the schedule doesn't change the stage
        // pill, so a thread you aren't looking at has nothing to redraw. The
        // notification bell already covers that case.
        return [new PrivateChannel('conversation.' . $this->reservation->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'HandoverScheduleUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'reservation_id'  => $this->reservation->reservation_id,
            'conversation_id' => $this->reservation->conversation_id,
            'confirmed'       => $this->reservation->handover_confirmed_at !== null,
        ];
    }
}
