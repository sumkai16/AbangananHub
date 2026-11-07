<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymentRefunds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    // 'Paid' = a landlord-recorded offline payment (rent ledger). It is never
    // escrowed, so it can't be released — the list exists so an admin can still
    // audit that money, clearly separated from platform-settled funds.
    // 'Voided' = a landlord struck their own recorded entry — visible here so
    // an admin arbitrating a dispute can see a correction happened, same
    // accountability reason the whole payments log exists.
    // 'Refunded' = an admin gave a held online payment back to the tenant (App\Services\PaymentRefunds).
    private const STATUSES = ['All', 'Held', 'Released', 'Paid', 'Voided', 'Pending', 'Refunded'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'All');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'All';
        }

        $search = trim((string) $request->query('search', ''));
        $sort = $request->query('sort', 'newest');

        // Search and the counts share one base query, so the strip and the status dropdown follow the search.
        $base = fn () => Payment::query()->when($search !== '', fn ($q) => $q->whereHas('reservation', function ($r) use ($search) {
            $r->whereHas('tenant', fn ($t) => $t->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('property', fn ($p) => $p->where('title', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%"))
                ->orWhereHas('property.landlord', fn ($l) => $l->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%"));
        }));

        $payments = $base()->with(['reservation.tenant', 'reservation.property.landlord', 'reservation.unit'])
            ->when($status !== 'All', fn ($q) => $q->where('status', $status))
            // On All with the default sort, money waiting on the admin (Held) comes first.
            ->when($status === 'All' && $sort === 'newest', fn ($q) => $q->orderByRaw("status = 'Held' desc"))
            ->when($sort === 'oldest', fn ($q) => $q->oldest('created_at'), fn ($q) => $q->when(
                in_array($sort, ['amount_desc', 'amount_asc'], true),
                fn ($q) => $q->orderBy('amount', $sort === 'amount_desc' ? 'desc' : 'asc'),
                fn ($q) => $q->latest('created_at')
            ))
            ->paginate(15)
            ->withQueryString();

        $counts = ['All' => $base()->count()];
        foreach (['Held', 'Released', 'Paid', 'Voided', 'Pending', 'Refunded'] as $st) {
            $counts[$st] = $base()->where('status', $st)->count();
        }

        $sums = [];
        foreach (['Held', 'Released', 'Paid'] as $st) {
            $sums[$st] = $base()->where('status', $st)->sum('amount');
        }

        return view('admin.payments.index', [
            'payments' => $payments,
            'status' => $status,
            'counts' => $counts,
            'sums' => $sums,
            'search' => $search,
            'sort' => in_array($sort, ['oldest', 'amount_desc', 'amount_asc'], true) ? $sort : 'newest',
            // What "Refund tenant" would send for each Held row on this page, from one query.
            'heldGroups' => PaymentRefunds::heldGroups($payments->getCollection()),
        ]);
    }

    public function release(Payment $payment)
    {
        // Held → Released moves real money conceptually; lock the row so a
        // double-click or a retried request can't both pass the status check
        // and release (and message) the same payment twice. The reservation
        // is locked too, since an admin release also resolves its dispute
        // and (possibly) flips it to Occupied — matching Reservation::
        // confirmMoveIn()'s locking shape for the same class of mutation.
        DB::transaction(function () use ($payment) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->isHeld(), 409, 'Only held payments can be released.');

            // An online move-in is several rows (rent, deposit) funded by one checkout; they are released together,
            // whichever of them the admin pressed Release on.
            $group = $locked->paymongo_checkout_session_id
                ? Payment::where('paymongo_checkout_session_id', $locked->paymongo_checkout_session_id)
                    ->where('status', 'Held')
                    ->orderBy('payment_id')
                    ->lockForUpdate()
                    ->get()
                : collect([$locked]);

            $group->each(fn (Payment $p) => $p->markReleased('admin_manual', auth()->id()));

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

            AuditLog::record(
                'payment.release',
                "Released held payment #{$locked->payment_id} to the landlord.",
                $locked,
                null,
                [
                    'amount'         => $group->sum('amount'),
                    'rows'           => $group->pluck('payment_id')->all(),
                    'reservation_id' => $locked->reservation_id,
                    'ended_lifecycle' => $reservation ? ($reservation->rental_status === 'Occupied') : false,
                ],
            );
        });

        $payment->refresh();
        $payment->load('reservation.property.landlord');

        $landlord = $payment->reservation?->property?->landlord;
        $landlordName = $landlord
            ? trim($landlord->first_name.' '.$landlord->last_name)
            : 'the landlord';

        // PaymentObserver broadcasts the Held -> Released transition. Its
        // $afterCommit is also what the old $shouldBroadcast flag was for: a
        // rolled-back release never reaches the observer, so there is nothing
        // left to guard against here.
        return back()->with('success', "Payment released to {$landlordName}.");
    }
}
