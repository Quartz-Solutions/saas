<?php

namespace App\Support\Billing\Paymob;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Support\Billing\PaymentGateway;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Paymob driver — Phase 3.2 scaffold.
 *
 * Implements PaymentGateway only. Paymob has no native subscription primitive;
 * recurring billing is performed merchant-side via tokenized cards (a one-off
 * card payment returns a token that can be re-charged headlessly). Therefore
 * SubscriptionGateway is intentionally NOT implemented here.
 *
 * Real implementations in this scaffold:
 *   - Webhook HMAC-SHA512 verification (verifyTransactionHmac)
 *   - handleWebhook() driven by that verification
 *   - baseUrl() switch on Paymob region
 *
 * Charge / refund / status flows are stubbed and throw until Phase 3.2 wires
 * the Unified Intentions API (preferred) or the legacy 3-step Accept flow.
 *
 * Regions supported by Paymob:
 *   eg → https://accept.paymob.com   (Egypt — original)
 *   ae → https://uae.paymob.com      (United Arab Emirates)
 *   sa → https://ksa.paymob.com      (Saudi Arabia)
 *   om → https://oman.paymob.com     (Oman)
 *   pk → https://pakistan.paymob.com (Pakistan)
 *
 * HTTP calls use Illuminate\Support\Facades\Http only; no Paymob composer SDK
 * is required.
 */
class PaymobGateway implements PaymentGateway
{
    /**
     * Ordered field list used to build the HMAC-SHA512 signed string for
     * Paymob TRANSACTION callbacks (the standard pay / refund / void / capture
     * webhook).
     *
     * Order is fixed by Paymob — do NOT sort or rearrange. Each entry is a
     * dot-path resolved against the `obj` object in the callback payload.
     *
     * Card-token callbacks use a DIFFERENT field list (token, masked_pan,
     * merchant_id, card_subtype, created_at, email, order_id, card_token_id,
     * card_token_value, etc.). That path is not handled here; add a
     * verifyCardTokenHmac() method when card-vaulting is wired.
     *
     * @var list<string>
     */
    protected const TRANSACTION_HMAC_FIELDS = [
        'amount_cents',
        'created_at',
        'currency',
        'error_occured',
        'has_parent_transaction',
        'id',
        'integration_id',
        'is_3d_secure',
        'is_auth',
        'is_capture',
        'is_refunded',
        'is_standalone_payment',
        'is_voided',
        'order.id',
        'owner',
        'pending',
        'source_data.pan',
        'source_data.sub_type',
        'source_data.type',
        'success',
    ];

    public function id(): string
    {
        return 'paymob';
    }

    public function displayName(): string
    {
        return 'Paymob';
    }

    // ------------------------------------------------------------------
    // PaymentGateway — stubs until Phase 3.2
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function authorize(int $amountCents, string $currency, array $context = []): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    public function capture(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    public function refund(Payment $payment, ?int $amountCents = null): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    public function void(Payment $payment): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    public function status(Payment $payment): Payment
    {
        throw new RuntimeException($this->stubMessage());
    }

    // ------------------------------------------------------------------
    // Webhook handling
    // ------------------------------------------------------------------

    public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
    {
        if (! $this->verifyTransactionHmac($request)) {
            $event->forceFill([
                'status' => 'failed',
                'error_message' => 'HMAC mismatch',
            ])->save();

            throw new RuntimeException('Paymob HMAC verification failed');
        }

        $payload = $request->json()->all();
        $transactionId = $payload['obj']['id'] ?? null;

        $event->forceFill([
            'status' => 'processed',
            'gateway_event_id' => $transactionId !== null ? (string) $transactionId : $event->gateway_event_id,
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    /**
     * Public helper so the webhook controller can verify ahead of persisting
     * the WebhookEvent row if it prefers. Delegates to the protected impl.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        return $this->verifyTransactionHmac($request);
    }

    /**
     * Verify a Paymob TRANSACTION callback using HMAC-SHA512.
     *
     * Paymob delivers the signature as the `hmac` QUERY-STRING parameter on
     * the callback URL (NOT a header). The signed string is the ordered
     * concatenation of TRANSACTION_HMAC_FIELDS pulled from `payload.obj`.
     * Booleans are lowercased to 'true' / 'false'; nulls become ''.
     *
     * @see https://developers.paymob.com/paymob-docs/integration-paths/apis
     */
    protected function verifyTransactionHmac(Request $request): bool
    {
        $secret = (string) config('billing.gateways.paymob.hmac_secret');
        if ($secret === '') {
            return false;
        }

        $provided = (string) $request->query('hmac', '');
        if ($provided === '') {
            return false;
        }

        $obj = (array) ($request->json('obj') ?? []);
        if ($obj === []) {
            return false;
        }

        $concat = '';
        foreach (self::TRANSACTION_HMAC_FIELDS as $path) {
            $concat .= $this->normalizeHmacValue($this->dotGet($obj, $path));
        }

        $computed = hash_hmac('sha512', $concat, $secret);

        return hash_equals($computed, strtolower($provided));
    }

    /**
     * Normalize a value into the string form Paymob signs.
     *
     * Rules:
     *   - true  → 'true'
     *   - false → 'false'
     *   - null  → ''
     *   - other → (string) $value
     */
    protected function normalizeHmacValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Read a dot-path from a nested array. Returns null when any segment is
     * missing rather than throwing — matches Paymob behavior where optional
     * source_data.* fields can be absent on non-card transactions.
     *
     * @param  array<string, mixed>  $data
     */
    protected function dotGet(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $cursor = $data;

        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    // ------------------------------------------------------------------
    // Region routing
    // ------------------------------------------------------------------

    /**
     * Pick the regional Paymob API host. Each Paymob region runs its own
     * Accept stack on a distinct domain; all of them share the same path
     * surface (/api/auth, /api/ecommerce, /api/acceptance, /v1/intention).
     */
    protected function baseUrl(): string
    {
        $region = (string) config('billing.gateways.paymob.region', 'eg');

        return match ($region) {
            'eg' => 'https://accept.paymob.com',
            'ae' => 'https://uae.paymob.com',
            'sa' => 'https://ksa.paymob.com',
            'om' => 'https://oman.paymob.com',
            'pk' => 'https://pakistan.paymob.com',
            default => throw new RuntimeException("Paymob: unsupported region [{$region}]. Supported: eg, ae, sa, om, pk."),
        };
    }

    private function stubMessage(): string
    {
        return 'Paymob: charge/refund flow not yet wired — Phase 3.2. Implement via Unified Intentions API (preferred: POST /v1/intention with secret_key Bearer) OR legacy 3-step Accept (auth-token → order register → payment_key → iframe). Docs: https://developers.paymob.com/paymob-docs/integration-paths/apis';
    }
}
