<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Paging for the API endpoints whose JSON has always been `{ data: [...] }`. The mobile app reads
 * `data` as a list, so the shape stays: `data` is one page of the same items, and `meta` says which
 * page it is and how many there are. `?page=` and `?per_page=` (capped) pick the page.
 */
class ApiPage
{
    public const MAX_PER_PAGE = 50;

    public static function perPage(Request $request, int $default = 20): int
    {
        return max(1, min($request->integer('per_page', $default), self::MAX_PER_PAGE));
    }

    /** @return array{current_page: int, last_page: int, per_page: int, total: int} */
    public static function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
        ];
    }
}
