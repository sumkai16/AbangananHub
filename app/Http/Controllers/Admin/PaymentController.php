<?php

namespace App\Http\Controllers\Admin;

use App\Events\PaymentStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    private const STATUSES = ['All', 'Held', 'Released', 'Pending'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'Held');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Held';
        }

        $payments = Payment::with(['reservation.tenant', 'reservation.property', 'reservation.unit'])
            ->when($status !== 'All', fn ($q) => $q->where('status', $status))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'Held' => Payment::where('status', 'Held')->count(),
            'Released' => Payment::where('status', 'Released')->count(),
            'Pending' => Payment::where('status', 'Pending')->count(),
        ];
        $counts['All'] = Payment::count();

        $sums = [
            'Held' => Payment::where('status', 'Held')->sum('amount'),
            'Released' => Payment::where('status', 'Released')->sum('amount'),
        ];

        return view('admin.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'counts' => $counts,
            'sums' => $sums,
        ]);
    }

    public function release(Payment $payment)
    {
        $shouldBroadcast = false;

        // Held → Released moves real money conceptually; lock the row so a
        // double-click or a retried request can't both pass the status check
        // and release (and message) the same payment twice. The reservation
        // is locked too, since an admin release also resolves its dispute
        // and (possibly) flips it to Occupied — matching Reservation::
        // confirmMoveIn()'s locking shape for the same class of mutation.
        DB::transaction(function () use ($payment, &$shouldBroadcast) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->isHeld(), 409, 'Only held payments can be released.');

            $locked->update([
                'status' => 'Released',
                'released_at' => now(),
                'released_by' => auth()->id(),
                'release_reason' => 'admin_manual',
            ]);

            $shouldBroadcast = true;

            $reservation = $locked->reservation_id
                ? Reservation::whereKey($locked->reservation_id)->with(['unit', 'property'])->lockForUpdate()->first()
                : null;

            if ($reservation) {
                $wasDisputed = $reservation->move_in_disputed_at !== null;

                // Only a genuinely escalated or completed move-in lifecycle
                // should be closed out here. A release on an ordinary
                // mid-Clock-1 reservation (keys never turned over, never
                // escalated) must not assert an occupancy that never
                // happened — it only moves the money.
                $endsLifecycle = $reservation->rental_status === 'Rental Agreement Signed'
                    && ($reservation->keys_turned_over_at !== null || $wasDisputed);

                if ($wasDisputed) {
                    // Clears the dispute so the admin queue empties and
                    // disputeMoveIn() can be called again on a future clock.
                    $reservation->move_in_disputed_at = null;
                    $reservation->move_in_dispute_reason = null;
                }

                if ($endsLifecycle) {
                    // Mirrors Reservation::confirmMoveIn() except
                    // tenant_confirmed_move_in_at is deliberately left null:
                    // an admin resolved this, not the tenant. Clears the
                    // deadline because an admin decision ends the countdown
                    // regardless of which clock was running.
                    $reservation->move_in_deadline_at = null;
                    $reservation->rental_status = 'Occupied';
                }

                $reservation->save();

                if ($endsLifecycle && $reservation->unit) {
                    $reservation->unit->availability_status = 'Occupied';
                    $reservation->unit->save();
                }

                $message = $endsLifecycle
                    ? 'An administrator has reviewed this reservation and released the deposit to the landlord.'
                    : 'An administrator has released the held deposit to the landlord.';

                $reservation->postSystemMessage($message);

                Notification::notify(
                    $reservation->tenant_id,
                    'payment_released',
                    'Deposit released',
                    $endsLifecycle
                        ? 'An administrator has reviewed your reservation and released the deposit to your landlord.'
                        : 'An administrator has released the held deposit on your reservation to your landlord.',
                    route('agreements.show', $reservation),
                    $reservation->conversation_id,
                );

                Notification::notify(
                    $reservation->property?->landlord_id,
                    'payment_released',
                    'Deposit released',
                    $endsLifecycle
                        ? 'An administrator has reviewed this reservation and released the held deposit to you.'
                        : 'An administrator has released the held deposit on this reservation to you.',
                    route('landlord.reservations.index'),
                    $reservation->conversation_id,
                );
            }
        });

        $payment->refresh();
        $payment->load('reservation.property.landlord');

        $landlord = $payment->reservation?->property?->landlord;
        $landlordName = $landlord
            ? trim($landlord->first_name.' '.$landlord->last_name)
            : 'the landlord';

        if ($shouldBroadcast) {
            PaymentStatusUpdated::dispatch($payment);
        }

        return back()->with('success', "Payment released to {$landlordName}.");
    }
}
