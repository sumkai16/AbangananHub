<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Scheduling the key handover.
 *
 * Symmetric by design — whoever is ready proposes a slot and the other party
 * confirms it. A confirmed slot becomes the basis for Clock 1's escalation
 * deadline, replacing the target_move_in_date the tenant picked at inquiry time
 * before the landlord had even replied.
 *
 * Both actions take a row lock for the same reason markTurnedOver does: they
 * write a value that feeds an automated payout deadline, and a double-submit
 * would otherwise post the system message twice.
 */
class HandoverController extends Controller
{
    public function propose(Request $request, Reservation $reservation)
    {
        Gate::authorize('scheduleHandover', $reservation);

        $validated = $request->validate([
            'handover_at' => ['required', 'date', 'after:now', 'before:' . now()->addYear()->toDateTimeString()],
        ], [
            'handover_at.required' => 'Pick a date and time for the handover.',
            'handover_at.after'    => 'The handover time has to be in the future.',
            'handover_at.before'   => 'Pick a handover time within the next year.',
        ]);

        $slot = Carbon::parse($validated['handover_at']);
        $userId = $request->user()->user_id;

        $proposed = DB::transaction(function () use ($reservation, $slot, $userId) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->proposeHandover($slot, $userId)) {
                return false;
            }

            $who = $userId === $locked->tenant_id
                ? ($locked->tenant?->first_name ?? 'The tenant')
                : ($locked->property?->landlord?->first_name ?? 'The landlord');

            $locked->postSystemMessage(
                "{$who} proposed a key handover on {$slot->format('F j, Y \a\t g:i A')}. The other party needs to confirm it."
            );

            Notification::notify(
                $this->counterpartyId($locked, $userId),
                'reservation',
                'Handover time proposed',
                "{$who} proposed handing over the keys on {$slot->format('M j, Y \a\t g:i A')}. Confirm it or suggest another time.",
                route('conversations.index', ['active' => $locked->conversation_id]),
                $locked->conversation_id,
            );

            return true;
        });

        if (! $proposed) {
            return back()->with('error', 'A handover time cannot be proposed for this reservation right now.');
        }

        return back()->with('success', 'Handover time proposed. Waiting for the other party to confirm.');
    }

    public function confirm(Request $request, Reservation $reservation)
    {
        Gate::authorize('scheduleHandover', $reservation);

        $userId = $request->user()->user_id;

        $confirmed = DB::transaction(function () use ($reservation, $userId) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->confirmHandover($userId)) {
                return false;
            }

            $slot = $locked->handover_at->format('F j, Y \a\t g:i A');
            $deadline = $locked->move_in_deadline_at?->format('F j, Y');

            $locked->postSystemMessage(
                "The key handover is set for {$slot}."
                . ($deadline ? " If the keys aren't turned over by {$deadline}, this reservation goes to admin review." : '')
            );

            Notification::notify(
                $this->counterpartyId($locked, $userId),
                'reservation',
                'Handover time confirmed',
                "The key handover is confirmed for {$slot}.",
                route('conversations.index', ['active' => $locked->conversation_id]),
                $locked->conversation_id,
            );

            return true;
        });

        if (! $confirmed) {
            return back()->with('error', 'That handover time can no longer be confirmed. It may have been changed.');
        }

        return back()->with('success', 'Handover time confirmed.');
    }

    /**
     * The party who did not act — they're the one who needs telling.
     */
    private function counterpartyId(Reservation $reservation, int $actorId): ?int
    {
        return $actorId === $reservation->tenant_id
            ? $reservation->property?->landlord_id
            : $reservation->tenant_id;
    }
}
