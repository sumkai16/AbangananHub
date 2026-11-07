<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * One page cut from a list that is already built in PHP. Some lists (rent status, tenant stage) are
 * worked out from the ledger after the rows are loaded, so the database cannot page them. Filter and
 * count on the full list first, then call this for what is shown.
 */
class Paging
{
    public static function collection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString();
    }
}
