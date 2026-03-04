<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve per-page value from query string with support for "all".
     */
    protected function resolvePerPage(Request $request, Builder $query, int $default = 15, int $max = 200): int
    {
        $perPage = $request->query('per_page', $default);

        if ($perPage === 'all') {
            $total = (clone $query)->toBase()->getCountForPagination();
            return max(1, (int) $total);
        }

        if (is_numeric($perPage)) {
            $perPage = (int) $perPage;
            if ($perPage > 0) {
                return min($perPage, $max);
            }
        }

        return $default;
    }
}
