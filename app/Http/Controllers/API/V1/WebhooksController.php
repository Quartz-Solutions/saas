<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Controllers\API\V1\Concerns\HandlesIdempotency;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\OutboundWebhook;
use App\Support\Webhooks\OutboundWebhookDispatcher;
use App\Support\Webhooks\WebhookEndpointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Outbound webhook endpoints (tenant-scoped).
 *
 * @group Webhooks
 *
 * @authenticated
 */
class WebhooksController extends ApiController
{
    use HandlesIdempotency;

    public function __construct(
        private readonly WebhookEndpointService $service,
        private readonly OutboundWebhookDispatcher $dispatcher,
    ) {}

    /**
     * List endpoints. Ability: `webhooks:read`.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:read');

        $tenant = $this->currentApiTenant();

        $paginator = OutboundWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return WebhookEndpointResource::collection($paginator)->response();
    }

    /**
     * Show one endpoint. Ability: `webhooks:read`.
     */
    public function show(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:read');

        return WebhookEndpointResource::make($this->resolveEndpoint($id))->response();
    }

    /**
     * Create an endpoint. Returns the plaintext secret ONCE (under
     * `data.secret`). Ability: `webhooks:write`.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:write');

        $tenant = $this->currentApiTenant();

        return $this->withIdempotency($request, function () use ($request, $tenant) {
            $events = array_keys((array) config('api-abilities.webhook_events', []));

            $data = Validator::make($request->all(), [
                'url' => ['required', 'url:http,https', 'max:2048'],
                'description' => ['nullable', 'string', 'max:255'],
                'events' => ['required', 'array', 'min:1'],
                'events.*' => ['string', Rule::in($events)],
                'is_active' => ['nullable', 'boolean'],
            ])->validate();

            try {
                $webhook = $this->service->create($tenant, $this->actor($request), $data);
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            $payload = WebhookEndpointResource::make($webhook)->resolve($request);
            $payload['secret'] = $webhook->secret;

            return response()->json(['data' => $payload], 201);
        });
    }

    /**
     * Update an endpoint. Ability: `webhooks:write`.
     */
    public function update(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:write');

        $webhook = $this->resolveEndpoint($id);
        $events = array_keys((array) config('api-abilities.webhook_events', []));

        $data = Validator::make($request->all(), [
            'url' => ['sometimes', 'url:http,https', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in($events)],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        try {
            $webhook = $this->service->update($webhook, $data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return WebhookEndpointResource::make($webhook)->response();
    }

    /**
     * Delete an endpoint. Ability: `webhooks:write`.
     */
    public function destroy(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:write');

        $this->service->delete($this->resolveEndpoint($id));

        return response()->json([], 204);
    }

    /**
     * Rotate the signing secret. Returns the new plaintext ONCE.
     * Ability: `webhooks:write`.
     */
    public function rotateSecret(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:write');

        $secret = $this->service->rotateSecret($this->resolveEndpoint($id));

        return response()->json([
            'data' => [
                'id' => $id,
                'secret' => $secret,
            ],
        ]);
    }

    /**
     * Fire a synthetic `test.ping` event to this endpoint.
     * Ability: `webhooks:write`.
     */
    public function testFire(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:write');

        $delivery = $this->dispatcher->testFire($this->resolveEndpoint($id));

        return response()->json([
            'data' => [
                'delivery_id' => $delivery->id,
                'status' => $delivery->status,
            ],
        ], 202);
    }

    private function resolveEndpoint(int $id): OutboundWebhook
    {
        $tenant = $this->currentApiTenant();

        $webhook = OutboundWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($id)
            ->first();

        if ($webhook === null) {
            abort(404, "Webhook endpoint [{$id}] not found.");
        }

        return $webhook;
    }
}
