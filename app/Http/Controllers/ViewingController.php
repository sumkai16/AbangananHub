<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Reservation;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Online unit viewings: the tenant asks from the chat, and either party
 * reschedules, confirms, declines or cancels. The landlord's Viewings tab
 * posts to these same routes, so there is one implementation of each action.
 *
 * Offline viewings (logged by the landlord for a visitor with no account)
 * live in Landlord\ViewingController instead: nobody is on the other end to
 * confirm, message or notify.
 *
 * Every write locks its row: a double-submit would otherwise post the system
 * message twice, and two tabs could book two active viewings at once.
 */
class ViewingController extends Controller
{
    public function store(Request $request, Reservation $reservation)
    {
        Gate::authorize('requestViewing', $reservation);

        $validated = $this->validateSlot($request) + $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $landlordId = $reservation->property->landlord_id;
        $slot = Carbon::parse($validated['scheduled_at']);

        if ($problem = ViewingRequest::slotProblem($slot, $landlordId)) {
            return back()->with('error', $problem);
        }

        $tenant = $request->user();

        $viewing = DB::transaction(function () use ($reservation, $slot, $tenant, $landlordId, $validated) {
            // Lock the reservation, not the viewing: the row that must not be
            // created twice doesn't exist yet.
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->viewings()->open()->exists()) {
                return null;
            }

            $viewing = ViewingRequest::create([
                'source'         => 'Online',
                'reservation_id' => $locked->reservation_id,
                'property_id'    => $locked->property_id,
                'unit_id'        => $locked->unit_id,
                'tenant_id'      => $tenant->user_id,
                'landlord_id'    => $landlordId,
                'scheduled_at'   => $slot,
                'status'         => 'Pending',
                'proposed_by'    => $tenant->user_id,
                'note'           => $validated['note'] ?? null,
            ]);

            $this->announce(
                $viewing,
                $tenant,
                "{$tenant->first_name} asked to view the unit on {$this->long($slot)}.",
                'Viewing requested',
                "{$tenant->first_name} asked to view the unit on {$this->short($slot)}. Approve it, decline it or suggest another time.",
            );

            return $viewing;
        });

        if (! $viewing) {
            return back()->with('error', 'You already have a viewing scheduled for this unit.');
        }

        $viewing->broadcastUpdate();

        return back()->with('success', 'Viewing requested. Waiting for the landlord to confirm.');
    }

    public function reschedule(Request $request, ViewingRequest $viewing)
    {
        Gate::authorize('participate', $viewing);
        abort_if($viewing->isOffline(), 404);

        $slot = Carbon::parse($this->validateSlot($request)['scheduled_at']);

        if ($problem = ViewingRequest::slotProblem($slot, $viewing->landlord_id)) {
            return back()->with('error', $problem);
        }

        $actor = $request->user();

        $done = $this->mutate($viewing, function (ViewingRequest $locked) use ($slot, $actor) {
            if (! $locked->isActive()) {
                return false;
            }

            // The time changed, so it needs agreeing again — by the other side.
            $locked->update([
                'scheduled_at' => $slot,
                'status'       => 'Pending',
                'proposed_by'  => $actor->user_id,
                'responded_at' => null,
            ]);

            $this->announce(
                $locked,
                $actor,
                "{$actor->first_name} suggested viewing the unit on {$this->long($slot)} instead.",
                'New viewing time suggested',
                "{$actor->first_name} suggested viewing the unit on {$this->short($slot)}. Confirm it or suggest another time.",
            );

            return true;
        });

        return $done
            ? back()->with('success', 'New time sent. Waiting for the other party to confirm.')
            : back()->with('error', 'This viewing can no longer be changed.');
    }

    public function confirm(Request $request, ViewingRequest $viewing)
    {
        Gate::authorize('respond', $viewing);

        $actor = $request->user();

        $done = $this->mutate($viewing, function (ViewingRequest $locked) use ($actor) {
            // Re-checked under the lock: the time may have been changed since
            // the page with this button was rendered.
            if (! $locked->awaitsResponseFrom($actor->user_id)) {
                return false;
            }

            $locked->update(['status' => 'Confirmed', 'responded_at' => now()]);

            $this->announce(
                $locked,
                $actor,
                "The viewing is confirmed for {$this->long($locked->scheduled_at)}.",
                'Viewing confirmed',
                "Your viewing on {$this->short($locked->scheduled_at)} is confirmed.",
            );

            return true;
        });

        return $done
            ? back()->with('success', 'Viewing confirmed.')
            : back()->with('error', 'That viewing time can no longer be confirmed. It may have been changed.');
    }

    public function decline(Request $request, ViewingRequest $viewing)
    {
        Gate::authorize('respond', $viewing);

        $reason = $request->validate([
            'decline_reason' => ['nullable', 'string', 'max:255'],
        ])['decline_reason'] ?? null;

        $actor = $request->user();

        $done = $this->mutate($viewing, function (ViewingRequest $locked) use ($actor, $reason) {
            if (! $locked->awaitsResponseFrom($actor->user_id)) {
                return false;
            }

            $locked->update([
                'status'         => 'Declined',
                'decline_reason' => $reason,
                'responded_at'   => now(),
            ]);

            $suffix = $reason ? " Reason: {$reason}" : '';

            $this->announce(
                $locked,
                $actor,
                "{$actor->first_name} declined the viewing on {$this->long($locked->scheduled_at)}.{$suffix}",
                'Viewing declined',
                "The viewing on {$this->short($locked->scheduled_at)} was declined.{$suffix}",
            );

            return true;
        });

        return $done
            ? back()->with('success', 'Viewing declined.')
            : back()->with('error', 'That viewing can no longer be declined. It may have been changed.');
    }

    public function cancel(Request $request, ViewingRequest $viewing)
    {
        Gate::authorize('participate', $viewing);
        abort_if($viewing->isOffline(), 404);

        $actor = $request->user();

        $done = $this->mutate($viewing, function (ViewingRequest $locked) use ($actor) {
            if (! $locked->isActive()) {
                return false;
            }

            $locked->update(['status' => 'Cancelled', 'responded_at' => now()]);

            $this->announce(
                $locked,
                $actor,
                "{$actor->first_name} cancelled the viewing on {$this->long($locked->scheduled_at)}.",
                'Viewing cancelled',
                "{$actor->first_name} cancelled the viewing on {$this->short($locked->scheduled_at)}.",
            );

            return true;
        });

        return $done
            ? back()->with('success', 'Viewing cancelled.')
            : back()->with('error', 'This viewing is no longer active.');
    }

    private function validateSlot(Request $request): array
    {
        return $request->validate([
            'scheduled_at' => ['required', 'date'],
        ], [
            'scheduled_at.required' => 'Pick a date and time for the viewing.',
        ]);
    }

    /**
     * Run $change against a locked copy of the viewing; broadcast after the
     * commit so the other party's refetch sees the new row.
     */
    private function mutate(ViewingRequest $viewing, \Closure $change): bool
    {
        $done = DB::transaction(function () use ($viewing, $change) {
            $locked = ViewingRequest::whereKey($viewing->getKey())->lockForUpdate()->firstOrFail();

            return $change($locked);
        });

        if ($done) {
            $viewing->refresh()->broadcastUpdate();
        }

        return $done;
    }

    /**
     * System message in the thread for both, a notification for whoever
     * didn't act.
     */
    private function announce(ViewingRequest $viewing, User $actor, string $systemMessage, string $title, string $notice): void
    {
        $reservation = $viewing->reservation;
        $reservation?->postSystemMessage($systemMessage);

        Notification::notify(
            $viewing->counterpartyOf($actor->user_id),
            'reservation',
            $title,
            $notice,
            $reservation?->conversation_id
                ? route('conversations.index', ['active' => $reservation->conversation_id])
                : null,
            $reservation?->conversation_id,
        );
    }

    private function long(Carbon $slot): string
    {
        return $slot->format('l, F j \a\t g:i A');
    }

    private function short(Carbon $slot): string
    {
        return $slot->format('M j \a\t g:i A');
    }
}
