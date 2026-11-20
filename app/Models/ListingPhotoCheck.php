<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The AI's advisory look at a listing's photos. See the create_listing_photo_checks_table migration. */
class ListingPhotoCheck extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amenity_results' => 'array', 'flags' => 'array', 'cost_usd' => 'float', 'created_at' => 'datetime'];
    }
}
