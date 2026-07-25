<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signals that a payment changed state (typically Pending -> Held once the
 * checkout settles).
 *
 * Dispatched by PaymentObserver off the status column, not by the call sites
 * — four of them settle payments and one used to forget.
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

        // The conversation stepper derives its "Paid" stage from payment
        // state, not from rental_status — which does not change when money
        // lands — so the open thread has to hear this on its own channel the
        // way it hears ReservationStatusUpdated.
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
        return 'PaymentStatusUpdated';
    }

    public function broadcastWith(): array
    {
        $reservation = $this->payment->reservation;

        return [
            'payment_id'      => $this->payment->payment_id,
            'reservation_id'  => $this->payment->reservation_id,
            'status'          => $this->payment->status,
            // Lets the inbox find the right list row without a refetch. The
            // stage is derived the same way chat-panel.blade.php derives it,
            // stated here so the two can't disagree about what "Paid" means.
            'conversation_id' => $reservation?->conversation_id,
            'stage'           => $this->derivedStage($reservation),
        ];
    }

    /**
     * Mirrors the stepper: the reservation stays on 'Rental Agreement Signed'
     * while money is escrowed, and only 'Occupied' outranks the Paid stage.
     */
    private function derivedStage(?Reservation $reservation): ?string
    {
        if (! $reservation) {
            return null;
        }

        if ($reservation->rental_status !== 'Rental Agreement Signed') {
            return $reservation->rental_status;
        }

        return in_array($this->payment->status, ['Held', 'Released'], true)
            ? 'Paid'
            : $reservation->rental_status;
    }
}
