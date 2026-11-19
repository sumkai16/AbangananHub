<?php

namespace App\Events;

use App\Models\PaymentClaim;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment report ("I've paid", filed by a tenant for a direct payment) was filed, cancelled, confirmed, turned
 * down or sent to an admin. Neither the reservation's status nor a Payment row changes when a report is only
 * filed, so none of the other live events fire, and the landlord's open chat (which shows the report with its
 * Confirm and Reject buttons) and the tenant's pay page kept showing the old state until a reload.
 *
 * Dispatched by PaymentClaim itself after the commit, so every path that touches a claim is covered.
 */
class PaymentClaimUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public PaymentClaim $claim)
    {
    }

    public function broadcastOn(): array
    {
        $reservation = $this->claim->reservation;
        $channels = [];

        if ($reservation?->conversation_id) {
            $channels[] = new PrivateChannel('conversation.' . $reservation->conversation_id);
        }

        if ($reservation?->tenant_id) {
            $channels[] = new PrivateChannel('user.' . $reservation->tenant_id);
        }

        if ($reservation?->property?->landlord_id) {
            $channels[] = new PrivateChannel('user.' . $reservation->property->landlord_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'PaymentClaimUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'claim_id'        => $this->claim->payment_claim_id,
            'reservation_id'  => $this->claim->reservation_id,
            'conversation_id' => $this->claim->reservation?->conversation_id,
            'claim_type'      => $this->claim->claim_type,
            'status'          => $this->claim->status,
        ];
    }
}
