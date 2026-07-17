<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function createCheckoutSession(Reservation $reservation)
    {
        Gate::authorize('sign', $reservation);

        if ($reservation->rental_status !== 'Rental Agreement Signed') {
            return back()->withErrors(['payment' => 'Agreement must be signed before payment.']);
        }

        if (!$reservation->unit) {
            return back()->withErrors(['payment' => 'This unit is no longer available. Please contact your landlord.']);
        }

        // Lock the reservation row so two concurrent requests (e.g. a double
        // click) can't both pass the "no pending payment" check before either
        // has inserted its Payment row.
        $placeholder = DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->reservation_id)->lockForUpdate()->first();

            $existingPending = Payment::where('reservation_id', $locked->reservation_id)
                ->where('status', 'Pending')
                ->whereNotNull('paymongo_checkout_session_id')
                ->exists();

            if ($existingPending) {
                return null;
            }

            // Placeholder row reserves this reservation for the current
            // request until it's updated with the PayMongo session id below.
            return Payment::create([
                'reservation_id' => $locked->reservation_id,
                'payment_type' => 'Initial',
                'amount' => $locked->unit->rental_fee,
                'payment_method' => 'GCash',
                'status' => 'Pending',
            ]);
        });

        if (!$placeholder) {
            return back()->withErrors(['payment' => 'A payment session is already in progress.']);
        }

        $amount = (int) ($reservation->unit->rental_fee * 100); // PayMongo expects cents

        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'description' => 'Initial rental payment for ' . $reservation->unit->unit_name,
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => $amount,
                                'name' => $reservation->unit->unit_name . ' — Initial Payment',
                                'quantity' => 1,
                            ],
                        ],
                        'payment_method_types' => ['gcash'],
                        'success_url' => route('payments.success', $reservation),
                        'cancel_url' => route('agreements.show', $reservation),
                    ],
                ],
            ]);

        if ($response->failed()) {
            // Free up the reservation for a retry instead of leaving a dead placeholder behind.
            $placeholder->delete();

            return back()->withErrors(['payment' => 'Could not create payment session. Please try again.']);
        }

        $checkoutData = $response->json('data');

        $placeholder->update([
            'paymongo_checkout_session_id' => $checkoutData['id'],
        ]);

        return redirect($checkoutData['attributes']['checkout_url']);
    }

public function success(Reservation $reservation)
{
    Gate::authorize('sign', $reservation);

    $reservation->load(['unit', 'tenant']);

    $latestPayment = Payment::where('reservation_id', $reservation->reservation_id)
        ->latest('payment_id')
        ->first();

    // If webhook hasn't fired yet, check PayMongo directly
    if ($latestPayment && $latestPayment->status === 'Pending' && $latestPayment->paymongo_checkout_session_id) {
        $response = Http::withBasicAuth(config('services.paymongo.secret_key'), '')
            ->get("https://api.paymongo.com/v1/checkout_sessions/{$latestPayment->paymongo_checkout_session_id}");

        if ($response->ok()) {
            $sessionStatus = $response->json('data.attributes.status');
            $payments = $response->json('data.attributes.payments') ?? [];

            if ($sessionStatus === 'paid' || count($payments) > 0) {
                $paymongoPaymentId = $payments[0]['id'] ?? null;
                $paymentIntentId = $response->json('data.attributes.payment_intent.id');

                $latestPayment->update([
                    'status' => 'Held',
                    'paymongo_payment_intent_id' => $paymentIntentId,
                    'paymongo_payment_id' => $paymongoPaymentId,
                    'paid_at' => now(),
                ]);

                $reservation->postSystemMessage($reservation->tenant->name . ' completed the initial payment. Funds are held by AbangananHub.');
            }
        }
    }

    return view('payments.pending', compact('reservation', 'latestPayment'));
}
}