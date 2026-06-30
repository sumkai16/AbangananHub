<?php
namespace App\Http\Controllers\Landlord;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $base = Reservation::whereHas('property', function ($query) {
            $query->where('landlord_id', Auth::id());
        });

        $counts = [
            'all'       => (clone $base)->count(),
            'Pending'   => (clone $base)->where('reservation_status', 'Pending')->count(),
            'Approved'  => (clone $base)->where('reservation_status', 'Approved')->count(),
            'Rejected'  => (clone $base)->where('reservation_status', 'Rejected')->count(),
            'Cancelled' => (clone $base)->where('reservation_status', 'Cancelled')->count(),
        ];

        $status = $request->query('status', 'all');
        if (in_array($status, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) {
            $base->where('reservation_status', $status);
        }

        $reservations = $base
            ->with(['property.media', 'property.units', 'unit', 'tenant'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('landlord.reservations.index', compact('reservations', 'counts', 'status'));
    }
public function approve(Reservation $reservation)
    {
        Gate::authorize('approve', $reservation);

        $hasCompetingApproved = Reservation::where('unit_id', $reservation->unit_id)
            ->where('reservation_id', '!=', $reservation->reservation_id)
            ->where('reservation_status', 'Approved')
            ->exists();

        if ($hasCompetingApproved) {
            return back()->withErrors(['reservation' => 'This unit already has an approved reservation. Cancel it first before approving another.']);
        }

        if (!$reservation->approve()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be approved.']);
        }

        $reservation->unit->update(['availability_status' => 'Reserved']);

        Reservation::where('unit_id', $reservation->unit_id)
            ->where('reservation_id', '!=', $reservation->reservation_id)
            ->where('reservation_status', 'Pending')
            ->update([
                'reservation_status' => 'Rejected',
                'rejection_reason'   => 'Another applicant was approved for this unit.',
            ]);

        return back()->with('success', 'Reservation approved and competing requests auto-declined.');
    }
    public function reject(Request $request, Reservation $reservation)
    {
        Gate::authorize('reject', $reservation);
        if (!$reservation->isPending()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be rejected.']);
        }
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);
        $reservation->update([
            'reservation_status' => 'Rejected',
            'rejection_reason'   => $request->rejection_reason,
        ]);
        return back()->with('success', 'Reservation rejected.');
    }
public function cancel(Reservation $reservation)
    {
        Gate::authorize('cancel', $reservation);

        $wasApproved = $reservation->isApproved();

        if (!$reservation->cancel()) {
            return back()->withErrors(['reservation' => 'This reservation can no longer be cancelled.']);
        }

        if ($wasApproved && $reservation->unit_id) {
            $otherApprovedExists = Reservation::where('unit_id', $reservation->unit_id)
                ->where('reservation_id', '!=', $reservation->reservation_id)
                ->where('reservation_status', 'Approved')
                ->exists();

            if (!$otherApprovedExists) {
                $reservation->unit->update(['availability_status' => 'Available']);
            }
        }

        return back()->with('success', 'Reservation cancelled.');
    }
}