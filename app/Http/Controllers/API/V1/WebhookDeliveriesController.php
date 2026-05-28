<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Resources\WebhookDeliveryResource;
use App\Jobs\DeliverWebhookJob;
use App\Models\OutboundWebhook;
use App\Models\OutboundWebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Outbound webhook delivery log.
 *
 * @group Webhooks
 *
 * @authenticated
 */
class WebhookDeliveriesController extends ApiController
{
    /**
     * GET /webhooks/{id}/deliveries — cursor-paginated when ?cursor= is
     * present, otherwise offset paginated.
     * Ability: `webhooks:read`.
     */
    public function index(Request $request, string $slug, int $webhookId): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:read');

        $webhook = $this->resolveWebhook($webhookId);

        $perPage = $this->perPage($request);

        $query = OutboundWebhookDelivery::query()
            ->where('outbound_webhook_id', $webhook->id)
            ->orderByDesc('id');

        $paginator = $request->filled('cursor')
            ? $query->cursorPaginate($perPage)
            : $query->paginate($perPage);

        return WebhookDeliveryResource::collection($paginator)->response();
    }

    /**
     * POST /webhooks/{id}/deliveries/{deliveryId}/retry — re-queues a failed
     * (or abandoned) delivery. Ability: `webhooks:write`.
     */
    public function retry(Request $request, string $slug, int $webhookId, int $deliveryId): JsonResponse
    {
        $this->requireAbility($request, 'webhooks:write');

        $webhook = $this->resolveWebhook($webhookId);

        $delivery = OutboundWebhookDelivery::query()
            ->where('outbound_webhook_id', $webhook->id)
            ->whereKey($deliveryId)
            ->first();

        if ($delivery === null) {
            abort(404, "Delivery [{$deliveryId}] not found.");
        }

        $delivery->forceFill([
            'status' => OutboundWebhookDelivery::STATUS_PENDING,
            'next_retry_at' => null,
            'failed_at' => null,
        ])->save();

        DeliverWebhookJob::dispatch($delivery->id);

        return response()->json([
            'data' => [
                'delivery_id' => $delivery->id,
                'status' => $delivery->status,
            ],
        ], 202);
    }

    private function resolveWebhook(int $id): OutboundWebhook
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
