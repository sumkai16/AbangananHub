<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signals that a payment changed state (typically Pending -> Held once the
 * PayMongo webhook lands).
 *
 * The agreement page renders five mutually exclusive server-side states and
 * told the tenant it would "update once the payment is verified" while
 * nothing was listening. This is what makes that true.
 */
class PaymentStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Payment $payment)
    {
    }

    public function broadcastOn(): array
    {
        $reservation = $this->payment->reservation;

        $channels = [];

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
        return 'PaymentStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'payment_id'     => $this->payment->payment_id,
            'reservation_id' => $this->payment->reservation_id,
            'status'         => $this->payment->status,
        ];
    }
}
