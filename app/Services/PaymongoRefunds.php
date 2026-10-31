<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The one PayMongo call that gives a tenant's money back: POST /v1/refunds.
 *
 * Fails closed, like PaymongoCheckout: when PayMongo cannot be reached or answers anything but a refund, the
 * result says so and the caller must treat the money as NOT refunded. Kept apart from the controller so tests
 * can fake the HTTP layer and so the refund rules live in PaymentRefunds, not in HTTP plumbing.
 */
class PaymongoRefunds
{
    /** PayMongo allows no refund under ₱1.00. */
    public const MIN_CENTAVOS = 100;

    /** Plain-words versions of the PayMongo error codes an admin is likely to meet. */
    private const MESSAGES = [
        'allowed_date_exceeded' => 'PayMongo no longer allows refunding this payment because its refund window has passed.',
        'available_balance_insufficient' => 'The PayMongo balance is too low to cover this refund right now. Try again after the next payout lands.',
        'resource_processing_state' => 'A refund for this payment is already being processed by PayMongo.',
        'resource_pending_state' => 'A refund for this payment is already waiting at PayMongo.',
        'resource_failed_state' => 'PayMongo says this payment was not completed, so it cannot be refunded.',
        'resource_not_found' => 'PayMongo does not know this payment.',
        'livemode_mismatched' => 'This payment was made in a different PayMongo mode (test or live) than the keys in use.',
        'parameter_above_maximum' => 'The refund amount is more than PayMongo will still refund on this payment.',
    ];

    /**
     * Ask PayMongo to refund $centavos of the payment $paymongoPaymentId (the `pay_...` id, not the checkout
     * session or the payment intent).
     *
     * @return array{ok: bool, id: ?string, status: ?string, transfer_link: ?string, message: ?string}
     */
    public static function create(string $paymongoPaymentId, int $centavos, string $note, bool $qrph): array
    {
        $url = $qrph
            ? config('services.paymongo.qrph_refunds_url')
            : config('services.paymongo.refunds_url');

        try {
            $response = Http::withBasicAuth((string) config('services.paymongo.secret_key'), '')
                ->timeout(20)
                ->acceptJson()
                ->post($url, ['data' => ['attributes' => [
                    'amount' => $centavos,
                    'payment_id' => $paymongoPaymentId,
                    // The create table lists duplicate / fraudulent / others; the admin's own words go in the note.
                    'reason' => 'others',
                    'notes' => mb_substr($note, 0, 255),
                ]]]);
        } catch (ConnectionException $e) {
            Log::error('PayMongo refund: could not reach PayMongo', ['payment_id' => $paymongoPaymentId, 'error' => $e->getMessage()]);

            return self::failure('We could not reach PayMongo, so nothing was refunded. Try again in a few minutes.');
        }

        $status = $response->json('data.attributes.status');
        $id = $response->json('data.id');

        if ($response->successful() && $id && in_array($status, ['pending', 'processing', 'succeeded'], true)) {
            return [
                'ok' => true,
                'id' => $id,
                'status' => $status,
                'transfer_link' => $response->json('data.attributes.transfer_link'),
                'message' => null,
            ];
        }

        $code = $response->json('errors.0.code');
        $detail = $response->json('errors.0.detail');

        Log::error('PayMongo refund refused', [
            'payment_id' => $paymongoPaymentId,
            'http_status' => $response->status(),
            'refund_status' => $status,
            'code' => $code,
            'detail' => $detail,
            // A refund PayMongo accepted but marked failed carries its reason here (amount, notes, status; no secrets).
            'attributes' => $response->json('data.attributes') ?? $response->json(),
        ]);

        if ($status === 'failed') {
            return self::failure('PayMongo accepted the refund request but could not process it. Nothing was refunded; try again, and contact PayMongo support if it keeps failing.');
        }

        $reason = self::MESSAGES[$code] ?? $detail ?? 'PayMongo answered with an error.';

        return self::failure('PayMongo did not refund this payment: '.rtrim((string) $reason, '.').'. Nothing was changed.');
    }

    /** @return array{ok: false, id: null, status: null, transfer_link: null, message: string} */
    private static function failure(string $message): array
    {
        return ['ok' => false, 'id' => null, 'status' => null, 'transfer_link' => null, 'message' => $message];
    }
}
