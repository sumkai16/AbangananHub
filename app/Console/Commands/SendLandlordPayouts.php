<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Models\User;
use App\Services\LandlordPayouts;
use App\Services\PaymongoTransfers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The hourly safety net behind the immediate payout job: checks transfers PayMongo has not confirmed yet, and
 * sends anything still owed (a landlord whose 48-hour hold ended, a payment whose job was missed, a wallet that
 * was short last time).
 */
class SendLandlordPayouts extends Command
{
    protected $signature = 'payouts:send';

    protected $description = 'Check unconfirmed PayMongo transfers and send any payouts still owed to landlords';

    public function handle(): int
    {
        // 1. Transfers PayMongo accepted but has not confirmed. The webhook usually settles them first.
        $polled = 0;

        Payout::where('status', 'Processing')
            ->whereNotNull('paymongo_transfer_id')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->each(function (Payout $payout) use (&$polled) {
                PaymongoTransfers::refresh($payout);
                $polled++;
            });

        // 2. Money still owed.
        $attempted = 0;

        foreach (LandlordPayouts::landlordIdsWithMoneyOwed() as $landlordId) {
            $landlord = User::find($landlordId);

            if (! $landlord) {
                continue;
            }

            $lock = Cache::lock('landlord-payout:'.$landlordId, 120);

            if (! $lock->get()) {
                continue;
            }

            try {
                if (LandlordPayouts::sendFor($landlord)) {
                    $attempted++;
                }
            } finally {
                $lock->release();
            }
        }

        $this->info("Checked {$polled} unconfirmed transfer(s); sent {$attempted} payout(s).");

        return self::SUCCESS;
    }
}
