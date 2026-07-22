<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    /** Statuses the index tabs and the export both accept. */
    private const VALID_STATUSES = [
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement',
        'Rental Agreement Signed', 'Occupied', 'Cancelled', 'Rejected',
    ];

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
                    ->orWhereHas('property', fn($q) => $q->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('unit', fn($q) => $q->where('unit_label', 'like', "%{$search}%"));
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

        $counts = [
            'all'                       => (clone $base)->count(),
            'Inquiry'                   => (clone $base)->where('rental_status', 'Inquiry')->count(),
            'Under Negotiation'         => (clone $base)->where('rental_status', 'Under Negotiation')->count(),
            'Pending Rental Agreement'  => (clone $base)->where('rental_status', 'Pending Rental Agreement')->count(),
            'Rental Agreement Signed'   => (clone $base)->where('rental_status', 'Rental Agreement Signed')->count(),
            'Occupied'                  => (clone $base)->where('rental_status', 'Occupied')->count(),
            'Cancelled'                 => (clone $base)->where('rental_status', 'Cancelled')->count(),
            'Rejected'                  => (clone $base)->where('rental_status', 'Rejected')->count(),
        ];

        $status = $request->query('status', 'all');

        if (in_array($status, self::VALID_STATUSES, true)) {
            $base->where('rental_status', $status);
        }

        $reservations = $base
            ->with(['property.media', 'property.landlord', 'unit', 'tenant', 'conversation', 'tenantRating'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $properties = Property::where('landlord_id', Auth::id())
            ->orderBy('title')
            ->get(['property_id', 'title']);

        return view('landlord.reservations.index', compact('reservations', 'counts', 'status', 'properties'));
    }

    /**
     * CSV of the currently filtered reservations, including the active
     * status tab. Streamed and chunked to keep memory flat.
     */
    public function export(Request $request)
    {
        $filename = 'abangananhub-reservations-' . now()->format('Y-m-d') . '.csv';

        $query = $this->filteredQuery($request);

        $status = $request->query('status', 'all');
        if (in_array($status, self::VALID_STATUSES, true)) {
            $query->where('rental_status', $status);
        }

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
            return back()->withErrors(['reservation' => 'This reservation can no longer be rejected.']);
        }

        // Release unit if it was reserved
        if ($reservation->unit && $reservation->unit->availability_status === 'Reserved') {
            $reservation->unit->update(['availability_status' => 'Available']);
        }

        return back()->with('success', 'Reservation rejected.');
    }

    public function cancel(Reservation $reservation)
    {
        Gate::authorize('cancel', $reservation);

        $unitWasReserved = $reservation->unit && $reservation->unit->availability_status === 'Reserved';

        if (!$reservation->cancel()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be cancelled.']);
        }

        if ($unitWasReserved) {
            $otherActiveExists = Reservation::where('unit_id', $reservation->unit_id)
                ->where('reservation_id', '!=', $reservation->reservation_id)
                ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
                ->exists();

            if (!$otherActiveExists) {
                $reservation->unit->update(['availability_status' => 'Available']);
            }
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

public function advanceToPendingAgreement(Request $request, Reservation $reservation)
{
    Gate::authorize('advanceStatus', $reservation);

    $request->validate([
        'agreement_terms_notes' => 'nullable|string|max:2000',
        'accept_tc' => 'accepted',
    ], [
        'accept_tc.accepted' => 'You must accept the terms and conditions to proceed.',
    ]);

    if (!$reservation->advanceToPendingAgreement($request->agreement_terms_notes)) {
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
}