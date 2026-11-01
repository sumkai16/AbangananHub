<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentRefundException;
use App\Services\PaymentRefunds;
use Illuminate\Http\Request;

/**
 * Give a tenant's held online payment back. Sits next to Admin\PaymentController::release: both end a held
 * payment, one toward the landlord and one toward the tenant. All the rules live in App\Services\PaymentRefunds;
 * this class only checks the form and turns a refusal into a response.
 */
class PaymentRefundController extends Controller
{
    public function store(Request $request, Payment $payment)
    {
        $data = $request->validate([
            // Also the note PayMongo keeps with the refund, whose limit is 255 characters.
            'refund_reason' => ['required', 'string', 'min:5', 'max:255'],
            'include_fee' => ['nullable', 'boolean'],
        ]);

        try {
            $result = PaymentRefunds::refund(
                $payment,
                $request->user(),
                $data['refund_reason'],
                (bool) ($data['include_fee'] ?? false),
            );
        } catch (PaymentRefundException $e) {
            // 409: someone else already moved this payment. Anything else: nothing changed, say why.
            abort_if($e->httpStatus === 409, 409, $e->getMessage());

            return back()->withInput()->with('error', $e->getMessage());
        }

        $amount = '₱'.number_format($result['centavos'] / 100, 2);

        return back()->with('success', $result['transfer_link']
            ? "Refund of {$amount} started. It is a QR Ph payment, so the tenant has to claim it with the link we sent them."
            : "Refunded {$amount} to the tenant. The reservation is cancelled and the unit is open again.");
    }
}
