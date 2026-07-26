<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

/**
 * Mobile equivalent of Landlord\PayoutController — read-only, mirrors the web
 * page exactly. See docs/specs/2026-07-26-landlord-payout-design.md.
 */
class PayoutController extends Controller
{
    public function index(): JsonResponse
    {
        $landlordId = auth()->id();

        $query = Payment::whereHas(
            'reservation.property',
            fn ($q) => $q->where('landlord_id', $landlordId)
        )->with(['reservation.tenant', 'reservation.property:property_id,title', 'reservation.unit:unit_id,unit_label']);

        $pending = (clone $query)->where('payout_status', 'Pending Payout')
            ->latest('released_at')
            ->get();

        $paidOutTotal = (clone $query)->where('payout_status', 'Paid Out')->sum('amount');

        $paidOut = (clone $query)->where('payout_status', 'Paid Out')
            ->latest('paid_out_at')
            ->paginate(15);

        $paidOut->getCollection()->transform(fn (Payment $p) => (new PaymentResource($p))->resolve());

        return response()->json([
            'pending'              => PaymentResource::collection($pending),
            'pending_total'        => $pending->sum('amount'),
            'paid_out'             => $paidOut,
            'paid_out_total'       => $paidOutTotal,
            'has_payout_destination' => auth()->user()->hasPayoutDestination(),
        ]);
    }
}
