<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
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

        // Prevent duplicate pending payments for the same reservation
        $existingPending = Payment::where('reservation_id', $reservation->reservation_id)
            ->where('status', 'Pending')
            ->whereNotNull('paymongo_checkout_session_id')
            ->first();

        if ($existingPending) {
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
            return back()->withErrors(['payment' => 'Could not create payment session. Please try again.']);
        }

        $checkoutData = $response->json('data');

        Payment::create([
            'reservation_id' => $reservation->reservation_id,
            'payment_type' => 'Initial',
            'amount' => $reservation->unit->rental_fee,
            'payment_method' => 'GCash',
            'paymongo_checkout_session_id' => $checkoutData['id'],
            'status' => 'Pending',
        ]);

        return redirect($checkoutData['attributes']['checkout_url']);
    }

    public function success(Reservation $reservation)
    {
        Gate::authorize('sign', $reservation);

        $reservation->load(['unit', 'tenant', 'payments' => function ($q) {
            $q->latest('payment_id')->limit(1);
        }]);

        $latestPayment = $reservation->payments->first();

        return view('payments.pending', compact('reservation', 'latestPayment'));
    }
}