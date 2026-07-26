<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Mobile equivalent of HandoverController — shared by tenant and landlord,
 * same as the web routes/web.php entry. See that controller's docblock for
 * why proposing/confirming is symmetric and row-locked.
 */
class HandoverController extends Controller
{
    public function propose(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('scheduleHandover', $reservation);

        $validated = $request->validate([
            'handover_at' => ['required', 'date', 'after:now', 'before:'.now()->addYear()->toDateTimeString()],
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
            throw ValidationException::withMessages(['handover' => ['A handover time cannot be proposed for this reservation right now.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }

    public function confirm(Request $request, Reservation $reservation): JsonResponse
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
                .($deadline ? " If the keys aren't turned over by {$deadline}, this reservation goes to admin review." : '')
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
            throw ValidationException::withMessages(['handover' => ['That handover time can no longer be confirmed. It may have been changed.']]);
        }

        return response()->json(['data' => new ReservationResource($reservation->fresh())]);
    }

    private function counterpartyId(Reservation $reservation, int $actorId): ?int
    {
        return $actorId === $reservation->tenant_id
            ? $reservation->property?->landlord_id
            : $reservation->tenant_id;
    }
}
