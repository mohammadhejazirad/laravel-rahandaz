<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaginatorPayload
{
    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int, prev_url: ?string, next_url: ?string}
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'prev_url' => $paginator->previousPageUrl(),
            'next_url' => $paginator->nextPageUrl(),
        ];
    }
}
