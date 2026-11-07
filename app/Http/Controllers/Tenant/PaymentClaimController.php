<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use App\Services\PaymentClaims;
use App\Support\PaymentTerms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The tenant's side of paying the landlord directly: report a payment, or take the report back. */
class PaymentClaimController extends Controller
{
    public function store(Request $request, Reservation $reservation)
    {
        Gate::authorize('fileClaim', $reservation);
        PaymentTerms::require($request);

        $data = $request->validate([
            'claim_type' => ['required', Rule::in(PaymentClaim::TYPES)],
            'billing_period' => ['nullable', 'required_if:claim_type,Monthly', 'date'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'payment_method' => ['required', Rule::in(PaymentClaim::METHODS)],
            'reference_no' => ['nullable', 'required_unless:payment_method,Cash', 'string', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'reference_no.required_unless' => 'Enter the reference number from your payment receipt. Your landlord uses it to find the payment.',
            'paid_on.before_or_equal' => 'The payment date cannot be in the future.',
        ]);

        PaymentClaims::file($reservation, $request->user(), $data);
        PaymentTerms::record($reservation, $data['payment_method'] === 'Cash' ? 'cash' : 'qr', $data['claim_type']);

        return back()->with('success', 'Sent to your landlord. You will be notified when they confirm it.');
    }

    /** A rejected report goes to an admin as a Report against the landlord. */
    public function dispute(Request $request, PaymentClaim $claim)
    {
        Gate::authorize('fileClaim', $claim->reservation);

        PaymentClaims::dispute($claim, $request->user());

        return back()->with('success', 'Sent to AbangananHub. An admin will review it.');
    }

    public function withdraw(Request $request, PaymentClaim $claim)
    {
        Gate::authorize('fileClaim', $claim->reservation);

        PaymentClaims::withdraw($claim, $request->user());

        return back()->with('success', 'Payment report withdrawn.');
    }
}
