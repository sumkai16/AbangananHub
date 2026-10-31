<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook events about refunds: `payment.refunded` (documented) and `payment.refund.updated` (not in PayMongo's
 * event list as read, so its shape is handled defensively). The webhook controller calls this for every event
 * that is not a paid checkout.
 *
 * A refund starts as `pending` or `processing` when an admin creates it (PaymentRefunds) and PayMongo reports
 * where it ends up: `succeeded`, or `failed`, which needs a human. Rows are matched on the refund id we stored,
 * so a refund made from the PayMongo dashboard (not ours) is logged and ignored.
 */
class PaymongoRefundEvents
{
    private const EVENTS = ['payment.refunded', 'payment.refund.updated'];

    private const STATUSES = ['pending', 'processing', 'succeeded', 'failed'];

    /** @return bool whether the event was a refund event this class handled */
    public static function handle(string $event, array $resource): bool
    {
        if (! in_array($event, self::EVENTS, true)) {
            return false;
        }

        foreach (self::refundsIn($resource) as $refund) {
            self::apply($refund['id'], $refund['status']);
        }

        return true;
    }

    /**
     * The refunds an event carries. A payment resource lists them under attributes.refunds; an event about a
     * single refund puts the refund itself in the resource.
     *
     * @return list<array{id: string, status: string}>
     */
    private static function refundsIn(array $resource): array
    {
        $found = [];

        if (($resource['type'] ?? null) === 'refund') {
            $found[] = ['id' => $resource['id'] ?? null, 'status' => $resource['attributes']['status'] ?? null];
        }

        foreach ($resource['attributes']['refunds'] ?? [] as $refund) {
            $found[] = ['id' => $refund['id'] ?? null, 'status' => $refund['attributes']['status'] ?? null];
        }

        return array_values(array_filter($found, fn ($r) => filled($r['id']) && in_array($r['status'], self::STATUSES, true)));
    }

    private static function apply(string $refundId, string $status): void
    {
        $changed = DB::transaction(function () use ($refundId, $status) {
            $rows = Payment::where('paymongo_refund_id', $refundId)->orderBy('payment_id')->lockForUpdate()->get();

            if ($rows->isEmpty()) {
                Log::info('PayMongo refund event for a refund we did not create', ['refund_id' => $refundId, 'status' => $status]);

                return null;
            }

            $before = $rows->first()->refund_status;

            if ($before === $status) {
                return null;
            }

            // A finished refund is not reopened by a late or replayed older event.
            if (in_array($before, ['succeeded', 'failed'], true)) {
                return null;
            }

            foreach ($rows as $row) {
                $row->update(['refund_status' => $status]);
            }

            $refunded = (float) $rows->sum('amount') + ($rows->first()->refund_includes_fee ? (float) $rows->sum('service_fee') : 0);

            return [$rows->first(), $refunded];
        });

        if (! $changed) {
            return;
        }

        [$changed, $refunded] = $changed;
        $changed->loadMissing('reservation.unit', 'reservation.property');

        if ($status === 'failed') {
            self::alertAdmins($changed, $refundId);
        } elseif ($status === 'succeeded') {
            $reservation = $changed->reservation;

            Notification::notify(
                $reservation?->tenant_id,
                'payment',
                'Refund sent',
                'PayMongo has sent your refund of ₱'.number_format($refunded, 2).'.',
                route('reservations.index'),
                $reservation?->conversation_id,
            );
        }
    }

    private static function alertAdmins(Payment $payment, string $refundId): void
    {
        User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))
            ->pluck('user_id')
            ->each(fn ($adminId) => Notification::notify(
                $adminId,
                'payment',
                'A refund failed',
                sprintf(
                    'PayMongo could not complete refund %s for reservation #%d. The tenant has not been repaid. Check the refund in the PayMongo dashboard and try it again there.',
                    $refundId,
                    $payment->reservation_id,
                ),
                route('admin.payments.index', ['status' => 'Refunded']),
            ));
    }
}
