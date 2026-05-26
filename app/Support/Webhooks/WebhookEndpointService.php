<?php

namespace App\Support\Webhooks;

use App\Models\OutboundWebhook;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Canonical service for `OutboundWebhook` endpoint lifecycle.
 *
 * Per CLAUDE.md "service-layer single seam": every cross-cutting write
 * goes through this class.
 */
class WebhookEndpointService
{
    public const SECRET_PREFIX = 'whsec_';

    /**
     * Create a new endpoint for the tenant.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tenant $tenant, User $createdBy, array $attributes): OutboundWebhook
    {
        $url = $this->normalizeUrl($attributes['url'] ?? '');
        $events = $this->normalizeEvents($attributes['events'] ?? []);

        $webhook = new OutboundWebhook;
        $webhook->forceFill([
            'tenant_id' => $tenant->id,
            'created_by_id' => $createdBy->id,
            'url' => $url,
            'description' => $attributes['description'] ?? null,
            'secret' => $this->generateSecret(),
            'events' => $events,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'failure_count' => 0,
        ])->save();

        return $webhook->fresh();
    }

    /**
     * Update an endpoint's attributes (URL, events, active flag, description).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(OutboundWebhook $webhook, array $attributes): OutboundWebhook
    {
        if (array_key_exists('url', $attributes)) {
            $webhook->url = $this->normalizeUrl($attributes['url']);
        }

        if (array_key_exists('events', $attributes)) {
            $webhook->events = $this->normalizeEvents($attributes['events']);
        }

        if (array_key_exists('is_active', $attributes)) {
            $webhook->is_active = (bool) $attributes['is_active'];
            if ($webhook->is_active) {
                $webhook->disabled_at = null;
            }
        }

        if (array_key_exists('description', $attributes)) {
            $webhook->description = $attributes['description'] !== null
                ? (string) $attributes['description']
                : null;
        }

        $webhook->save();

        return $webhook->fresh();
    }

    /**
     * Rotate the signing secret. Returns the plaintext value of the new secret
     * (it remains stored on the row but `#[Hidden]` keeps it out of JSON).
     */
    public function rotateSecret(OutboundWebhook $webhook): string
    {
        $secret = $this->generateSecret();
        $webhook->forceFill(['secret' => $secret])->save();

        return $secret;
    }

    /**
     * Hard-delete an endpoint. Deliveries cascade.
     */
    public function delete(OutboundWebhook $webhook): void
    {
        $webhook->delete();
    }

    /**
     * Mark an endpoint as failing. Auto-disable after N consecutive failures.
     */
    public function recordFailure(OutboundWebhook $webhook, int $threshold = 10): OutboundWebhook
    {
        $webhook->failure_count = ((int) $webhook->failure_count) + 1;

        if ($webhook->failure_count >= $threshold) {
            $webhook->is_active = false;
            $webhook->disabled_at = now();
        }

        $webhook->save();

        return $webhook;
    }

    public function recordSuccess(OutboundWebhook $webhook): OutboundWebhook
    {
        $webhook->forceFill([
            'failure_count' => 0,
            'last_delivery_at' => now(),
        ])->save();

        return $webhook;
    }

    public function generateSecret(): string
    {
        return self::SECRET_PREFIX.Str::random(48);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('A valid webhook URL is required.');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Webhook URL must use http or https.');
        }

        return $url;
    }

    /**
     * @param  mixed  $events
     * @return array<int, string>
     */
    private function normalizeEvents($events): array
    {
        if (! is_array($events) || $events === []) {
            throw new InvalidArgumentException('At least one event must be selected.');
        }

        $known = array_keys((array) config('api-abilities.webhook_events', []));
        $events = array_values(array_unique(array_map('strval', $events)));

        foreach ($events as $e) {
            if (! in_array($e, $known, true)) {
                throw new InvalidArgumentException("Unknown event: {$e}");
            }
        }

        return $events;
    }
}
