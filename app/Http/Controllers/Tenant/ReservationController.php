<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /**
     * The landlord recorded that they kept money the tenant paid straight to them, and the tenant disagrees: send it to
     * AbangananHub as a "Refund not returned" report against the landlord. AbangananHub never held this money, so the
     * admin cannot refund it; the admin decides what to do about the landlord's account or listing. Once per
     * reservation (a filled `refund_report_id` is the mark).
     */
    public function escalateRefund(Reservation $reservation)
    {
        abort_unless($reservation->tenant_id === Auth::id(), 403);

        $result = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->with(['property', 'tenant'])->lockForUpdate()->firstOrFail();

            if ($locked->refund_report_id !== null) {
                return ['warning' => 'You already sent this to AbangananHub.'];
            }

            $kept = $locked->keptDirectAmount();

            if ($kept <= 0) {
                return ['warning' => 'Your landlord has not recorded keeping any of your payment.'];
            }

            $landlordId = $locked->property?->landlord_id;
            $note = filled($locked->move_in_refund_note) ? ' Their reason: "'.$locked->move_in_refund_note.'".' : '';

            $report = Report::create([
                'reporter_id' => Auth::id(),
                'property_id' => $locked->property_id,
                'reported_user_id' => $landlordId,
                'report_reason' => 'Refund not returned: tenant paid straight to the landlord and the landlord recorded keeping ₱'
                    .number_format($kept, 2).'.'.$note.' Reservation #'.$locked->reservation_id.'.',
                'report_status' => 'Pending',
            ]);

            $locked->forceFill(['refund_report_id' => $report->report_id])->save();

            // Asking AbangananHub to look at the money is also asking an admin to read the thread it happened in.
            $locked->conversation?->requestAdminReview(Auth::user());

            AuditLog::record('refund.escalate', '₱'.number_format($kept, 2).' kept by the landlord sent to admin', $locked, null, [
                'reservation_id' => $locked->reservation_id, 'report_id' => $report->report_id,
            ]);

            User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))->pluck('user_id')->each(fn ($adminId) => Notification::notify(
                $adminId,
                'payment',
                'Refund not returned',
                ($locked->tenant->first_name ?? 'A tenant').' says their landlord kept ₱'.number_format($kept, 2).' paid directly, and asked AbangananHub to look at it.',
                route('admin.refund-requests.index'),
                $locked->conversation_id,
            ));

            Notification::notify($landlordId, 'payment', 'Payment sent for review',
                ($locked->tenant->first_name ?? 'Your tenant').' asked AbangananHub to review the ₱'.number_format($kept, 2).' you kept.',
                route('landlord.reservations.index'), $locked->conversation_id);

            return ['success' => 'Sent to AbangananHub. We will review it and the landlord\'s account.'];
        });

        return back()->with(array_key_first($result), reset($result));
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
