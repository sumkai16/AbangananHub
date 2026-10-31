<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\SendAgreementRequest;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ViewingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    /**
     * The page's stage tabs. Reservations is the request pipeline only (plans/reservations-page-pipeline.md): from
     * the first inquiry to a signed agreement whose move-in money the landlord has not confirmed yet. Once it is
     * confirmed the lease is on the Contracts page, so Reserved, Occupied and Completed never show here.
     */
    public const STAGES = ['open', 'new', 'discussing', 'agreement', 'payment', 'closed', 'history'];

    /** Narrows a reservation query to one stage tab. */
    public static function applyStage($query, string $stage)
    {
        $awaitingPayment = fn ($q) => $q->where('rental_status', 'Rental Agreement Signed')->whereNull('move_in_paid_at');

        return match ($stage) {
            'new' => $query->where('rental_status', 'Inquiry'),
            'discussing' => $query->where('rental_status', 'Under Negotiation'),
            'agreement' => $query->where('rental_status', 'Pending Rental Agreement'),
            'payment' => $query->where($awaitingPayment),
            'closed' => $query->whereIn('rental_status', ['Rejected', 'Cancelled']),
            // Past the pipeline (move-in confirmed): read-only record, the lease itself is on Contracts.
            'history' => $query->where(fn ($q) => $q->whereIn('rental_status', ['Reserved', 'Occupied', 'Completed'])
                ->orWhere(fn ($s) => $s->where('rental_status', 'Rental Agreement Signed')->whereNotNull('move_in_paid_at'))),
            default => $query->where(fn ($q) => $q->whereIn('rental_status', ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement'])
                ->orWhere($awaitingPayment)),
        };
    }

    /**
     * The landlord's reservations with the search/property/date-range filters
     * applied — but NOT the status filter, because index() needs this query to
     * compute the per-status tab counts before narrowing to one status.
     * Shared by index() and export() so a CSV matches what the page shows.
     */
    private function filteredQuery(Request $request)
    {
        $base = Reservation::whereHas('property', function ($query) {
            $query->where('landlord_id', Auth::id());
        });

        if ($search = trim((string) $request->query('search'))) {
            $base->where(function ($query) use ($search) {
                $query->whereHas('tenant', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                })
                    ->orWhereHas('property', fn ($q) => $q->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('unit', fn ($q) => $q->where('unit_label', 'like', "%{$search}%"));
            });
        }

        if ($propertyId = $request->query('property')) {
            $base->where('property_id', $propertyId);
        }

        if ($from = $request->query('from')) {
            $base->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $base->whereDate('created_at', '<=', $to);
        }

        return $base;
    }

    public function index(Request $request)
    {
        $base = $this->filteredQuery($request);
        $stage = in_array($request->query('stage'), self::STAGES, true) ? $request->query('stage') : 'open';

        $counts = collect(self::STAGES)->mapWithKeys(fn ($s) => [$s => self::applyStage(clone $base, $s)->count()]);

        // The strip: whose turn it is. "Payment to confirm" = signed, awaiting payment, with a move-in report pending.
        $withReport = fn ($q) => $q->whereHas('paymentClaims', fn ($c) => $c->where('status', 'Pending')->where('claim_type', 'MoveIn'));
        $toConfirm = self::applyStage(clone $base, 'payment')->where($withReport);
        $strip = [
            'reply' => $counts['new'],
            'discussing' => $counts['discussing'],
            'waiting' => $counts['agreement'] + self::applyStage(clone $base, 'payment')->whereNot($withReport)->count(),
            'confirm' => (clone $toConfirm)->count(),
            'confirmAmount' => (float) \App\Models\PaymentClaim::where('status', 'Pending')->where('claim_type', 'MoveIn')
                ->whereIn('reservation_id', (clone $toConfirm)->select('reservation_id'))->sum('amount'),
        ];

        $reservations = self::applyStage($base, $stage)
            ->with(['property.media', 'property.landlord', 'unit', 'tenant', 'conversation', 'payments', 'paymentClaims'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $properties = Property::where('landlord_id', Auth::id())
            ->orderBy('title')
            ->get(['property_id', 'title']);

        // Badge on the Viewings tab: requests the landlord still has to answer.
        $pendingViewings = ViewingRequest::where('landlord_id', Auth::id())
            ->open()
            ->where('status', 'Pending')
            ->where('proposed_by', '!=', Auth::id())
            ->count();

        return view('landlord.reservations.index', compact('reservations', 'counts', 'stage', 'strip', 'properties', 'pendingViewings'));
    }

    /**
     * CSV of the currently filtered reservations, including the active
     * stage tab. Streamed and chunked to keep memory flat.
     */
    public function export(Request $request)
    {
        $filename = 'abangananhub-reservations-' . now()->format('Y-m-d') . '.csv';

        $stage = in_array($request->query('stage'), self::STAGES, true) ? $request->query('stage') : 'open';
        $query = self::applyStage($this->filteredQuery($request), $stage);

        $query->with(['tenant:user_id,first_name,last_name,email,contact_number',
            'property:property_id,title', 'unit:unit_id,unit_label']);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Tenant', 'Email', 'Contact', 'Property', 'Unit', 'Status',
                'Target Move In', 'Target Move Out', 'Occupants', 'Requested On',
            ]);

            $query->chunk(200, function ($reservations) use ($handle) {
                foreach ($reservations as $reservation) {
                    $tenant = $reservation->tenant;

                    fputcsv($handle, [
                        $tenant ? trim($tenant->first_name . ' ' . $tenant->last_name) : '',
                        $tenant->email ?? '',
                        $tenant->contact_number ?? '',
                        $reservation->property->title ?? '',
                        $reservation->unit->unit_label ?? '',
                        $reservation->rental_status,
                        optional($reservation->target_move_in_date)->format('Y-m-d'),
                        optional($reservation->target_move_out_date)->format('Y-m-d'),
                        $reservation->occupants_count ?? '',
                        optional($reservation->created_at)->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function reject(Request $request, Reservation $reservation)
    {
        Gate::authorize('reject', $reservation);

        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if (!$reservation->reject($request->rejection_reason)) {
            return back()->withErrors(['reservation' => $reservation->cancellationBlockedReason() ?? 'This reservation can no longer be rejected.']);
        }

        return back()->with('success', 'Reservation rejected.');
    }

    public function cancel(Reservation $reservation)
    {
        Gate::authorize('cancel', $reservation);

        if (!$reservation->cancel()) {
            return back()->withErrors(['reservation' => $reservation->cancellationBlockedReason() ?? 'This reservation can no longer be cancelled.']);
        }

        return back()->with('success', 'Reservation cancelled.');
    }

    public function advanceToNegotiation(Reservation $reservation)
    {
        Gate::authorize('advanceStatus', $reservation);

        if (!$reservation->advanceToNegotiation()) {
            return back()->withErrors(['reservation' => 'This reservation cannot move to negotiation right now.']);
        }

        $reservation->postSystemMessage(Auth::user()->name . ' accepted the inquiry and started negotiation.');

        return back()->with('success', 'Moved to Under Negotiation.');
    }

    /** Send the agreement with the final terms agreed in chat (SendAgreementRequest authorizes and validates). */
    public function advanceToPendingAgreement(SendAgreementRequest $request, Reservation $reservation)
    {
        if (!$reservation->advanceToPendingAgreement($request->validated())) {
            return back()->withErrors(['reservation' => 'This reservation cannot move to Pending Rental Agreement right now.']);
        }

        $reservation->update(['landlord_tc_accepted_at' => now()]);

        $reservation->postSystemMessage(Auth::user()->name . ' sent the rental agreement.');

        return back()->with('success', 'Agreement sent to tenant.');
    }

    /**
     * Landlord asserts the keys changed hands, starting the tenant's clock.
     *
     * Locked despite moving no money: it starts a countdown that ends in a
     * payout, and a double-click would otherwise post the system message twice.
     * See RULES.md → Concurrency & State Transitions.
     */
    public function markTurnedOver(Reservation $reservation)
    {
        Gate::authorize('markTurnedOver', $reservation);

        $marked = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->markKeysTurnedOver()) {
                return false;
            }

            $days = config('rentals.move_in_confirmation_days');

            $locked->postSystemMessage(
                "The landlord marked the keys as turned over. {$locked->tenant->name} has {$days} days to confirm move-in, after which the deposit is released automatically."
            );

            Notification::notify(
                $locked->tenant_id,
                'move_in_confirmation_due',
                'Confirm your move-in',
                "Your landlord marked the keys as turned over. Confirm your move-in within {$days} days to release your deposit.",
                route('agreements.show', $locked),
                $locked->conversation_id,
            );

            return true;
        });

        if (! $marked) {
            return back()->with('error', 'Turnover cannot be marked for this reservation right now.');
        }

        return back()->with('success', 'Keys marked as turned over. The tenant has been asked to confirm their move-in.');
    }

    /**
     * Landlord confirms a walk-in reserved for a future date has actually
     * moved in. No system message, no notification — unlike markTurnedOver(),
     * a walk-in has no conversation_id and its tenant account is an inactive
     * placeholder login (see Landlord\TenantController::store()), so
     * there is no thread or notifiable account to reach.
     */
    public function confirmWalkInMoveIn(Reservation $reservation)
    {
        Gate::authorize('confirmWalkInMoveIn', $reservation);

        $confirmed = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $locked->confirmWalkInMoveIn();
        });

        if (! $confirmed) {
            return back()->with('error', 'This reservation can no longer be confirmed as moved in.');
        }

        return back()->with('success', 'Move-in confirmed. The unit is now marked occupied.');
    }
}
