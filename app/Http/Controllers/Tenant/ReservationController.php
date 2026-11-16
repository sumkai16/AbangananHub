<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $base = Reservation::where('tenant_id', Auth::id());

        $counts = Reservation::statusCounts($base, Reservation::STATUSES);

        $status = $request->query('status', 'all');

        if (in_array($status, Reservation::STATUSES, true)) {
            $base->where('rental_status', $status);
        }

        $reservations = $base
            ->with(['property.media', 'property.landlord', 'unit', 'tenant', 'conversation', 'payments', 'paymentClaims'])
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

        if (! $property->isLive()) {
            return back()->withInput()->with('error', 'This property is not currently available for inquiries.');
        }

        $reservation = Reservation::startInquiry(
            $unit,
            Auth::id(),
            $request->only(['target_move_in_date', 'target_move_out_date', 'occupants_count']),
            $request->message ?? '',
        );

        // Null: this tenant already has an open inquiry or reservation on this unit.
        if (! $reservation) {
            return back()->with('warning', 'You already have an active inquiry or reservation for this unit.');
        }

        return redirect()->route('conversations.show', $reservation->conversation)
            ->with('success', 'Inquiry started — discuss the details with your landlord.');
    }

    /** The tenant asks their landlord to return money they paid directly on a reservation that ended. Once. */
    public function requestRefund(Reservation $reservation)
    {
        abort_unless($reservation->tenant_id === Auth::id(), 403);

        $amount = $reservation->moneyToSettle();

        if ($amount <= 0 || $reservation->move_in_refund_requested_at !== null) {
            return back()->with('warning', 'There is nothing to request, or you already asked.');
        }

        $reservation->forceFill(['move_in_refund_requested_at' => now()])->save();

        Notification::notify($reservation->property->landlord_id, 'payment', 'Tenant asked for a refund',
            ($reservation->tenant->first_name ?? 'The tenant').' asked you to return the ₱'.number_format($amount, 2).' they paid. Record whether you returned or kept it.',
            route('landlord.reservations.index'), $reservation->conversation_id);

        return back()->with('success', 'Your landlord was asked to return your payment.');
    }

    public function cancel(Reservation $reservation)
    {
        if ($reservation->tenant_id !== Auth::id()) {
            abort(403);
        }

        if (!$reservation->cancel()) {
            return back()->with('error', $reservation->cancellationBlockedReason() ?? 'This reservation can no longer be cancelled.');
        }

        // Reservation::cancel() already releases the unit via releaseUnit().

        return back()->with('success', 'Reservation cancelled.');
    }
}
