<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LandlordPayouts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends everything currently owed to one landlord, right after a payment becomes payable (PaymentObserver).
 * The per-landlord lock makes two payments that land together one transfer rather than a race; whatever misses
 * the lock or is held back is picked up by the hourly `payouts:send` sweep. Needs a queue worker in production
 * (QUEUE_CONNECTION=database); without one the sweep still sends, an hour late.
 */
class SendLandlordPayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Never retried by the queue: a retry after a half-finished send is the double-send we guard against. */
    public int $tries = 1;

    public function __construct(public int $landlordId)
    {
    }

    public function handle(): void
    {
        $landlord = User::find($this->landlordId);

        if (! $landlord) {
            return;
        }

        try {
            Cache::lock('landlord-payout:'.$this->landlordId, 120)
                ->block(15, fn () => LandlordPayouts::sendFor($landlord));
        } catch (LockTimeoutException) {
            Log::info('Payout for landlord '.$this->landlordId.' is already being sent; the sweep will catch the rest.');
        } catch (Throwable $e) {
            // This runs inside the webhook request when the queue is sync: a payout problem must not fail the
            // payment that triggered it.
            report($e);
        }
    }
}
