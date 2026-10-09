<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\MassPrunable;

/** One AI search. See the create_ai_search_logs_table migration. */
class AiSearchLog extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['intent' => 'array', 'relaxed' => 'array', 'used_ai' => 'boolean', 'created_at' => 'datetime'];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }
}
