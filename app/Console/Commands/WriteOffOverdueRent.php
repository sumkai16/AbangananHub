<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Reservation;
use App\Services\RentWriteOff;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Daily: an ended rental whose move-out date was `rentals.write_off_after_days` ago (default 90) and still has rent
 * owed is written off. One that the landlord pressed "Collect again" on is left alone. The landlord is told.
 * Idempotent: a rental already written off is skipped, and a failure on one row never stops the rest.
 */
class WriteOffOverdueRent extends Command
{
    protected $signature = 'reservations:write-off-overdue-rent
                            {--date= : Treat this date as "today" (YYYY-MM-DD)}';

    protected $description = 'Write off rent that an ended tenant still owes after the set number of days';

    public function handle(): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : Carbon::today();
        $cutoff = $today->copy()->subDays(RentWriteOff::afterDays())->toDateString();
        $done = 0;
        $failures = 0;

        Reservation::where('rental_status', 'Completed')
            ->whereNull('rent_written_off_at')
            ->whereNull('rent_write_off_resumed_at')
            ->whereNotNull('target_move_out_date')
            ->whereDate('target_move_out_date', '<=', $cutoff)
            ->whereHas('property')
            ->with(['tenant', 'property', 'unit'])
            ->chunkById(100, function ($reservations) use (&$done, &$failures) {
                foreach ($reservations as $reservation) {
                    try {
                        $amount = RentWriteOff::apply($reservation, RentWriteOff::AUTO);

                        if ($amount) {
                            $done++;
                            $this->tell($reservation, $amount);
                        }
                    } catch (\Throwable $e) {
                        $failures++;
                        Log::error('Rent write-off failed for reservation '.$reservation->reservation_id, ['exception' => $e->getMessage()]);
                    }
                }
            }, 'reservation_id');

        $this->info("Rent written off: {$done}, {$failures} failed.");

        return self::SUCCESS;
    }

    private function tell(Reservation $reservation, float $amount): void
    {
        $name = trim(($reservation->tenant?->first_name ?? '').' '.($reservation->tenant?->last_name ?? '')) ?: 'A former tenant';

        Notification::notify(
            $reservation->property?->landlord_id,
            'rent_written_off',
            'Unpaid rent written off',
            $name.' still owed ₱'.number_format($amount, 2).' '.RentWriteOff::afterDays().' days after moving out, so it was written off. '
                .'Paid later? Open the ledger and press Collect again to record it.',
            route('landlord.payments.ledger', $reservation),
            null,
            $reservation,
        );
    }
}
