<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\ListingPhotoChecker;
use Illuminate\Console\Command;

/** Runs the advisory photo check of one listing right now and prints the answer (for demos and manual checks). */
class ListingPhotosCheck extends Command
{
    protected $signature = 'listing-photos:check {property_id}';

    protected $description = 'Check a listing\'s photos against its claimed amenities (AI, advisory)';

    public function handle(ListingPhotoChecker $checker): int
    {
        $property = Property::find($this->argument('property_id'));
        if (! $property) {
            $this->error('No such property.');

            return self::FAILURE;
        }

        $check = $checker->check($property);
        if ($check === null) {
            $this->warn('No check was made: the feature is off (AI_SEARCH_FEATURE_PHOTO_CHECK), the AI is not configured, there are no photos, or the AI failed.');

            return self::FAILURE;
        }

        $this->line("{$property->title}: checked {$check->photos_checked} photo(s), cost \$" . $check->cost_usd);
        $this->table(['Amenity', 'Photos show'], collect($check->amenity_results)->map(fn ($s, $n) => [$n, str_replace('_', ' ', $s)])->values()->all());
        foreach ($check->flags as $flag => $on) {
            $on && $this->warn(str_replace('_', ' ', $flag));
        }
        $check->note && $this->line($check->note);

        return self::SUCCESS;
    }
}
