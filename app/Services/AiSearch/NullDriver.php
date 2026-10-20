<?php

namespace App\Services\AiSearch;

/** No AI configured: always unavailable, so the keyword fallback answers. Also the test default. */
class NullDriver implements AiSearchDriver
{
    public function interpret(string $query, array $vocab): array
    {
        throw new AiSearchUnavailable('No AI search driver configured.');
    }

    public function rank(array $intent, array $candidates, string $language, bool $exact): array
    {
        throw new AiSearchUnavailable('No AI search driver configured.');
    }
}
