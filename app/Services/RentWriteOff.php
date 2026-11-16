<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rent an ended tenant still owes: it keeps counting as owed, and is written off after a set time.
 *
 * "Written off" is the accounting word for rent that was owed but is not chased any more. The months stay on the ledger;
 * only the headline figures go to zero (RentLedger::summary() does that off `rent_written_off_at`). The clock is one per
 * rental: the move-out date plus config('rentals.write_off_after_days'). Written off by the daily job (source 'auto') or
 * by the landlord (source 'manual'); "Collect again" undoes it and stops the job from doing it a second time.
 */
class RentWriteOff
{
    public const AUTO = 'auto';

    public const MANUAL = 'manual';

    public static function afterDays(): int
    {
        return max(1, (int) config('rentals.write_off_after_days', 90));
    }

    public static function warningDays(): int
    {
        return max(0, (int) config('rentals.write_off_warning_days', 7));
    }

    /** The day the balance writes itself off. Null unless this is an ended rental the job still looks after. */
    public static function dueOn(Reservation $reservation): ?Carbon
    {
        if ($reservation->rental_status !== 'Completed'
            || $reservation->rent_written_off_at
            || $reservation->rent_write_off_resumed_at
            || ! $reservation->target_move_out_date) {
            return null;
        }

        return Carbon::parse($reservation->target_move_out_date)->startOfDay()->addDays(self::afterDays());
    }

    /** True from `write_off_warning_days` before the due day until the job runs. */
    public static function inWarning(Reservation $reservation, ?Carbon $today = null): bool
    {
        $due = self::dueOn($reservation);

        return $due !== null && ($today ?? Carbon::today())->greaterThanOrEqualTo($due->copy()->subDays(self::warningDays()));
    }

    /**
     * Write off what the tenant still owes. Returns the amount, null when it was already written off, false when nothing is owed.
     */
    public static function apply(Reservation $reservation, string $source): float|false|null
    {
        return DB::transaction(function () use ($reservation, $source) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->rent_written_off_at) {
                return null;
            }

            $owed = RentLedger::for($locked)->summary()['outstanding'];
            if ($owed <= 0) {
                return false;
            }

            $locked->forceFill([
                'rent_written_off_amount' => $owed,
                'rent_written_off_at' => now(),
                'rent_write_off_source' => $source,
            ])->save();

            AuditLog::record(
                $source === self::AUTO ? 'rent.write_off_auto' : 'rent.write_off',
                ($source === self::AUTO ? 'Unpaid rent written off automatically' : 'Landlord wrote off unpaid rent')
                    .': ₱'.number_format($owed, 2).' on an ended rental',
                $locked,
                null,
                ['amount' => $owed, 'source' => $source],
            );

            return $owed;
        });
    }

    /** "Collect again": the balance counts as owed again and the job leaves this rental alone. False when nothing was written off. */
    public static function resume(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->rent_written_off_at) {
                return false;
            }

            $locked->forceFill([
                'rent_written_off_amount' => null,
                'rent_written_off_at' => null,
                'rent_write_off_source' => null,
                'rent_write_off_resumed_at' => now(),
            ])->save();

            AuditLog::record('rent.resume_collecting', 'Landlord is collecting written-off rent again on an ended rental', $locked);

            return true;
        });
    }

    /** What is really lost: the written-off amount less the deposit still held. Never below zero. */
    public static function loss(float $writtenOff, float $depositRemaining): float
    {
        return round(max(0.0, $writtenOff - max(0.0, $depositRemaining)), 2);
    }
}
