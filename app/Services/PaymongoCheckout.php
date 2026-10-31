<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Everything the app does to a PayMongo checkout session: open one, recover from an abandoned one, and settle
 * the rows it funded once it is paid.
 *
 * Kept apart from the controllers so the web and API payment controllers, the webhook and the poll fallback share
 * one implementation and tests can fake the HTTP layer. The recovery calls fail closed: when PayMongo cannot be
 * reached or answers something unexpected, the caller must assume the session may still be payable.
 *
 * One online move-in is one PayMongo payment but several rows here (rent `Initial`, `Deposit`): see
 * Payment::checkoutGroup(). Settling moves the whole group together or not at all.
 */
class PaymongoCheckout
{
    private const BASE = 'https://api.paymongo.com/v1/checkout_sessions';

    /** The session's status as PayMongo reports it (e.g. 'active', 'expired', 'paid'), or null if unknown. */
    public static function status(string $sessionId): ?string
    {
        $response = self::client()->get(self::BASE.'/'.$sessionId);

        return $response->ok() ? $response->json('data.attributes.status') : null;
    }

    /** The session's status and hosted checkout URL as PayMongo reports them, or null when it cannot be read. */
    public static function session(string $sessionId): ?array
    {
        $response = self::client()->get(self::BASE.'/'.$sessionId);

        if (! $response->ok()) {
            return null;
        }

        return [
            'status' => $response->json('data.attributes.status'),
            'checkout_url' => $response->json('data.attributes.checkout_url'),
        ];
    }

    /**
     * Make sure this session can no longer be paid. True when it has expired (already, or because we just
     * expired it); false whenever that cannot be confirmed, including when it has in fact been paid.
     */
    public static function abandon(string $sessionId): bool
    {
        if (self::client()->post(self::BASE.'/'.$sessionId.'/expire')->successful()) {
            return true;
        }

        return self::status($sessionId) === 'expired';
    }

    /**
     * Open a checkout session. $lines are ['name' => string, 'amount' => pesos] in the order the tenant should see
     * them; the rent line comes first. Returns PayMongo's raw response: the caller decides what a failure means.
     *
     * @param  array<int, array{name: string, amount: float}>  $lines
     */
    public static function create(Reservation $reservation, array $lines, string $description, string $successUrl, string $cancelUrl): Response
    {
        return self::client()->post(self::BASE, [
            'data' => [
                'attributes' => [
                    'send_email_receipt' => false,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => $description,
                    'line_items' => array_map(fn (array $line) => [
                        'currency' => 'PHP',
                        'amount' => Payment::toCentavos($line['amount']), // PayMongo expects cents
                        'name' => $line['name'],
                        'quantity' => 1,
                    ], array_values($lines)),
                    'payment_method_types' => ['gcash', 'qrph'],
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ],
        ]);
    }

    /**
     * The payment in a checkout session's `payments` list that actually took the money: the entry whose status is
     * 'paid', else the first. Its `pay_` id is what a refund is made against.
     *
     * @param  array  $sessionPayments  the session's `payments` list, as the webhook and the poll both carry it
     */
    public static function paidPayment(array $sessionPayments): ?array
    {
        foreach ($sessionPayments as $entry) {
            if (($entry['attributes']['status'] ?? null) === 'paid') {
                return $entry;
            }
        }

        return $sessionPayments[0] ?? null;
    }

    /**
     * Whether the money PayMongo took for a paid checkout is the amount we asked for: the rent, the deposit and the
     * fee of the whole checkout group. The session is created server-side, so a mismatch means something is wrong
     * (a changed price, a replayed or foreign event), and the payment must not be settled on the strength of it: it
     * stays Pending and the admins are told.
     *
     * PayMongo reports the paid amount in centavos at `payments[].attributes.amount`. A payload without one
     * cannot be checked; that is logged and let through, because the signature has already vouched for it.
     */
    public static function paidAmountMatches(array $sessionPayments, Payment $payment): bool
    {
        $paid = self::paidPayment($sessionPayments)['attributes']['amount'] ?? null;

        if (! is_numeric($paid)) {
            Log::warning('PayMongo paid amount missing; settling without an amount check', ['payment_id' => $payment->payment_id]);

            return true;
        }

        $expected = Payment::toCentavos($payment->checkoutTotal());

        if ((int) $paid === $expected) {
            return true;
        }

        Log::error('PayMongo paid amount does not match the checkout', [
            'payment_id' => $payment->payment_id,
            'expected_centavos' => $expected,
            'paid_centavos' => (int) $paid,
        ]);
        $payment->alertAdminsAmountMismatch((int) $paid);

        return false;
    }

    /**
     * Settle a paid checkout: every row it funded moves from Pending in one locked transaction, so the webhook and
     * the poll fallback arriving together settle it once.
     *
     * Move-in rows (rent + deposit) go to escrow, `Held`. Monthly rent has no handover left to protect, so it goes
     * straight to `Paid` and is owed to the landlord at once.
     *
     * @param  string|null  $methodUsed  PayMongo's `payment_method_used`, when it sent one
     * @param  array  $sessionPayments  the session's `payments` list
     * @return Collection<int, Payment>|null the settled rows, or null when it was already settled (or is not ours)
     */
    public static function settle(Payment $payment, ?string $methodUsed, array $sessionPayments, ?string $intentId): ?Collection
    {
        if (! $payment->paymongo_checkout_session_id) {
            return null;
        }

        $paid = self::paidPayment($sessionPayments);
        $method = Payment::resolvePaymongoMethod($methodUsed ?? ($paid['attributes']['source']['type'] ?? null));

        $rows = DB::transaction(function () use ($payment, $paid, $method, $intentId) {
            $rows = Payment::where('paymongo_checkout_session_id', $payment->paymongo_checkout_session_id)
                ->orderBy('payment_id')
                ->lockForUpdate()
                ->get();

            // Already settled by the other path (or the webhook was retried).
            if ($rows->isEmpty() || $rows->first()->status !== 'Pending') {
                return null;
            }

            // The observer reads the landlord off these when a row becomes payable; rows fetched as a collection may
            // not lazy-load (Model::preventLazyLoading), so load them explicitly.
            $rows->loadMissing('reservation.property');

            foreach ($rows as $row) {
                $isRent = $row->payment_type === 'Monthly';

                $row->update([
                    'status' => $isRent ? 'Paid' : 'Held',
                    'payment_method' => $method ?? $row->payment_method,
                    'paymongo_payment_intent_id' => $intentId,
                    // The pay_ id: the id a refund is made against, the same on every row of the checkout.
                    'paymongo_payment_id' => $paid['id'] ?? null,
                    'paid_at' => now(),
                    // Rent settles straight to the landlord with no escrow step, so it is payout-eligible the
                    // moment it is paid. Move-in money isn't: it only becomes eligible once escrow releases it.
                    'payout_owed_at' => $isRent ? now() : null,
                ]);
            }

            return $rows;
        });

        if ($rows === null) {
            return null;
        }

        // The broadcast and both notifications are raised by PaymentObserver off the status change above.
        $lead = $rows->first();
        $reservation = $lead->reservation;
        $tenant = $reservation?->tenant?->name;

        $reservation?->postSystemMessage($lead->payment_type === 'Monthly'
            ? $tenant.' paid rent for '.($lead->billing_period?->format('M Y') ?? 'this period').' online.'
            : $tenant.' completed the initial payment. Funds are held by AbangananHub.');
        $lead->alertAdminsIfStranded();

        return $rows;
    }

    private static function client()
    {
        return Http::withBasicAuth(config('services.paymongo.secret_key'), '');
    }
}
