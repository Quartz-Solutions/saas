<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decorate the response with X-RateLimit-* headers for every API call.
 *
 * The `throttle:<name>` middleware adds these for the current bucket already,
 * but only when the route actually opts into a throttle. We add them here
 * unconditionally for every API response so SDKs can always parse them, and
 * pick the bucket name from the chosen route limiter (defaulting to api.read).
 *
 * Headers emitted (per api.md §3.10):
 *   X-RateLimit-Limit      → bucket size per minute
 *   X-RateLimit-Remaining  → requests remaining in the current window
 *   Retry-After            → seconds until reset (only on 429)
 */
class ApiRateLimitHeaders
{
    public function handle(Request $request, Closure $next, string $limiter = 'api.read'): Response
    {
        $key = $this->bucketKey($request, $limiter);
        $max = $this->limitFor($request, $limiter);

        $response = $next($request);

        $remaining = max(0, $max - RateLimiter::attempts($key));

        $response->headers->set('X-RateLimit-Limit', (string) $max);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);

        if ($response->getStatusCode() === 429) {
            $response->headers->set('Retry-After', (string) RateLimiter::availableIn($key));
        }

        return $response;
    }

    private function bucketKey(Request $request, string $limiter): string
    {
        $tokenId = $request->user()?->currentAccessToken()?->id;

        return sprintf('%s:%s', $limiter, $tokenId !== null ? 'token:'.$tokenId : 'ip:'.$request->ip());
    }

    private function limitFor(Request $request, string $limiter): int
    {
        return match ($limiter) {
            'api.write' => (int) config('api-abilities.rate_limits.write', 30),
            'api.auth' => (int) config('api-abilities.rate_limits.auth', 6),
            default => (int) config('api-abilities.rate_limits.read', 120),
        };
    }
}
