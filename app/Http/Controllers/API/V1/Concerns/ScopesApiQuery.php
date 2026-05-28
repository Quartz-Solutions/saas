<?php

namespace App\Http\Controllers\API\V1\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Tiny helpers for filtering + sorting list endpoints uniformly.
 *
 * Per api.md §3.8:
 *   ?field=value           equality filter
 *   ?sort=field            ascending
 *   ?sort=-field           descending
 *
 * Unknown fields are silently ignored — never 422.
 */
trait ScopesApiQuery
{
    /**
     * Apply equality filters from `?key=value` for each whitelisted column.
     *
     * @param  array<int, string>  $allowed
     */
    protected function applyFilters(Builder $query, Request $request, array $allowed): Builder
    {
        foreach ($allowed as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }

        return $query;
    }

    /**
     * Apply `?sort=field` / `?sort=-field` against an allowed column list.
     *
     * @param  array<int, string>  $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default, string $direction = 'desc'): Builder
    {
        $raw = (string) $request->input('sort', '');
        $dir = $direction === 'asc' ? 'asc' : 'desc';
        $column = $default;

        if ($raw !== '') {
            $dir = str_starts_with($raw, '-') ? 'desc' : 'asc';
            $candidate = ltrim($raw, '-');
            if (in_array($candidate, $allowed, true)) {
                $column = $candidate;
            }
        }

        return $query->orderBy($column, $dir);
    }
}
