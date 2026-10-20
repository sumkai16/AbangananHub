<?php

namespace App\Services\AiSearch;

/** We chose not to call the AI: `$reason` says why ('crowded' = too many AI searches running right now, 'busy' = daily cap). */
class AiSearchBusy extends AiSearchUnavailable
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('AI search skipped: ' . $reason);
    }
}
