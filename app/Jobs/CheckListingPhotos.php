<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\ListingPhotoChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Runs the advisory photo check of one listing in the background (Phase 7b). A failure is logged by the checker and ends here. */
class CheckListingPhotos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $propertyId) {}

    public function handle(ListingPhotoChecker $checker): void
    {
        $property = Property::find($this->propertyId);

        if ($property !== null && $property->verification_status === 'Pending') {
            $checker->check($property);
        }
    }
}
