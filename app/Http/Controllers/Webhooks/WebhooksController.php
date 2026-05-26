<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\WebhookDestroyRequest;
use App\Http\Requests\Webhooks\WebhookStoreRequest;
use App\Http\Requests\Webhooks\WebhookUpdateRequest;
use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use App\Models\Tenant;
use App\Support\Webhooks\OutboundWebhookDispatcher;
use App\Support\Webhooks\WebhookEndpointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class WebhooksController extends Controller
{
    public function __construct(
        private readonly WebhookEndpointService $service,
        private readonly OutboundWebhookDispatcher $dispatcher,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant();

        $endpoints = OutboundWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn (OutboundWebhook $w) => $this->shape($w))
            ->values()
            ->all();

        $recentDeliveries = OutboundWebhookDelivery::query()
            ->whereIn('outbound_webhook_id', collect($endpoints)->pluck('id'))
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get()
            ->map(fn (OutboundWebhookDelivery $d) => [
                'id' => $d->id,
                'outbound_webhook_id' => $d->outbound_webhook_id,
                'event_type' => $d->event_type,
                'status' => $d->status,
                'response_code' => $d->response_code,
                'attempt' => $d->attempt,
                'delivered_at' => $d->delivered_at?->toIso8601String(),
                'failed_at' => $d->failed_at?->toIso8601String(),
                'created_at' => $d->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('tenants/webhooks', [
            'endpoints' => $endpoints,
            'deliveries' => $recentDeliveries,
            'available_events' => (array) config('api-abilities.webhook_events', []),
        ]);
    }

    public function store(WebhookStoreRequest $request): RedirectResponse
    {
        $tenant = $this->currentTenant();

        try {
            $webhook = $this->service->create($tenant, $request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['url' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook endpoint created.')]);

        // Show plain-text secret ONCE so it can be copied.
        return back()->with('webhook_secret', [
            'id' => $webhook->id,
            'plain_text' => $webhook->secret,
        ]);
    }

    public function update(WebhookUpdateRequest $request, string $tenantSlug, OutboundWebhook $webhook): RedirectResponse
    {
        unset($tenantSlug);

        try {
            $this->service->update($webhook, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['url' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook endpoint updated.')]);

        return back();
    }

    public function destroy(WebhookDestroyRequest $request, string $tenantSlug, OutboundWebhook $webhook): RedirectResponse
    {
        unset($tenantSlug, $request);

        $this->service->delete($webhook);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook endpoint deleted.')]);

        return back();
    }

    /**
     * Rotate the endpoint's signing secret and reveal the new plain-text value.
     */
    public function rotateSecret(WebhookDestroyRequest $request, string $tenantSlug, OutboundWebhook $webhook): RedirectResponse
    {
        unset($tenantSlug, $request);

        $secret = $this->service->rotateSecret($webhook);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('New webhook secret generated.')]);

        return back()->with('webhook_secret', [
            'id' => $webhook->id,
            'plain_text' => $secret,
        ]);
    }

    /**
     * Send a synthetic `test.ping` event to the endpoint.
     */
    public function testFire(WebhookDestroyRequest $request, string $tenantSlug, OutboundWebhook $webhook): RedirectResponse
    {
        unset($tenantSlug, $request);

        $this->dispatcher->testFire($webhook);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test event queued.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(OutboundWebhook $webhook): array
    {
        return [
            'id' => $webhook->id,
            'url' => $webhook->url,
            'description' => $webhook->description,
            'events' => (array) $webhook->events,
            'is_active' => (bool) $webhook->is_active,
            'failure_count' => (int) $webhook->failure_count,
            'last_delivery_at' => $webhook->last_delivery_at?->toIso8601String(),
            'disabled_at' => $webhook->disabled_at?->toIso8601String(),
            'created_at' => $webhook->created_at?->toIso8601String(),
        ];
    }

    private function currentTenant(): Tenant
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
