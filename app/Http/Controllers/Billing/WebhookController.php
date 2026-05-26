<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Support\Billing\GatewayRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Inbound webhook router.
 *
 * Per Phase 3.0 spec: persist the raw payload to webhook_events FIRST,
 * THEN dispatch to the resolved gateway's handleWebhook(). The persist-
 * first contract is what makes the admin "webhook event replay" feature
 * (Phase 4) possible — even if signature verification or downstream
 * dispatch throws, the row stays for forensic review.
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly GatewayRegistry $registry,
    ) {}

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        $driver = $this->registry->find($gateway);

        if ($driver === null) {
            throw new NotFoundHttpException("Unknown gateway [{$gateway}].");
        }

        // 1. Persist the raw payload FIRST (idempotently on gateway_event_id).
        $payload = $request->json()->all();
        $eventId = (string) ($payload['id'] ?? ('local-'.uniqid('', true)));
        $eventType = (string) ($payload['type'] ?? 'unknown');

        $event = WebhookEvent::query()
            ->where('gateway', $gateway)
            ->where('gateway_event_id', $eventId)
            ->first();

        if ($event === null) {
            $event = new WebhookEvent;
            $event->forceFill([
                'gateway' => $gateway,
                'gateway_event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => $payload,
                'headers' => $this->headerSnapshot($request),
                'signature' => $request->header('Stripe-Signature') ?? $request->header('X-Webhook-Signature'),
                'status' => 'received',
                'processing_attempts' => 0,
                'received_at' => now(),
            ])->save();
        } else {
            $event->forceFill([
                'status' => 'received',
                'processing_attempts' => (int) $event->processing_attempts + 1,
            ])->save();
        }

        // 2. Dispatch to the gateway driver.
        try {
            $driver->handleWebhook($request, $event);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, string>
     */
    private function headerSnapshot(Request $request): array
    {
        $keep = ['stripe-signature', 'content-type', 'user-agent', 'x-webhook-signature'];
        $out = [];
        foreach ($keep as $name) {
            $value = $request->header($name);
            if ($value !== null) {
                $out[$name] = is_array($value) ? implode(',', $value) : (string) $value;
            }
        }

        return $out;
    }
}
