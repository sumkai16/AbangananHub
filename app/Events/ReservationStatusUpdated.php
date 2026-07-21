<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signals that a reservation moved to a new rental_status.
 *
 * Deliberately carries no rendered markup: the chat panel renders
 * differently for the landlord and the tenant (each sees their own
 * actions), so there is no single HTML payload that would be correct
 * for both. Clients treat this as a "refetch yourself" nudge and pull
 * their own render from GET /conversations/{id}.
 */
class ReservationStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Reservation $reservation,
        public ?string $previousStatus = null,
    ) {
    }

    public function broadcastOn(): array
    {
        $conversation = $this->reservation->conversation;

        return [
            new PrivateChannel('conversation.' . $this->reservation->conversation_id),
            new PrivateChannel('user.' . $conversation->tenant_id),
            new PrivateChannel('user.' . $conversation->landlord_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ReservationStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'reservation_id'  => $this->reservation->reservation_id,
            'conversation_id' => $this->reservation->conversation_id,
            'status'          => $this->reservation->rental_status,
            'previous_status' => $this->previousStatus,
        ];
    }
}
