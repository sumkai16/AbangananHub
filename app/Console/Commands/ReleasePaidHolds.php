<?php

namespace App\Console\Commands;

use App\Models\TenantHold;
use App\Services\Holds\HoldService;
use Illuminate\Console\Command;

/** Lifts every open tenant hold whose overdue rent has been paid (or whose tenancy was cancelled). Run daily. */
class ReleasePaidHolds extends Command
{
    protected $signature = 'holds:release-paid';

    protected $description = 'Release tenant holds whose overdue rent has been paid';

    public function handle(HoldService $holds): int
    {
        $released = 0;

        TenantHold::open()->whereNotNull('reservation_id')->with('reservation')->each(function (TenantHold $hold) use ($holds, &$released) {
            if ($holds->releaseIfPaid($hold)) {
                $released++;
            }
        });

        $this->info("Released {$released} hold(s).");

        return self::SUCCESS;
    }
}
