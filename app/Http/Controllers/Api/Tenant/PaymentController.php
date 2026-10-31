<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Concerns\ReconcilesPaymongoCheckout;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\RentLedger;
use App\Support\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Mobile equivalent of Tenant\PaymentController. Both createCheckoutSession
 * endpoints mirror the web ones exactly (same locking, same placeholder
 * pattern) but return the checkout_url as JSON instead of redirecting to it —
 * the client opens it in a WebView and intercepts the return to success_url
 * rather than letting the WebView navigate there, per plans/mobile-app.md.
 * Reconciliation is the same trait the web return-handler uses, not a
 * second implementation of the money-settling logic.
 */
class PaymentController extends Controller
{
    use ReconcilesPaymongoCheckout;

    public function pay(Reservation $reservation): JsonResponse
    {
        Gate::authorize('sign', $reservation);

        if (! Payments::onlineFor($reservation)) {
            return response()->json(['message' => Payments::OFFLINE_MESSAGE], 409);
        }

        if ($reservation->rental_status !== 'Rental Agreement Signed') {
            throw ValidationException::withMessages(['payment' => ['Agreement must be signed before payment.']]);
        }

        if (! $reservation->unit) {
            throw ValidationException::withMessages(['payment' => ['This unit is no longer available. Please contact your landlord.']]);
        }

        if ($reservation->hasSettledInitialPayment()) {
            throw ValidationException::withMessages(['payment' => ['The initial payment for this reservation has already been made.']]);
        }

        if ($message = $this->clearStaleCheckouts($reservation, 'Initial')) {
            throw ValidationException::withMessages(['payment' => [$message]]);
        }

        [$lead, $checkoutUrl] = $this->openCheckout(
            $reservation,
            'Initial',
            null,
            (float) $reservation->monthlyRent(),
            rentLineName: $reservation->unit->unit_name.' — First month’s rent',
            description: 'Move-in payment for '.$reservation->unit->unit_name,
            // No web route to redirect to — the app intercepts the WebView's navigation to success_url instead of loading it.
            successUrl: route('payments.success', $reservation),
            cancelUrl: route('agreements.show', $reservation),
            busyMessage: 'A payment session is already in progress.',
        );

        return response()->json([
            'data' => [
                'checkout_url' => $checkoutUrl,
                'payment'      => new PaymentResource($lead->fresh()),
            ],
        ]);
    }

    /**
     * Called after the WebView intercepts success_url. PayMongo can't reach
     * localhost, so the webhook may not have landed yet — this is the exact
     * poll-fallback the web success() page runs, shared via the trait.
     */
    public function reconcile(Reservation $reservation): JsonResponse
    {
        Gate::authorize('sign', $reservation);

        $reservation->loadMissing(['unit', 'tenant']);

        $latestPayment = Payment::where('reservation_id', $reservation->reservation_id)
            ->where('payment_type', 'Initial')
            ->latest('payment_id')
            ->first();

        $latestPayment = $this->reconcilePayment($reservation, $latestPayment);

        return response()->json([
            'data' => $latestPayment ? new PaymentResource($latestPayment) : null,
        ]);
    }

    public function payRent(Reservation $reservation): JsonResponse
    {
        Gate::authorize('payRent', $reservation);

        if (! Payments::onlineFor($reservation)) {
            return response()->json(['message' => Payments::OFFLINE_MESSAGE], 409);
        }

        $reservation->loadMissing('unit', 'tenant');

        if (! $reservation->unit) {
            throw ValidationException::withMessages(['payment' => ['This unit is no longer available. Please contact your landlord.']]);
        }

        $period = RentLedger::for($reservation)->unsettledPeriods()->first();

        if (! $period) {
            return response()->json(['message' => 'Your rent is already fully paid.']);
        }

        $billingPeriod = $period['period']->copy()->startOfMonth();
        $balance = round((float) $period['balance'], 2);

        if ($message = $this->clearStaleCheckouts($reservation, 'Monthly', $billingPeriod)) {
            throw ValidationException::withMessages(['payment' => [$message]]);
        }

        [$lead, $checkoutUrl] = $this->openCheckout(
            $reservation,
            'Monthly',
            $billingPeriod,
            $balance,
            rentLineName: $reservation->unit->unit_name.' — Rent ('.$period['label'].')'.($period['late_fee'] > 0 ? ' + late fee' : ''),
            description: $reservation->unit->unit_name.' — Rent for '.$period['label'],
            successUrl: route('payments.rent.success', $reservation),
            cancelUrl: route('tenancy.show', $reservation),
            busyMessage: 'A payment session is already in progress, or this tenancy is no longer active.',
        );

        return response()->json([
            'data' => [
                'checkout_url' => $checkoutUrl,
                'payment'      => new PaymentResource($lead->fresh()),
            ],
        ]);
    }

    public function reconcileRent(Reservation $reservation): JsonResponse
    {
        Gate::authorize('payRent', $reservation);

        $reservation->loadMissing(['unit', 'tenant']);

        $latestPayment = Payment::where('reservation_id', $reservation->reservation_id)
            ->where('payment_type', 'Monthly')
            ->latest('payment_id')
            ->first();

        $latestPayment = $this->reconcilePayment($reservation, $latestPayment);

        return response()->json([
            'data' => $latestPayment ? new PaymentResource($latestPayment) : null,
        ]);
    }
}
