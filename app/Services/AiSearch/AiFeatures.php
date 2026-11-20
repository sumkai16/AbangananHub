<?php

namespace App\Services\AiSearch;

/** One place that answers "is this optional AI search feature on?" (config('ai_search.features')). */
class AiFeatures
{
    public static function on(string $name): bool
    {
        return (bool) config("ai_search.features.$name", false);
    }
}
