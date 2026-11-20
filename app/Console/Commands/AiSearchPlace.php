<?php

namespace App\Console\Commands;

use App\Services\AiSearch\GeoLookup;
use Illuminate\Console\Command;

/**
 * Shows what OpenStreetMap answers for a place name, the way AI search would ask it. Handy for checking a place
 * before a demo ("near Skyrise"). The answer is kept in geo_places like any other lookup.
 */
class AiSearchPlace extends Command
{
    protected $signature = 'ai-search:place {name : the place as a tenant would type it}';

    protected $description = 'Look up a place name on OpenStreetMap (inside Cebu) the way AI search does';

    public function handle(GeoLookup $geo): int
    {
        $place = $geo->find((string) $this->argument('name'));

        if ($place === null) {
            $this->warn('Not found inside Cebu. A search for it falls back to matching the address text.');

            return self::FAILURE;
        }

        $this->table(['Name', 'City', 'Lat', 'Lng'], [[$place['name'], $place['city'] ?? '-', $place['lat'], $place['lng']]]);

        return self::SUCCESS;
    }
}
