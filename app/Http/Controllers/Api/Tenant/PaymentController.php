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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
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

        if (! Payments::online()) {
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

        $placeholder = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->first();

            // Re-checked under the lock: the checks above ran before it, on a row that may have moved on.
            if ($locked->rental_status !== 'Rental Agreement Signed') {
                return 'Agreement must be signed before payment.';
            }

            if ($locked->hasSettledInitialPayment()) {
                return 'The initial payment for this reservation has already been made.';
            }

            if ($locked->unitHeldByAnother(['Rental Agreement Signed', 'Reserved', 'Occupied'])) {
                return 'This unit is no longer available. Please contact your landlord.';
            }

            // Any Pending row blocks, session id or not: a placeholder still waiting for its id is a live click.
            // Stale ones were cleared by clearStaleCheckouts() above.
            $existingPending = Payment::where('reservation_id', $locked->reservation_id)
                ->where('payment_type', 'Initial')
                ->where('status', 'Pending')
                ->exists();

            if ($existingPending) {
                return 'A payment session is already in progress.';
            }

            return Payment::create([
                'reservation_id' => $locked->reservation_id,
                'payment_type'   => 'Initial',
                'amount'         => $locked->monthlyRent(),
                'payment_method' => 'GCash',
                'status'         => 'Pending',
            ]);
        });

        if (is_string($placeholder)) {
            throw ValidationException::withMessages(['payment' => [$placeholder]]);
        }

        $amount = Payment::toCentavos($reservation->monthlyRent());

        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'send_email_receipt'  => false,
                        'show_description'    => true,
                        'show_line_items'     => true,
                        'description'         => 'Initial rental payment for '.$reservation->unit->unit_name,
                        'line_items'          => [[
                            'currency' => 'PHP',
                            'amount'   => $amount,
                            'name'     => $reservation->unit->unit_name.' — Initial Payment',
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => ['gcash', 'qrph'],
                        // No web route to redirect to — the app intercepts the
                        // WebView's navigation to this URL instead of loading it.
                        'success_url' => route('payments.success', $reservation),
                        'cancel_url'  => route('agreements.show', $reservation),
                    ],
                ],
            ]);

        if ($response->failed()) {
            $placeholder->delete();

            throw ValidationException::withMessages(['payment' => ['Could not create payment session. Please try again.']]);
        }

        $checkoutData = $response->json('data');

        $placeholder->update(['paymongo_checkout_session_id' => $checkoutData['id']]);

        return response()->json([
            'data' => [
                'checkout_url' => $checkoutData['attributes']['checkout_url'],
                'payment'      => new PaymentResource($placeholder->fresh()),
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

        if (! Payments::online()) {
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

        $placeholder = DB::transaction(function () use ($reservation, $billingPeriod, $balance) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->first();

            if ($locked->rental_status !== 'Occupied') {
                return null;
            }

            $existingPending = Payment::where('reservation_id', $locked->reservation_id)
                ->where('payment_type', 'Monthly')
                ->whereDate('billing_period', $billingPeriod->toDateString())
                ->where('status', 'Pending')
                ->exists();

            if ($existingPending) {
                return null;
            }

            return Payment::create([
                'reservation_id' => $locked->reservation_id,
                'payment_type'   => 'Monthly',
                'billing_period' => $billingPeriod,
                'amount'         => $balance,
                'payment_method' => 'GCash',
                'status'         => 'Pending',
            ]);
        });

        if (! $placeholder) {
            throw ValidationException::withMessages(['payment' => ['A payment session is already in progress, or this tenancy is no longer active.']]);
        }

        $amount = Payment::toCentavos($balance);

        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'send_email_receipt'  => false,
                        'show_description'    => true,
                        'show_line_items'     => true,
                        'description'         => $reservation->unit->unit_name.' — Rent for '.$period['label'],
                        'line_items'          => [[
                            'currency' => 'PHP',
                            'amount'   => $amount,
                            'name'     => $reservation->unit->unit_name.' — Rent ('.$period['label'].')'.($period['late_fee'] > 0 ? ' + late fee' : ''),
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => ['gcash', 'qrph'],
                        'success_url' => route('payments.rent.success', $reservation),
                        'cancel_url'  => route('tenancy.show', $reservation),
                    ],
                ],
            ]);

        if ($response->failed()) {
            $placeholder->delete();

            throw ValidationException::withMessages(['payment' => ['Could not create payment session. Please try again.']]);
        }

        $checkoutData = $response->json('data');

        $placeholder->update(['paymongo_checkout_session_id' => $checkoutData['id']]);

        return response()->json([
            'data' => [
                'checkout_url' => $checkoutData['attributes']['checkout_url'],
                'payment'      => new PaymentResource($placeholder->fresh()),
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
