<?php

namespace App\Services;

use App\Models\Payout;

/**
 * Webhook events about money sent out of the platform wallet: `transfer.outward.successful` and
 * `transfer.outward.failed`. PayMongo sends these as a wallet transaction, with the status at
 * `attributes.status` and the transfer id (`tr_...`) at `attributes.transfer_id`; the payout is found by that
 * transfer id. The webhook controller calls this for every event that is not a checkout payment, and an event
 * that is not about a transfer is left for the next handler.
 */
class PaymongoTransferEvents
{
    /** @return bool whether the event was a transfer event this class handled */
    public static function handle(string $event, array $resource): bool
    {
        if (! str_starts_with($event, 'transfer.')) {
            return false;
        }

        // Money coming in is not ours to settle.
        if (! str_starts_with($event, 'transfer.outward.')) {
            return true;
        }

        $transferId = $resource['attributes']['transfer_id'] ?? null;
        $payout = $transferId ? Payout::where('paymongo_transfer_id', $transferId)->first() : null;

        // Unknown transfer (sent from the dashboard by hand, or a test): nothing of ours to settle.
        if (! $payout) {
            return true;
        }

        $attributes = $resource['attributes'] ?? [];

        $status = $attributes['status'] ?? match (true) {
            str_ends_with($event, '.successful') => 'succeeded',
            str_ends_with($event, '.failed') => 'failed',
            default => '',
        };

        PaymongoTransfers::applyStatus($payout, (string) $status, [
            'provider_error' => $attributes['provider_error'] ?? null,
        ]);

        return true;
    }
}
