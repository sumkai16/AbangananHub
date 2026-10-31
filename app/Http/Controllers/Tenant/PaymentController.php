<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\ReconcilesPaymongoCheckout;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymongoCheckout;
use App\Services\RentLedger;
use App\Support\PaymentTerms;
use App\Support\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    use ReconcilesPaymongoCheckout;

    public function createCheckoutSession(Request $request, Reservation $reservation)
    {
        Gate::authorize('sign', $reservation);
        PaymentTerms::require($request);

        if (! Payments::onlineFor($reservation)) {
            return back()->withErrors(['payment' => Payments::OFFLINE_MESSAGE]);
        }

        if ($reservation->rental_status !== 'Rental Agreement Signed') {
            return back()->withErrors(['payment' => 'Agreement must be signed before payment.']);
        }

        if (!$reservation->unit) {
            return back()->withErrors(['payment' => 'This unit is no longer available. Please contact your landlord.']);
        }

        if ($reservation->hasSettledInitialPayment()) {
            return back()->withErrors(['payment' => 'The initial payment for this reservation has already been made.']);
        }

        if ($message = $this->clearStaleCheckouts($reservation, 'Initial')) {
            return back()->withErrors(['payment' => $message]);
        }

        [, $checkoutUrl] = $this->openCheckout(
            $reservation,
            'Initial',
            null,
            (float) $reservation->monthlyRent(),
            rentLineName: $reservation->unit->unit_name . ' — First month’s rent',
            description: 'Move-in payment for ' . $reservation->unit->unit_name,
            successUrl: route('payments.success', $reservation),
            cancelUrl: route('agreements.show', $reservation),
            busyMessage: 'A payment session is already in progress.',
        );

        PaymentTerms::record($reservation, 'online', 'MoveIn');

        return redirect($checkoutUrl);
    }

    /**
     * The tenant closed the PayMongo page and is stuck on "Payment processing": take them back to the same checkout.
     * A checkout that was in fact paid is settled instead, and one that expired is cleared so they can choose again.
     */
    public function continueCheckout(Request $request, Reservation $reservation)
    {
        [$type, $payment] = $this->openCheckoutFor($request, $reservation);

        if (! $payment) {
            return back()->withErrors(['payment' => 'There is no open payment to continue. You can choose how to pay again.']);
        }

        $reservation->loadMissing('tenant');

        if ($this->reconcilePayment($reservation, $payment)->status !== 'Pending') {
            return redirect()->route($type === 'Initial' ? 'payments.success' : 'payments.rent.success', $reservation);
        }

        $session = PaymongoCheckout::session($payment->paymongo_checkout_session_id);

        if ($session === null) {
            return back()->withErrors(['payment' => 'We could not reach PayMongo just now. Please try again in a moment.']);
        }

        if ($session['status'] === 'active' && filled($session['checkout_url'])) {
            return redirect()->away($session['checkout_url']);
        }

        // Expired (or otherwise closed): clear it so the tenant can start fresh.
        if ($message = $this->abandonCheckout($reservation, $payment)) {
            return back()->withErrors(['payment' => $message]);
        }

        return back()->with('warning', 'That payment window has closed. Nothing was charged. Choose how to pay again.');
    }

    /** The tenant gives up an open online checkout on purpose, to pay another way. */
    public function cancelCheckout(Request $request, Reservation $reservation)
    {
        [, $payment] = $this->openCheckoutFor($request, $reservation);

        if (! $payment) {
            return back()->with('warning', 'There is no open payment to cancel.');
        }

        if ($message = $this->abandonCheckout($reservation, $payment)) {
            return back()->withErrors(['payment' => $message]);
        }

        return back()->with('success', 'Payment cancelled. Nothing was charged. Choose how to pay again.');
    }

    /** @return array{string, ?Payment} the payment type asked for (authorized for) and its open checkout, if any */
    private function openCheckoutFor(Request $request, Reservation $reservation): array
    {
        $type = $request->input('type') === 'Monthly' ? 'Monthly' : 'Initial';

        Gate::authorize($type === 'Initial' ? 'sign' : 'payRent', $reservation);

        return [$type, $this->findOpenCheckout($reservation, $type)];
    }

    public function success(Reservation $reservation)
    {
        Gate::authorize('sign', $reservation);

        $reservation->load(['unit', 'tenant']);

        // The Initial row is the lead of the checkout; the Deposit row is created after it and must not be shown as the payment.
        $latestPayment = Payment::where('reservation_id', $reservation->reservation_id)
            ->where('payment_type', 'Initial')
            ->latest('payment_id')
            ->first();

        $latestPayment = $this->reconcilePayment($reservation, $latestPayment);

        return view('payments.pending', compact('reservation', 'latestPayment'));
    }

    /**
     * Rent payment checkout — settles the tenant's single earliest unsettled
     * billing period, direct to 'Paid'. No escrow: unlike the initial payment,
     * there is no handover left to protect once a tenant is already occupying
     * the unit.
     */
    public function createRentCheckoutSession(Request $request, Reservation $reservation)
    {
        Gate::authorize('payRent', $reservation);
        PaymentTerms::require($request);

        if (! Payments::onlineFor($reservation)) {
            return back()->withErrors(['payment' => Payments::OFFLINE_MESSAGE]);
        }

        $reservation->loadMissing('unit', 'tenant');

        if (!$reservation->unit) {
            return back()->withErrors(['payment' => 'This unit is no longer available. Please contact your landlord.']);
        }

        $period = RentLedger::for($reservation)->unsettledPeriods()->first();

        if (!$period) {
            return back()->with('success', 'Your rent is already fully paid.');
        }

        $billingPeriod = $period['period']->copy()->startOfMonth();
        $balance = round((float) $period['balance'], 2);

        if ($message = $this->clearStaleCheckouts($reservation, 'Monthly', $billingPeriod)) {
            return back()->withErrors(['payment' => $message]);
        }

        [, $checkoutUrl] = $this->openCheckout(
            $reservation,
            'Monthly',
            $billingPeriod,
            $balance,
            rentLineName: $reservation->unit->unit_name . ' — Rent (' . $period['label'] . ')' . ($period['late_fee'] > 0 ? ' + late fee' : ''),
            description: $reservation->unit->unit_name . ' — Rent for ' . $period['label'],
            successUrl: route('payments.rent.success', $reservation),
            cancelUrl: route('tenancy.show', $reservation),
            busyMessage: 'A payment session is already in progress, or this tenancy is no longer active.',
        );

        PaymentTerms::record($reservation, 'online', 'Monthly');

        return redirect($checkoutUrl);
    }

    public function rentSuccess(Reservation $reservation)
    {
        Gate::authorize('payRent', $reservation);

        $reservation->load(['unit', 'tenant']);

        $latestPayment = Payment::where('reservation_id', $reservation->reservation_id)
            ->where('payment_type', 'Monthly')
            ->latest('payment_id')
            ->first();

        $latestPayment = $this->reconcilePayment($reservation, $latestPayment);

        return view('payments.pending', [
            'reservation' => $reservation,
            'latestPayment' => $latestPayment,
            'returnRoute' => route('tenancy.show', $reservation),
        ]);
    }
}
