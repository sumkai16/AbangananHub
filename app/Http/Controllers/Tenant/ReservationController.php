<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Conversation;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PropertyUnit;
class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $base = Reservation::where('tenant_id', Auth::id());

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
        $validStatuses = ['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied', 'Cancelled', 'Rejected'];

        if (in_array($status, $validStatuses, true)) {
            $base->where('rental_status', $status);
        }

        $reservations = $base
            ->with(['property.media', 'property.landlord', 'unit', 'tenant', 'conversation'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('tenant.reservations.index', compact('reservations', 'counts', 'status'));
    }

public function store(StoreReservationRequest $request)
    {
        $unit = PropertyUnit::where('unit_id', $request->unit_id)
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->first();

        if (!$unit) {
            return back()->withInput()->with('error', 'This unit is no longer available.');
        }

        $property = $unit->property;

        if ($property->landlord_id === Auth::id()) {
            return back()->withInput()->with('error', 'You cannot inquire on your own listing.');
        }

        if ($property->verification_status !== 'Approved') {
            return back()->withInput()->with('error', 'This property is not currently available for inquiries.');
        }

        // Check for existing active reservation on this unit by this tenant
        $exists = Reservation::where('unit_id', $unit->unit_id)
            ->where('tenant_id', Auth::id())
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
            ->exists();

        if ($exists) {
            return back()->with('warning', 'You already have an active inquiry or reservation for this unit.');
        }

        $conversation = Conversation::where('tenant_id', Auth::id())
            ->where('landlord_id', $property->landlord_id)
            ->where('property_id', $property->property_id)
            ->where('status', '!=', 'Cancelled')
            ->first();

        if ($conversation) {
            // Existing live conversation — update unit if it changed
            if ($conversation->unit_id !== $unit->unit_id) {
                $conversation->update(['unit_id' => $unit->unit_id]);
            }
        } else {
            // No live conversation exists (either none ever existed, or the
            // prior one was cancelled) — start a fresh one.
            $conversation = Conversation::create([
                'tenant_id'   => Auth::id(),
                'landlord_id' => $property->landlord_id,
                'property_id' => $property->property_id,
                'unit_id'     => $unit->unit_id,
            ]);
        }

       Reservation::create([
            'property_id'          => $property->property_id,
            'unit_id'              => $unit->unit_id,
            'tenant_id'            => Auth::id(),
            'conversation_id'      => $conversation->conversation_id,
            'reservation_date'     => now(),
            'rental_status'        => 'Inquiry',
            'target_move_in_date'  => $request->target_move_in_date,
            'target_move_out_date' => $request->target_move_out_date,
        ]);

        // Send optional first message
        if ($request->filled('message')) {
            $conversation->messages()->create([
                'sender_id' => Auth::id(),
                'message'   => $request->message,
            ]);
        }

        return redirect()->route('conversations.show', $conversation)
            ->with('success', 'Inquiry started — discuss the details with your landlord.');
    }

    public function cancel(Reservation $reservation)
    {
        if ($reservation->tenant_id !== Auth::id()) {
            abort(403);
        }

        if (!$reservation->cancel()) {
            return back()->with('error', 'This reservation can no longer be cancelled.');
        }

        // Reservation::cancel() already releases the unit via releaseUnit().

        return back()->with('success', 'Reservation cancelled.');
    }
}