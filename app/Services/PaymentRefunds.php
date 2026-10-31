<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * An admin gives a tenant's held online payment back (the other way out of the move-in review queue, next to
 * releasing it to the landlord).
 *
 * One online checkout is one payment on PayMongo's side but several rows here (rent, deposit; see
 * Payment::checkoutGroup()), so the group is refunded together as ONE partial refund against its `pay_...` id:
 * the rows' `amount` plus, when the admin ticks it, the online payment fee the tenant paid on top. PayMongo does
 * not say it returns its own processing fee, so a refunded fee is the platform's cost.
 *
 * Everything runs in one transaction on the locked rows. PayMongo is asked first, with the locks held and before
 * any row is written, so a refusal changes nothing and a double click cannot refund twice. If the database write
 * fails after PayMongo said yes, the refund id is logged at critical level so the rows can be fixed by hand.
 */
class PaymentRefunds
{
    /**
     * @return array{rows: Collection<int, Payment>, centavos: int, status: string, transfer_link: ?string}
     *
     * @throws PaymentRefundException
     */
    public static function refund(Payment $payment, User $admin, string $reason, bool $includeFee): array
    {
        return DB::transaction(function () use ($payment, $admin, $reason, $includeFee) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $group = $locked->paymongo_checkout_session_id
                ? Payment::where('paymongo_checkout_session_id', $locked->paymongo_checkout_session_id)
                    ->orderBy('payment_id')->lockForUpdate()->get()
                : $locked->newCollection([$locked]);

            // The status broadcast reads these off every row; rows loaded as a collection may not lazy load.
            $group->load('reservation.property');

            self::assertRefundable($group);

            $quote = self::quote($group);
            $centavos = $quote['rent_centavos'] + ($includeFee ? $quote['fee_centavos'] : 0);

            if ($centavos < PaymongoRefunds::MIN_CENTAVOS) {
                throw new PaymentRefundException('PayMongo does not refund less than ₱1.00.');
            }

            $reservation = $locked->reservation_id
                ? Reservation::whereKey($locked->reservation_id)->with(['unit', 'property'])->lockForUpdate()->first()
                : null;

            if ($reservation && in_array($reservation->rental_status, ['Occupied', 'Completed'], true)) {
                throw new PaymentRefundException('The tenant has already moved in, so this payment cannot be refunded here.', 409);
            }

            $result = PaymongoRefunds::create(
                $quote['paymongo_payment_id'],
                $centavos,
                $reason,
                $group->contains(fn (Payment $p) => $p->payment_method === 'QRPh'),
            );

            if (! $result['ok']) {
                throw new PaymentRefundException($result['message']);
            }

            try {
                foreach ($group as $row) {
                    $row->update([
                        'status' => 'Refunded',
                        'refunded_at' => now(),
                        'refunded_by' => $admin->user_id,
                        'refund_reason' => mb_substr($reason, 0, 255),
                        'paymongo_refund_id' => $result['id'],
                        'refund_status' => $result['status'],
                        'refund_transfer_link' => $result['transfer_link'],
                        'refund_includes_fee' => $includeFee,
                    ]);
                }

                $cancelled = $reservation ? self::cancelReservation($reservation, $reason) : false;

                self::tell($reservation, $group, $centavos, $result, $cancelled);

                AuditLog::record(
                    'payment.refund',
                    sprintf('Refunded %s held payment(s) (₱%s) to the tenant.', $group->count(), number_format($centavos / 100, 2)),
                    $locked,
                    $reason,
                    [
                        'payment_ids' => $group->pluck('payment_id')->all(),
                        'amount' => $centavos / 100,
                        'includes_fee' => $includeFee,
                        'paymongo_refund_id' => $result['id'],
                        'refund_status' => $result['status'],
                        'reservation_id' => $locked->reservation_id,
                    ],
                );
            } catch (\Throwable $e) {
                // PayMongo already agreed to the refund; the rows will roll back. Leave a trail to fix them by hand.
                report($e);
                logger()->critical('PayMongo refund was created but the database write failed and rolled back', [
                    'paymongo_refund_id' => $result['id'],
                    'payment_ids' => $group->pluck('payment_id')->all(),
                ]);

                throw $e;
            }

            return [
                'rows' => $group,
                'centavos' => $centavos,
                'status' => $result['status'],
                'transfer_link' => $result['transfer_link'],
            ];
        });
    }

    /**
     * What refunding this group would send, from rows already in memory.
     *
     * @param  Collection<int, Payment>  $rows
     * @return array{rent_centavos: int, fee_centavos: int, rent: float, fee: float, paymongo_payment_id: ?string, rows: int}
     */
    public static function quote(Collection $rows): array
    {
        $rent = (int) $rows->sum(fn (Payment $p) => Payment::toCentavos($p->amount));
        $fee = (int) $rows->sum(fn (Payment $p) => Payment::toCentavos($p->service_fee ?? 0));

        return [
            'rent_centavos' => $rent,
            'fee_centavos' => $fee,
            'rent' => $rent / 100,
            'fee' => $fee / 100,
            'paymongo_payment_id' => $rows->pluck('paymongo_payment_id')->filter()->unique()->first(),
            'rows' => $rows->count(),
        ];
    }

    /**
     * The Held rows each Held payment on a page belongs to, keyed by payment id, in one query. The admin list
     * uses it to show what "Refund tenant" would send for a row without a query per row.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<int, Collection<int, Payment>>
     */
    public static function heldGroups(Collection $payments): array
    {
        $held = $payments->where('status', 'Held');

        if ($held->isEmpty()) {
            return [];
        }

        $sessions = $held->pluck('paymongo_checkout_session_id')->filter()->unique()->values();

        $siblings = $sessions->isEmpty()
            ? collect()
            : Payment::whereIn('paymongo_checkout_session_id', $sessions)
                ->where('status', 'Held')->orderBy('payment_id')->get()
                ->groupBy('paymongo_checkout_session_id');

        return $held->mapWithKeys(fn (Payment $p) => [
            $p->payment_id => $p->paymongo_checkout_session_id
                ? ($siblings[$p->paymongo_checkout_session_id] ?? collect([$p]))
                : collect([$p]),
        ])->all();
    }

    /** @param  Collection<int, Payment>  $group */
    private static function assertRefundable(Collection $group): void
    {
        if ($group->every(fn (Payment $p) => $p->status === 'Refunded')) {
            throw new PaymentRefundException('This payment has already been refunded.', 409);
        }

        if ($group->contains(fn (Payment $p) => $p->status !== 'Held')) {
            throw new PaymentRefundException('Only payments that are still held can be refunded.', 409);
        }

        if ($group->contains(fn (Payment $p) => $p->recorded_by !== null || $p->voided_at !== null || $p->payout_owed_at !== null || $p->payout_id !== null)) {
            throw new PaymentRefundException('This money was not collected through PayMongo, or is already owed to the landlord, so it cannot be refunded here.', 409);
        }

        if ($group->pluck('paymongo_payment_id')->filter()->unique()->count() !== 1) {
            throw new PaymentRefundException('This payment has no single PayMongo payment id to refund against. Refund it from the PayMongo dashboard.', 409);
        }
    }

    /**
     * Cancel the reservation the refunded money belonged to and free its unit, the way an admin force-cancel does
     * (Admin\ReservationController::forceCancel). Already ended reservations are left alone: that is the case of
     * money that arrived after the reservation ended, where the refund is the whole fix.
     */
    private static function cancelReservation(Reservation $reservation, string $reason): bool
    {
        if (in_array($reservation->rental_status, Reservation::TERMINAL_STATUSES, true)) {
            return false;
        }

        // Said before the status flips, while the thread is still open.
        $reservation->postSystemMessage('An administrator has reviewed this reservation and refunded the held payment to the tenant. The reservation has been cancelled.');

        $reservation->rental_status = 'Cancelled';
        $reservation->remarks = '[Admin] Payment refunded: '.mb_substr($reason, 0, 400);
        $reservation->move_in_disputed_at = null;
        $reservation->move_in_dispute_reason = null;
        $reservation->move_in_deadline_at = null;
        $reservation->save();

        if ($reservation->unit && in_array($reservation->unit->availability_status, ['Reserved', 'Occupied'], true)) {
            $otherActive = Reservation::where('unit_id', $reservation->unit_id)
                ->where('reservation_id', '!=', $reservation->reservation_id)
                ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
                ->exists();

            if (! $otherActive) {
                $reservation->unit->update(['availability_status' => 'Available']);
            }
        }

        return true;
    }

    /**
     * @param  Collection<int, Payment>  $group
     * @param  array{id: ?string, status: ?string, transfer_link: ?string}  $result
     */
    private static function tell(?Reservation $reservation, Collection $group, int $centavos, array $result, bool $cancelled): void
    {
        if (! $reservation) {
            return;
        }

        $amount = '₱'.number_format($centavos / 100, 2);
        $unitLabel = $reservation->unit?->unit_label ?? $reservation->property?->title;

        $tenantText = "An administrator refunded {$amount} of your payment for {$unitLabel}.";
        $tenantText .= $result['transfer_link']
            ? ' This was a QR Ph payment, so you need to claim the refund with this link: '.$result['transfer_link'].' (it works for 3 days).'
            : ' It should reach your GCash account within the day.';

        Notification::notify(
            $reservation->tenant_id,
            'payment',
            'Payment refunded',
            $tenantText,
            route('reservations.index'),
            $reservation->conversation_id,
        );

        Notification::notify(
            $reservation->property?->landlord_id,
            'payment',
            'Held payment refunded to the tenant',
            "An administrator refunded the held {$amount} on {$unitLabel} to the tenant".($cancelled ? ' and cancelled the reservation.' : '.'),
            route('landlord.reservations.index'),
            $reservation->conversation_id,
        );
    }
}
