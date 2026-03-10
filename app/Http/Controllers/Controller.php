<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolve per-page value from query string with support for "all".
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     * @template TResult
     * @param Builder<TModel>|Relation<TRelatedModel, TDeclaringModel, TResult> $query
     * @return int
     */
    protected function resolvePerPage(Request $request, Builder|Relation $query, int $default = 15, int $max = 200): int
    {
        $perPage = $request->query('per_page', $default);

        if ($perPage === 'all') {
            // Support both Eloquent Builder and Relation instances
            $total = $query instanceof Relation
                ? $query->getQuery()->toBase()->getCountForPagination()
                : (clone $query)->toBase()->getCountForPagination();
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
