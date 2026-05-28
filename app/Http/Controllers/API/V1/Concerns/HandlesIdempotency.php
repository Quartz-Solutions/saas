<?php

namespace App\Http\Controllers\API\V1\Concerns;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key support for write endpoints.
 *
 * If the client sends an `Idempotency-Key` header and we've already processed
 * the same (auth-token, URL, key) tuple in the last 24h, we replay the cached
 * response untouched. Otherwise we execute the work + cache the result.
 *
 * Per api.md §4 / Phase B: keyed in Redis; 24h window per Stripe convention.
 */
trait HandlesIdempotency
{
    protected int $idempotencyTtlSeconds = 86400;

    /**
     * Wrap a write handler in an idempotency lookup.
     *
     * @param  Closure(): Response  $handler
     */
    protected function withIdempotency(Request $request, Closure $handler): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            return $handler();
        }

        $cacheKey = $this->idempotencyCacheKey($request, $key);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['status'], $cached['body'])) {
            $response = new JsonResponse($cached['body'], (int) $cached['status']);
            $response->headers->set('Idempotent-Replay', 'true');

            return $response;
        }

        $response = $handler();

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            Cache::put(
                $cacheKey,
                [
                    'status' => $response->getStatusCode(),
                    'body' => json_decode($response->getContent() ?: 'null', true),
                ],
                $this->idempotencyTtlSeconds,
            );
        }

        return $response;
    }

    private function idempotencyCacheKey(Request $request, string $key): string
    {
        $tokenId = (int) ($request->user()?->currentAccessToken()?->id ?? 0);
        $url = $request->method().' '.$request->path();

        return sprintf('api.idem:%d:%s:%s', $tokenId, sha1($url), $key);
    }
}
