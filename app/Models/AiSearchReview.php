<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A search an admin checked and corrected. See the create_ai_search_reviews_table migration. */
class AiSearchReview extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['intent' => 'array', 'exported_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
