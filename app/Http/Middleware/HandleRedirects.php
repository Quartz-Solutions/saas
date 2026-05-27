<?php

namespace App\Http\Middleware;

use App\Models\Redirect as RedirectRow;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Matches the current request path against the `redirects` table and
 * issues a 30x response if a row is found. Bumps the row's `hits` and
 * `last_hit_at` so admins can spot popular old URLs.
 *
 * Looks up by exact path (case-insensitive). Wildcards are out of
 * scope for v1 — admins use the seed list + 404 log to discover gaps.
 */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        if ($path === '/') {
            return $next($request);
        }

        // Guard against pre-migration boot states (notably test classes
        // without RefreshDatabase): if the table isn't there yet there
        // are no redirects to match anyway.
        try {
            if (! Schema::hasTable('redirects')) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        $row = RedirectRow::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(from_path) = ?', [strtolower($path)])
            ->first();

        if ($row === null) {
            return $next($request);
        }

        $row->forceFill([
            'hits' => $row->hits + 1,
            'last_hit_at' => now(),
        ])->saveQuietly();

        $target = $row->to_path;
        if (! preg_match('#^https?://#i', $target)) {
            $target = url($target);
        }

        return redirect()->away($target, (int) $row->status_code);
    }
}
