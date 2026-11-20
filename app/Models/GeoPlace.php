<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** A place name looked up once on OpenStreetMap. See the create_geo_places_table migration. */
class GeoPlace extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'fetched_at' => 'datetime'];
    }

    /** Hits are kept 90 days, misses 7 (a new building gets mapped). */
    public function prunable(): Builder
    {
        return static::where(fn ($q) => $q->whereNotNull('lat')->where('fetched_at', '<', now()->subDays(90)))
            ->orWhere(fn ($q) => $q->whereNull('lat')->where('fetched_at', '<', now()->subDays(7)));
    }
}
