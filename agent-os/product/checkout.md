# Multi-Gateway Checkout — Architecture Plan

> **Status:** Plan only. The current implementation has the gaps described in
> §1 and needs the rework described in §3–§12.

## 1. Problem statement — what's wrong today

### 1.1 The "Subscribe" button is a lie

On `/t/{slug}/billing/plans`, every plan card renders a `<Form action=POST /billing/subscribe>` with **one hidden input: `gateway = default_gateway`** (`resources/js/pages/billing/plans.tsx:211`). The user never sees a gateway picker — Stripe is silently selected because it's the only `default_gateway` in `config/billing.php`.

That means:

- If you disable Stripe and only configure Paymob, every "Subscribe" click silently posts `gateway=stripe` and 500s on `Gateway [stripe] is not registered`.
- A tenant in Egypt cannot say "I want to pay with Paymob" — only an admin can change the global default.
- Adding a second gateway is a UI change everywhere, not a config flip.

### 1.2 Each gateway returns a different shape from the same method

`SubscriptionGateway::createSubscription(Tenant, Plan, context): Subscription` is the contract. What each driver actually does inside it:

| Gateway   | Mode                          | What `createSubscription` returns           |
|-----------|-------------------------------|--------------------------------------------|
| Stripe    | Subscriptions API (recurring) | Subscription with no redirect — assumes a Customer + payment method already exist. Sign-up uses a different method, `checkoutSessionUrl()` |
| PayPal    | `/v1/billing/subscriptions`   | Subscription with `metadata.approve_url` — user must click an external URL |
| Paymob    | (stub) recurring not native   | throws; recurring is merchant-side via card tokens |
| PayTabs   | Agreement / Repeat Billing    | needs prior charge with `tran_class=recurring` |
| Geidea    | `direct/session/subscription` | Subscription with `metadata.redirect_url`  |
| APS       | Recurring via stored token    | needs prior charge with `service_command=TOKENIZATION` first |
| Telr      | Repeat Billing                | needs prior charge with `tran_class=continuous` |
| HyperPay  | `/v1/subscriptions`           | needs `registrationId` from prior tokenizing charge |
| MyFatoorah| V2 Recurring                  | Subscription with `metadata.redirect_url`  |
| HitPay    | Recurring Billing             | Subscription with redirect URL             |
| Billplz   | None (N-bills)                | not implemented — no native subscriptions  |
| iPay88    | Auto-Debit on token           | needs prior tokenizing charge              |
| Fawry     | Pay-By-Link recurring or MIT  | returns merchant-side recurring config     |

A controller calling the same method on these drivers needs to know which gateway it's talking to in order to know what to do with the result. **That's not polymorphism — it's a tagged union pretending to be a contract.**

### 1.3 No `CheckoutSession` entity

The "user wants to start a subscription" event has no persisted record. If the user clicks Subscribe, gets redirected to PayPal, abandons the tab, then comes back tomorrow — we have nothing to reconcile against. We can't tell them "your last checkout is still pending" because we never stored that intent.

### 1.4 Sign-up and "change plan" use different code paths

The `/get-started` flow uses `StripeGateway::checkoutSessionUrl()` which creates a hosted Checkout Session. The tenant billing flow uses `BillingService::subscribeToPlan() → createSubscription()` which never goes near Checkout Sessions. Two paths, two contracts, two places to update when something changes.

### 1.5 Cannot disable Stripe

The architecture assumes Stripe is the default. Disabling Stripe in `/admin/gateways` removes it from the registry — the plans page still posts `gateway=stripe` and crashes.

---

## 2. Goals

The reworked design must:

1. **Decouple plan-click from gateway-call.** Picking a plan creates a `CheckoutSession` (intent record); the user then picks a gateway and only then is the gateway's API touched.
2. **Polymorphism that actually polymorphs.** Every gateway responds to `initiateCheckout(CheckoutSession)` with a discriminated `CheckoutResult` whose shape tells the controller exactly what to render next.
3. **Survive disabled gateways.** The picker lists only enabled gateways that support the plan's currency. With zero enabled gateways, paid plans show "Payment unavailable" instead of crashing.
4. **One code path for sign-up and change-plan.** `/get-started` and `/t/{slug}/billing/plans` both funnel into the same `CheckoutController`.
5. **Idempotent webhook reconciliation.** Each gateway's webhook locates its `CheckoutSession`, creates the local `Subscription` + `Invoice` + `Payment`, marks the session `completed`. Re-delivery never double-creates.
6. **Abandonment recovery.** Sessions that never complete are visible in `/t/{slug}/billing` so the user can resume or pick a different gateway.

Non-goals (out of scope for this plan):

- Per-tenant gateway lock (each tenant only sees one gateway). The architecture supports it; the policy is a Phase-3.5 follow-up.
- Mixed-currency plans. One plan = one currency.
- Saved payment methods reused across gateways. Each gateway has its own tokenized payment method store.

---

## 3. Design overview

The flow becomes:

```
┌────────────────────────────────────────────────────────────────────────────┐
│ User picks a plan (anywhere: /pricing, /get-started, /t/{slug}/billing/plans) │
└──────────────────────────────┬─────────────────────────────────────────────┘
                                ▼
                ┌─────────────────────────────────┐
                │  POST /checkout/start            │
                │  body: plan_slug                 │
                │  controller: CheckoutController  │
                │  creates: CheckoutSession row    │
                │  state: pending                  │
                └────────────────┬─────────────────┘
                                 ▼
              ┌─────────────────────────────────────┐
              │  GET /checkout/{session}             │
              │  React: <CheckoutPage>               │
              │  shows: plan summary + gateway picker│
              │  (only gateways that support the     │
              │  plan's currency + are enabled)      │
              └────────────────┬─────────────────────┘
                               ▼
              ┌─────────────────────────────────────┐
              │  POST /checkout/{session}/pay        │
              │  body: gateway                       │
              │  controller calls:                   │
              │    $gateway->initiateCheckout($s)    │
              │  returns: CheckoutResult (union)     │
              │  state: awaiting_payment             │
              └────────────────┬─────────────────────┘
                               ▼
            ┌─────────────────────────────────────────────┐
            │   Render the CheckoutResult.kind             │
            ├─────────────────────────────────────────────┤
            │   redirect    →  302 to result.url           │
            │   form_post   →  self-submitting form to URL │
            │   iframe      →  embed iframe with src       │
            │   widget      →  load script + render        │
            │   kiosk_ref   →  show reference + instructions│
            └────────────────┬─────────────────────────────┘
                             ▼
              ┌─────────────────────────────────────┐
              │  User completes payment at gateway   │
              │  Gateway POSTs webhook → /webhooks/{g}│
              │  WebhookController persists event    │
              │  Driver::handleWebhook reconciles:   │
              │    finds CheckoutSession             │
              │    creates Subscription+Invoice+Pmt  │
              │    state: completed                  │
              └────────────────┬─────────────────────┘
                               ▼
              ┌─────────────────────────────────────┐
              │  Gateway redirects browser back to:  │
              │  GET /checkout/{session}/return      │
              │  Controller checks session.status:    │
              │    completed → /t/{slug}/dashboard    │
              │    pending   → "still processing"     │
              │                (auto-refresh w/poll)  │
              │    failed    → back to /checkout      │
              └─────────────────────────────────────┘
```

Two-step UX (`plan → gateway → pay`) is mandatory because of the matrix in §6: some gateways need a hosted page, others a form POST, others a widget. A single "Subscribe" button can't be all of those at once.

---

## 4. Data model

### 4.1 New table: `checkout_sessions`

```php
Schema::create('checkout_sessions', function (Blueprint $table) {
    $table->id();
    $table->ulid('public_id')->unique();            // browser-facing handle
    $table->foreignId('user_id')->constrained();    // who initiated
    $table->foreignId('tenant_id')->constrained();  // tenant the sub is for
    $table->foreignId('plan_id')->constrained()->restrictOnDelete();

    $table->string('intent', 16);                   // 'subscription' | 'one_time'
    $table->string('status', 24);                   // see §7
    $table->string('gateway', 32)->nullable();      // null until user picks
    $table->string('gateway_session_id')->nullable(); // id at the gateway
    $table->string('currency', 3);
    $table->unsignedBigInteger('amount_cents');

    // Polymorphic "what to render next" — populated by initiateCheckout()
    $table->string('result_kind', 16)->nullable(); // 'redirect'|'form_post'|'iframe'|'widget'|'kiosk_ref'
    $table->jsonb('result_payload')->nullable();   // shape depends on kind

    // FKs filled on completion
    $table->foreignId('subscription_id')->nullable()->constrained();
    $table->foreignId('invoice_id')->nullable()->constrained();

    $table->timestamp('expires_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('canceled_at')->nullable();
    $table->string('cancel_reason')->nullable();
    $table->jsonb('metadata')->default('{}');
    $table->timestamps();

    $table->index(['tenant_id', 'status']);
    $table->index(['gateway', 'gateway_session_id']);
    $table->index('public_id');
});
```

Why a separate `checkout_sessions` table rather than reusing `subscriptions`:

- The user may abandon checkout. We don't want orphan `subscriptions` rows.
- The user may try Stripe, fail, then retry with Paymob. That's two sessions, one eventual subscription.
- Webhook reconciliation looks up by `(gateway, gateway_session_id)` — a separate table indexed for exactly that lookup.

### 4.2 Relationships

```
User       1 ── n CheckoutSession
Tenant     1 ── n CheckoutSession
Plan       1 ── n CheckoutSession
Subscription 0..1 ── 1 CheckoutSession  (success only)
Invoice      0..1 ── 1 CheckoutSession  (success only)
```

### 4.3 Existing models — minor updates

`Subscription` gains `checkout_session_id` (nullable FK) so subscription rows can be traced back to the session that produced them. `Invoice` similarly gains `checkout_session_id` so we know the very first invoice on a new sub came from this checkout (vs renewal invoices from later cycles).

No changes to `Plan`, `Tenant`, `Payment`, `WebhookEvent`, or the existing `Gateway*` interfaces.

---

## 5. Polymorphic gateway interface

### 5.1 New interface: `CheckoutGateway`

```php
namespace App\Support\Billing;

interface CheckoutGateway
{
    /**
     * Initiate checkout for the given session. Implementations:
     *   - hit the gateway API (or build the form)
     *   - persist gateway_session_id back to the CheckoutSession
     *   - return a CheckoutResult describing how the front-end should
     *     drive the customer to completion
     *
     * MUST be idempotent: re-calling on a session that's already
     * awaiting_payment should return the previous result.
     */
    public function initiateCheckout(CheckoutSession $session): CheckoutResult;

    /**
     * Currencies this gateway can settle in. Used by the gateway picker
     * to filter out gateways that can't take the plan's currency.
     *
     * @return array<int, string>  ISO 4217 codes, uppercase
     */
    public function supportedCurrencies(): array;

    /**
     * Whether this gateway natively supports recurring subscriptions
     * (vs requiring merchant-side N-bills / MIT charges).
     */
    public function supportsSubscriptions(): bool;
}
```

Every existing driver class will additionally implement `CheckoutGateway`. The existing `PaymentGateway` + `SubscriptionGateway` interfaces stay — they describe *what happens after* the checkout completes (renewals, refunds, cancellations).

### 5.2 The discriminated union: `CheckoutResult`

```php
namespace App\Support\Billing\Checkout;

abstract class CheckoutResult
{
    public function __construct(
        public readonly string $kind,
        public readonly string $gatewaySessionId,
        public readonly ?int $expiresAt = null, // unix seconds
    ) {}

    /** @return array<string, mixed> */
    abstract public function toPayload(): array;
}

final class RedirectCheckout extends CheckoutResult
{
    public function __construct(
        string $gatewaySessionId,
        public readonly string $url,
        ?int $expiresAt = null,
    ) {
        parent::__construct('redirect', $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return ['url' => $this->url];
    }
}

final class FormPostCheckout extends CheckoutResult
{
    /**
     * @param array<string, string> $params  All fields to render as hidden inputs.
     */
    public function __construct(
        string $gatewaySessionId,
        public readonly string $action,
        public readonly array $params,
        public readonly string $method = 'POST',
        ?int $expiresAt = null,
    ) {
        parent::__construct('form_post', $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return ['action' => $this->action, 'method' => $this->method, 'params' => $this->params];
    }
}

final class IframeCheckout extends CheckoutResult
{
    public function __construct(
        string $gatewaySessionId,
        public readonly string $iframeUrl,
        public readonly array $iframeAttributes = [], // height, allow, etc.
        ?int $expiresAt = null,
    ) {
        parent::__construct('iframe', $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return ['iframe_url' => $this->iframeUrl, 'iframe_attributes' => $this->iframeAttributes];
    }
}

final class WidgetCheckout extends CheckoutResult
{
    /**
     * @param array<string, mixed> $widgetConfig
     */
    public function __construct(
        string $gatewaySessionId,
        public readonly string $scriptUrl,
        public readonly array $widgetConfig,
        ?int $expiresAt = null,
    ) {
        parent::__construct('widget', $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return ['script_url' => $this->scriptUrl, 'widget_config' => $this->widgetConfig];
    }
}

final class KioskReferenceCheckout extends CheckoutResult
{
    public function __construct(
        string $gatewaySessionId,
        public readonly string $reference,
        public readonly ?string $instructionsUrl = null,
        ?int $expiresAt = null,
    ) {
        parent::__construct('kiosk_ref', $gatewaySessionId, $expiresAt);
    }

    public function toPayload(): array
    {
        return ['reference' => $this->reference, 'instructions_url' => $this->instructionsUrl];
    }
}
```

Persistence shape on the `CheckoutSession` row:

| `result_kind` | `result_payload` (jsonb)                                       |
|---------------|----------------------------------------------------------------|
| `redirect`    | `{"url": "https://checkout.stripe.com/c/pay/..."}`             |
| `form_post`   | `{"action": "https://sbcheckout.payfort.com/FortAPI/paymentPage", "method": "POST", "params": {...}}` |
| `iframe`      | `{"iframe_url": "https://accept.paymob.com/api/acceptance/iframes/1?payment_token=...", "iframe_attributes": {"height": "650"}}` |
| `widget`      | `{"script_url": "https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=...", "widget_config": {"entity_id_card": "8a82...", "brands": "VISA MASTER MADA"}}` |
| `kiosk_ref`   | `{"reference": "1234567890", "instructions_url": null}`        |

---

## 6. Per-gateway flow types

Mapping each of the 13 catalog gateways to a `CheckoutResult.kind`:

| Gateway   | Subscription kind | One-time kind | Notes |
|-----------|------------------|---------------|-------|
| Stripe    | `redirect` (Checkout Session) | `redirect` (Checkout Session) | Stripe Checkout handles both modes |
| PayPal    | `redirect` (approve URL)      | `redirect` (approve URL)      | PayPal Smart Buttons can also render in-page; redirect is simpler |
| Paymob    | n/a (no native subs)          | `iframe`      | Unified Checkout iframe; recurring is merchant-side via card tokens |
| Fawry     | `kiosk_ref` (Pay-By-Link recurring) | `kiosk_ref` (FawryRef) | Kiosk reference; subscription = pre-generated N bills |
| PayTabs   | `redirect`                    | `redirect`    | Hosted PayPage; managed form also possible (would be `iframe`) |
| Geidea    | `redirect`                    | `redirect`    | Native session URL; Apple Pay button is widget-style — defer |
| APS       | `form_post` (after tokenize)  | `form_post`   | Self-submitting form to `/FortAPI/paymentPage`; recurring via stored token |
| Telr      | `redirect`                    | `redirect`    | REST returns `_links.auth` URL |
| HyperPay  | `widget` (COPYandPAY)         | `widget`      | Inline widget mandatory for PCI scope; backend prepares checkout then JS loads |
| MyFatoorah| `redirect`                    | `redirect`    | SendPayment → InvoiceURL |
| HitPay    | `redirect`                    | `redirect`    | Payment Request → url; Recurring Billing same shape |
| Billplz   | n/a (no native subs)          | `redirect`    | Bill URL; recurring = N bills issued by merchant |
| iPay88    | n/a (no native subs)          | `form_post`   | Self-submitting form; Auto-Debit recurring uses tokenized card MIT |

Gateways with `n/a (no native subs)` are filtered out of the picker when the plan's `intent` is `subscription` AND no merchant-side workaround is desired. For Phase 1 of the rework we filter them out hard; Phase 3 (post-launch) can add N-bills bridging.

---

## 7. State machine

```
                                   ┌─────────────┐
                                   │   pending   │   ← row created, no gateway picked
                                   └──────┬──────┘
                                          │ POST /checkout/{s}/pay
                                          ▼
                                 ┌────────────────┐
                                 │awaiting_payment│   ← gateway called, result stored
                                 └───┬────┬───┬───┘
              gateway webhook        │    │   │    user clicks "cancel" on gateway
              "succeeded"            │    │   │    OR session expired
                                     ▼    ▼   ▼
                              ┌──────────┐ ┌────────┐ ┌───────────┐
                              │completed │ │ failed │ │ canceled  │
                              └──────────┘ └───┬────┘ └───────────┘
                                               │
                                               │ user clicks "retry"
                                               ▼
                                       (new CheckoutSession spawned)
```

States in detail:

| State              | Set by                              | Allowed transitions                |
|--------------------|-------------------------------------|------------------------------------|
| `pending`          | POST `/checkout/start`              | → `awaiting_payment`, `canceled`, `expired` |
| `awaiting_payment` | POST `/checkout/{s}/pay`            | → `completed`, `failed`, `canceled`, `expired` |
| `completed`        | webhook handler on success          | terminal                           |
| `failed`           | webhook handler on declined payment | terminal — user can spawn new session |
| `canceled`         | user clicks "cancel" or hits cancel URL on gateway | terminal |
| `expired`          | scheduled job (`expires_at` passed) | terminal                           |

A pending or awaiting-payment session lasts at most 30 minutes (configurable in `config('billing.checkout.timeout_minutes')`). A scheduled job fires every 5 minutes to mark expired sessions and free any locked gateway sessions where the gateway exposes a cancel endpoint.

The Plan picker UI looks at "user's active checkout sessions" — if one is `awaiting_payment` and not expired, the picker shows a "Resume checkout" CTA instead of "Subscribe".

---

## 8. End-to-end data flows

### 8.1 Stripe Checkout (redirect)

```
user clicks "Pro" on /t/acme/billing/plans
  POST /checkout/start { plan_slug: 'pro' }
    CheckoutController::start()
      creates CheckoutSession #42
        tenant_id=1, plan_id=2, currency='USD', amount_cents=2900
        status='pending', expires_at=now+30m
    → 302 /checkout/01J... (the public_id)

user lands on /checkout/01J...
  CheckoutController::show()
    loads CheckoutSession
    queries enabled gateways that support USD + subscriptions
    returns props: [Stripe, PayPal]
  React renders gateway picker

user clicks "Pay with Stripe"
  POST /checkout/01J.../pay { gateway: 'stripe' }
    CheckoutController::pay()
      $stripe->initiateCheckout($session)
        StripeGateway::initiateCheckout()
          calls client->checkout->sessions->create(mode='subscription', ...)
          updates session: gateway='stripe',
                           gateway_session_id='cs_test_a1...',
                           status='awaiting_payment',
                           result_kind='redirect',
                           result_payload={"url":"https://checkout.stripe.com/..."}
          returns new RedirectCheckout(...)
    controller returns 302 to result.url

browser → Stripe Checkout hosted page → user pays with test card → Stripe redirects

POST /webhooks/stripe (checkout.session.completed)
  WebhookController persists WebhookEvent
  StripeGateway::handleWebhook()
    verifies signature
    parses event.data.object — has session.id = 'cs_test_a1...'
    locates CheckoutSession by gateway_session_id
    creates Subscription row (from event's subscription id)
    creates Invoice row
    creates Payment row
    updates CheckoutSession:
      subscription_id=#7, invoice_id=#12,
      status='completed', completed_at=now()

(slightly later — Stripe redirects browser to success URL)
GET /checkout/01J.../return
  CheckoutController::return()
    loads CheckoutSession (now status=completed)
    → 302 /t/acme/dashboard
```

### 8.2 Paymob Iframe (in-page)

```
user clicks "Paymob" on /checkout/01J...
  POST /checkout/01J.../pay { gateway: 'paymob' }
    PaymobGateway::initiateCheckout()
      POST /v1/intention with secret_key Bearer
      response: { id: 'int_xyz', client_secret: 'csec_abc' }
      iframe URL: {baseUrl}/unifiedcheckout/?publicKey=...&clientSecret=csec_abc
      updates session: status='awaiting_payment',
                       gateway_session_id='int_xyz',
                       result_kind='iframe',
                       result_payload={"iframe_url": "https://accept.paymob.com/unifiedcheckout/?...", "iframe_attributes": {"height": "700"}}
      returns IframeCheckout(...)
  controller returns 200 with the same /checkout/01J... page
  React reads result_payload, swaps the gateway picker for an <iframe src=…>

user completes payment inside the iframe
  Paymob fires HMAC-signed webhook
POST /webhooks/paymob?hmac=...
  PaymobGateway::handleWebhook()
    verifies HMAC-SHA512 over the 20 ordered fields
    parses payload — has intention_id == 'int_xyz'
    locates CheckoutSession by gateway_session_id='int_xyz'
    creates Subscription (no — Paymob doesn't do subs natively; record as one_time)
      OR for subscription intent: creates a "manual recurring" row that
      the BillingService::renewWithStoredToken job will charge each cycle
    creates Payment row
    updates CheckoutSession: status='completed'

iframe posts message to parent window: { kind: 'paymob_checkout_success' }
React listens on window.postMessage, navigates to /checkout/01J.../return
GET /checkout/01J.../return
  loads CheckoutSession (now completed)
  → 302 /t/acme/dashboard
```

### 8.3 APS Hosted (form post)

```
user clicks "APS" on /checkout/01J...
  POST /checkout/01J.../pay { gateway: 'aps' }
    ApsGateway::initiateCheckout()
      Build params:
        service_command = PURCHASE (or TOKENIZATION + RECURRING for subs)
        merchant_identifier = ...
        access_code = ...
        merchant_reference = session.public_id  ← KEY: use session id for reconciliation
        amount = toMinorUnits(2900, 'USD') = 290000  (USD is 2-decimal so 2900 ¢ × 100 = 290000)
                  wait, that's wrong; should be 2900¢ → 2900 minor units
                  (cents already = minor units for USD)
        currency = USD
        return_url = route('checkout.return', { session: '01J...' })
      signature = SHA-256(phrase + sorted_concat + phrase)
      params['signature'] = signature

      updates session: status='awaiting_payment',
                       gateway_session_id=session.public_id (merchant_reference),
                       result_kind='form_post',
                       result_payload={
                         "action": "https://sbcheckout.payfort.com/FortAPI/paymentPage",
                         "method": "POST",
                         "params": { ... all the signed params ... }
                       }
      returns FormPostCheckout(...)

React reads result_payload, renders a hidden auto-submitting <form>
  <form action=... method=POST id="aps-form">
    {Object.entries(params).map(...) → <input hidden>}
  </form>
  <script>document.getElementById('aps-form').submit();</script>

browser POSTs to APS → APS shows hosted card page → user pays → APS redirects
APS also POSTs server-to-server notification to /webhooks/aps
  ApsGateway::handleWebhook()
    verifies signature with response_phrase
    locates CheckoutSession where gateway_session_id == merchant_reference
    creates Subscription/Payment, marks session completed
```

### 8.4 Fawry Kiosk Reference

```
user clicks "Fawry" on /checkout/01J...
  POST /checkout/01J.../pay { gateway: 'fawry' }
    FawryGateway::initiateCheckout()
      POST /ECommerceWeb/Fawry/payments/charge
      response: { referenceNumber: '1234567890', expirationTime: <ms> }

      updates session: status='awaiting_payment',
                       gateway_session_id='1234567890',
                       result_kind='kiosk_ref',
                       result_payload={
                         "reference": "1234567890",
                         "instructions_url": "https://www.fawry.com/howto",
                         "expires_at": <iso>
                       }
      returns KioskReferenceCheckout(...)

React renders a card:
  ╔═══════════════════════════╗
  ║   Pay at any Fawry kiosk   ║
  ║                            ║
  ║   Reference: 1234567890    ║
  ║   Expires: in 30 days      ║
  ║                            ║
  ║   [Copy code]              ║
  ║   [SMS me the code]        ║
  ║   How does this work? →    ║
  ╚═══════════════════════════╝

user goes to a Fawry kiosk + pays in cash → 5 business days later → 
Fawry POSTs server notification to /webhooks/fawry
  FawryGateway::handleWebhook()
    verifies SHA-256 signature
    locates CheckoutSession by gateway_session_id='1234567890'
    creates Subscription/Payment, marks session completed
    sends email "your subscription is now active"

If the user comes back to /checkout/01J... before paying:
  React shows the same kiosk-ref card with a "still waiting" indicator
```

### 8.5 HyperPay Widget (in-page COPYandPAY)

```
user clicks "HyperPay" on /checkout/01J...
  POST /checkout/01J.../pay { gateway: 'hyperpay' }
    HyperPayGateway::initiateCheckout()
      POST /v1/checkouts (form-encoded) with Bearer access_token
        entityId, amount, currency, paymentType=DB, merchantTransactionId=session.public_id
      response: { id: 'check_abc' }
      script URL: {baseUrl}/v1/paymentWidgets.js?checkoutId=check_abc

      updates session: status='awaiting_payment',
                       gateway_session_id='check_abc',
                       result_kind='widget',
                       result_payload={
                         "script_url": "https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=check_abc",
                         "widget_config": {
                           "brands": "VISA MASTER MADA",
                           "shopperResultUrl": "<return_url>"
                         }
                       }
      returns WidgetCheckout(...)

React injects the script tag + renders <form class="paymentWidgets" data-brands="VISA MASTER MADA">
HyperPay's JS takes over, renders the card form inside the page
user pays → HyperPay redirects to widget_config.shopperResultUrl
(server gets the encrypted AES-256-GCM webhook in parallel)

  HyperPayGateway::handleWebhook()
    decrypts AES-256-GCM body with webhook_secret
    parses payload — has checkout id
    locates CheckoutSession by gateway_session_id='check_abc'
    creates Subscription/Payment, marks session completed
```

---

## 9. Webhook reconciliation

### 9.1 The lookup key

Every gateway must store something in its `CheckoutSession.gateway_session_id` that's recoverable from the webhook payload. The natural choices:

| Gateway   | Stored as gateway_session_id  | Recovered from webhook       |
|-----------|------------------------------|------------------------------|
| Stripe    | `cs_test_…` (Checkout Session id) | `event.data.object.id`   |
| PayPal    | order or subscription id     | `event.resource.id`          |
| Paymob    | intention id                 | `payload.obj.intention_id`   |
| Fawry     | `referenceNumber`            | `payload.fawryRefNumber`     |
| PayTabs   | `tran_ref`                   | webhook body `tran_ref`      |
| Geidea    | session id                   | `payload.order.merchantReferenceId` |
| APS       | `merchant_reference`         | response/webhook `merchant_reference` (= session.public_id, our own id) |
| Telr      | order ref                    | webhook `tran_ref`           |
| HyperPay  | checkout id                  | decrypted webhook `payload.id` |
| MyFatoorah| InvoiceId                    | `event.Data.Invoice.Id`      |
| HitPay    | payment_request id           | event body `id`              |
| Billplz   | bill id                      | callback `id`                |
| iPay88    | `RefNo` (= session.public_id) | callback `RefNo`            |

When a gateway uses our own `session.public_id` as its merchant reference (APS, iPay88), reconciliation is trivial and we get idempotency for free.

### 9.2 Reconciliation pattern

Every driver's `handleWebhook()` follows the same skeleton:

```php
public function handleWebhook(Request $request, WebhookEvent $event): WebhookEvent
{
    // 1) Verify signature (driver-specific, already implemented)
    if (! $this->verifySignature($request)) {
        return $event->fill(['status' => 'failed', 'error_message' => 'Signature mismatch'])
            ->tap->save();
    }

    // 2) Parse → extract event_type + gateway_session_id
    $payload = $this->parseWebhook($request);

    // 3) Idempotency guard
    if ($event->status === 'processed') {
        return $event; // already handled in a prior delivery
    }

    // 4) Locate the CheckoutSession (most events relate to one)
    $session = CheckoutSession::query()
        ->where('gateway', $this->id())
        ->where('gateway_session_id', $payload['session_id'])
        ->first();

    // 5) Dispatch by event type (gateway-specific)
    DB::transaction(function () use ($payload, $event, $session) {
        match ($payload['event_type']) {
            'checkout.completed', 'subscription.activated' =>
                $this->onCheckoutCompleted($session, $payload),
            'subscription.renewed' =>
                $this->onSubscriptionRenewed($payload),
            'subscription.canceled' =>
                $this->onSubscriptionCanceled($payload),
            'payment.refunded' =>
                $this->onPaymentRefunded($payload),
            default => null, // ignore irrelevant events (logged for debugging)
        };

        $event->fill([
            'status' => 'processed',
            'processed_at' => now(),
        ])->save();
    });

    return $event->fresh();
}
```

`onCheckoutCompleted` is the only piece that touches `CheckoutSession`:

```php
protected function onCheckoutCompleted(CheckoutSession $session, array $payload): void
{
    if ($session->status === 'completed') {
        return; // idempotent re-delivery
    }

    $subscription = $this->billing->subscriptionFromCheckout($session, $payload);
    $invoice      = $this->billing->invoiceFromCheckout($session, $payload, $subscription);
    $payment      = $this->billing->paymentFromCheckout($session, $payload, $invoice);

    $session->fill([
        'subscription_id' => $subscription?->id,
        'invoice_id'      => $invoice?->id,
        'status'          => 'completed',
        'completed_at'    => now(),
    ])->save();

    // Domain event for downstream listeners (welcome email, audit log, etc.)
    Event::dispatch(new CheckoutCompleted($session->fresh()));
}
```

### 9.3 The new `BillingService` helpers

`BillingService` gets three new methods that each driver calls from `onCheckoutCompleted`:

- `subscriptionFromCheckout(CheckoutSession, payload)` — creates or finds a Subscription row using the data from this webhook
- `invoiceFromCheckout(CheckoutSession, payload, Subscription)` — creates the first invoice
- `paymentFromCheckout(CheckoutSession, payload, Invoice)` — creates the Payment row

The drivers pass gateway-specific fields (period start/end, gateway IDs, payment method type) via `payload` — BillingService stays gateway-agnostic.

For gateways with no native subscriptions (Paymob, Billplz, iPay88): if `session.intent === 'subscription'`, BillingService creates a `Subscription` row with `gateway = '{gateway}'` and `metadata.recurring_via = 'merchant_tokens'`, plus a `renew_token` field. The `BillingService::renewIfDue` cron iterates these and calls each driver's MIT (merchant-initiated transaction) endpoint.

---

## 10. UI surfaces

### 10.1 Plan picker — minimal change

`/t/{slug}/billing/plans` and `/pricing` and `/get-started` all change from rendering an inline `<Form action=POST /billing/subscribe>` to:

```tsx
<Form action={`/checkout/start`} method="post">
  <input type="hidden" name="plan_slug" value={plan.slug} />
  <input type="hidden" name="tenant_id" value={tenant.id} />
  {/* No gateway input. Gateway is picked on next page. */}
  <Button type="submit">{plan.cta}</Button>
</Form>
```

The "Subscribe" button always lands on `/checkout/{public_id}`. One code path, three callers.

### 10.2 `/checkout/{public_id}` — the new page

Layout:

```
╔═══════════════════════════════════════════════════════════════╗
║   ← Back to plans                                              ║
║                                                                ║
║   Order summary                                                ║
║   ┌──────────────────────────────────────────────────────┐    ║
║   │  Pro plan                          $29.00 / month     │    ║
║   │  Workspace: Acme Corp                                  │    ║
║   │  14-day trial                                          │    ║
║   └──────────────────────────────────────────────────────┘    ║
║                                                                ║
║   Choose how to pay                                            ║
║   ┌──────────────────────────────────────────────────────┐    ║
║   │   [Stripe]   Cards, ACH, wallets             Global   │    ║
║   │   [Paymob]   Cards, wallets, Aman/Masary        EG    │    ║
║   │   [PayPal]   PayPal balance, cards          Global    │    ║
║   │   ...                                                  │    ║
║   └──────────────────────────────────────────────────────┘    ║
║                                                                ║
║   [Continue to payment]                                        ║
╚═══════════════════════════════════════════════════════════════╝
```

The picker only shows gateways that:
1. Have `driver_status === 'shipped'` OR `driver_status === 'planned'` with `enabled === true`
2. Support the plan's `currency` (`gateway->supportedCurrencies()` contains it)
3. For subscription intents: support recurring (`gateway->supportsSubscriptions()` is true) OR session has `intent === 'one_time'`
4. Are enabled in config

Each gateway tile shows: name, regions, capabilities badges, and a "Recommended for your region" sticker if `gateway->regions[]` includes the tenant's country.

### 10.3 After gateway pick — render by `result_kind`

```tsx
function CheckoutNextStep({ session }: { session: CheckoutSession }) {
    switch (session.result_kind) {
        case 'redirect':
            window.location.href = session.result_payload.url; // browser nav
            return <Loading message="Redirecting to gateway…" />;

        case 'form_post':
            return <AutoSubmittingForm {...session.result_payload} />;

        case 'iframe':
            return <CheckoutIframe
                src={session.result_payload.iframe_url}
                {...session.result_payload.iframe_attributes}
                onSuccess={() => router.visit(`/checkout/${session.public_id}/return`)}
            />;

        case 'widget':
            return <ScriptedWidget
                scriptUrl={session.result_payload.script_url}
                config={session.result_payload.widget_config}
            />;

        case 'kiosk_ref':
            return <KioskReferenceCard {...session.result_payload} />;
    }
}
```

The four `AutoSubmittingForm` / `CheckoutIframe` / `ScriptedWidget` / `KioskReferenceCard` components are pure-presentational React. Each gateway implementer just produces the right `result_payload` shape; the front end already knows how to render it.

### 10.4 Return URL — `/checkout/{public_id}/return`

This is what gateways redirect to after the customer completes payment on the gateway side:

```php
public function return(string $publicId): Response | RedirectResponse
{
    $session = CheckoutSession::query()->where('public_id', $publicId)->firstOrFail();
    $session->refresh();

    if ($session->status === 'completed') {
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscription activated!']);
        return redirect()->route('tenants.dashboard', ['tenantSlug' => $session->tenant->slug]);
    }

    // Webhook hasn't landed yet (race condition: redirect arrived first)
    // The page polls /checkout/{public_id}/status every 3s
    return Inertia::render('checkout/processing', [
        'session' => $session->toArray(),
        'pollUrl' => "/checkout/{$publicId}/status",
    ]);
}

public function status(string $publicId): JsonResponse
{
    $session = CheckoutSession::query()->where('public_id', $publicId)->firstOrFail();
    return response()->json([
        'status' => $session->status,
        'subscription_id' => $session->subscription_id,
    ]);
}
```

After ~10 seconds of polling without seeing `completed`, the UI offers two actions:
- "I paid but it's still showing pending" → opens a support ticket
- "Try a different gateway" → spawns a new CheckoutSession

### 10.5 Resume + cancel buttons

Each tenant's `/t/{slug}/billing/plans` shows a banner at the top if there's a `pending` or `awaiting_payment` session:

```
⏳ You have a pending checkout for the Pro plan via Stripe.
   [Resume]   [Cancel and try again]
```

---

## 11. Failure modes

| Scenario | What we do |
|----------|------------|
| User clicks Subscribe, picks gateway, gateway API is down | `initiateCheckout` throws; controller returns to gateway picker with toast "Stripe is temporarily unavailable, try another option." Session stays `pending`. |
| User completes payment, webhook never arrives | Return URL shows polling page. After 30s of "still processing", an admin alert fires (audit log). Admin can manually mark session completed via `/admin/checkout-sessions/{id}/force-complete` (Phase 2). |
| User closes the browser mid-checkout, webhook arrives | Session moves to `completed` server-side. Email notification: "Your subscription is now active. Visit your dashboard." |
| User completes payment but on next page the webhook hasn't fired yet (race) | Return URL detects `awaiting_payment`, polls `/status` every 3s, redirects to dashboard once `completed`. |
| User picks Stripe, abandons. Hours later picks Paymob. | New CheckoutSession spawned. Old one expires after 30m, marked `expired`. Both are visible in `/t/{slug}/billing/checkout-history`. |
| Webhook arrives with malformed signature | `failed` row in `webhook_events`, no state change. Stripe's retry policy kicks in. |
| Webhook arrives for a session that doesn't exist locally (e.g. orphaned Stripe customer) | Logged as `webhook_events.status=ignored` with note "no matching CheckoutSession". Audit-able. |
| Two webhooks for the same event (re-delivery) | Idempotency guard: `if ($session->status === 'completed') return;`. Second delivery returns 200 without doing anything. |
| Gateway returns redirect URL containing the customer's email/PII | Sanitise before logging — never store the full URL in `webhook_events.payload` if it has query-string PII. |
| Plan changes mid-checkout (admin archives Pro while a user is paying for it) | The CheckoutSession references plan_id — even if the plan is archived later, the checkout completes against the original plan terms (Stripe's price ID is locked in at session creation). |
| Currency mismatch (user is in EG, plan is USD, only Stripe enabled — does Egypt support Stripe charges?) | The gateway picker filters by `supportedCurrencies()` so this wouldn't show. If somehow a session is created with an incompatible gateway, `initiateCheckout` validates currency and throws `UnsupportedCurrencyException`. |

---

## 12. Migration plan — how we get from "today" to this

Phased so each step is independently shippable + reversible.

### Phase 1 — foundation (1 PR, no UX change visible to users)

- `php artisan make:migration create_checkout_sessions_table`
- `CheckoutSession` model + factory + tests
- `CheckoutGateway` interface, `CheckoutResult` classes (with the 5 subtypes)
- `app/Support/Billing/Checkout/CheckoutService.php` — handles session lifecycle: `start()`, `pick()`, `cancel()`, `expire()`, `findByPublicId()`
- `CheckoutCompleted` domain event
- Sweep job `ExpireStaleCheckouts` registered in scheduler (every 5 minutes)
- No controller or UI changes yet. Tests cover the service in isolation.

### Phase 2 — make `StripeGateway` implement `CheckoutGateway`

- `StripeGateway::initiateCheckout(CheckoutSession): RedirectCheckout` — wraps the existing `checkoutSessionUrl` logic
- `StripeGateway::supportedCurrencies()` returns the 24 ISO codes
- `StripeGateway::supportsSubscriptions()` returns `true`
- Update `StripeGateway::handleWebhook` for the `checkout.session.completed` and `customer.subscription.created` event types to call `onCheckoutCompleted()` (touches CheckoutSession)
- New tests for the full happy-path: start session → initiate Stripe → fake webhook → session completed + Subscription created

After Phase 2 you can demo: hit `/checkout/start?plan=pro&tenant=acme` via tinker, get back a URL, complete it in browser, see a Subscription row appear.

### Phase 3 — the controller + UI

- `CheckoutController::start()` — creates session, redirects to `/checkout/{public_id}`
- `CheckoutController::show()` — renders gateway picker
- `CheckoutController::pay()` — calls driver's `initiateCheckout`, redirects/renders per `result_kind`
- `CheckoutController::return()` — handles gateway return URL
- `CheckoutController::status()` — JSON endpoint for polling
- `resources/js/pages/checkout/show.tsx` — order summary + gateway picker
- `resources/js/pages/checkout/processing.tsx` — polling page
- `resources/js/components/checkout/*.tsx` — `AutoSubmittingForm`, `CheckoutIframe`, `ScriptedWidget`, `KioskReferenceCard`, `GatewayTile`
- Update `/t/{slug}/billing/plans` to POST to `/checkout/start` (1 hidden input change, no other code change)
- Update `/pricing` plan picker similarly
- Update `/get-started` to use the same `/checkout/start` instead of `StripeGateway::checkoutSessionUrl` directly

After Phase 3: `/get-started` and `/t/{slug}/billing/plans` both flow through `/checkout` — tenant can pick gateway, sees the right widget, completes payment, lands on dashboard.

### Phase 4 — port the remaining 12 drivers

Each driver gets:
- `implements CheckoutGateway` clause
- `initiateCheckout` method returning the appropriate `CheckoutResult` subclass per the matrix in §6
- `supportedCurrencies()` from the catalog research
- `supportsSubscriptions()` boolean
- `handleWebhook` updated to call `onCheckoutCompleted()` for the matching event

These 12 ports are independent — they parallel-agentable, one per driver, ~150 lines per driver. The Stripe port from Phase 2 is the template.

### Phase 5 — kill the old paths

- Remove `BillingController::subscribe` (it's been a no-op route since Phase 3; the plans page POSTs to `/checkout/start` now)
- Remove `BillingService::subscribeToPlan` once nothing calls it directly
- Remove `StripeGateway::checkoutSessionUrl` once `/get-started` uses `CheckoutService::start` instead

### Phase 6 — operational polish

- `/admin/checkout-sessions` index for support staff (view abandoned sessions, force-complete stuck ones)
- `/t/{slug}/billing/checkout-history` for tenants to see their attempts
- Email notifications on session abandonment (after 24h pending, send "did you forget?" reminder with resume link)
- Webhook replay UI per gateway already exists at `/admin/webhooks` — verify it works for the new checkout events
- Audit log entries for each session state transition

---

## 13. Per-gateway implementation matrix

The table below maps each gateway to its checkout shape so contributors can implement one in isolation.

| Gateway   | `result_kind` | `gateway_session_id` source | Init API call | Webhook event types that complete | Sandbox creds required |
|-----------|---------------|------------------------------|----------------|------------------------------------|------------------------|
| Stripe    | `redirect`    | `cs_test_*` Checkout Session id | `POST /v1/checkout/sessions` mode=subscription | `checkout.session.completed`, `customer.subscription.created` | Test API key + webhook signing secret (CLI in dev) |
| PayPal    | `redirect`    | `WH-…` order id or `I-…` subscription id | `POST /v1/billing/subscriptions` OR `POST /v2/checkout/orders` | `BILLING.SUBSCRIPTION.ACTIVATED`, `PAYMENT.CAPTURE.COMPLETED` | Sandbox client id + secret + webhook id |
| Paymob    | `iframe`      | intention id from `POST /v1/intention` response | Unified Intentions API | `TRANSACTION.processed` callback (HMAC-SHA512 query param) | Sandbox secret_key + integration_id_card + iframe_id + hmac_secret |
| Fawry     | `kiosk_ref`   | `referenceNumber` from charge response | `POST /ECommerceWeb/Fawry/payments/charge` with `paymentMethod=PAYATFAWRY` | `PAID` server-notification-V2 SHA-256 hash | Staging merchant_code + secure_key |
| PayTabs   | `redirect`    | `tran_ref` from PayPage create response | `POST /payment/request` tran_type=sale | `paid` IPN with HMAC-SHA256 of raw body | Region-specific profile_id + server_key |
| Geidea    | `redirect`    | session.id from create-session response | `POST /payment-intent/api/v2/direct/session` | `payment_succeeded` HMAC-SHA256-base64 callback | Public key + API password |
| APS       | `form_post`   | `merchant_reference` (= session.public_id) | self-submitting form to `/FortAPI/paymentPage` with signed params | response-phrase-verified server notification | merchant_identifier + access_code + sha_request_phrase + sha_response_phrase |
| Telr      | `redirect`    | order ref from REST Create Order | `POST /gateway/order.json method=create` | IPN with multiple `*_check` SHA1(secret:fields…) | store_id + auth_key + ipn_secret |
| HyperPay  | `widget`      | checkout id from `/v1/checkouts` | `POST /v1/checkouts` form-encoded | AES-256-GCM-encrypted webhook body | Bearer access_token + entityId(s) + webhook AES key |
| MyFatoorah| `redirect`    | InvoiceId from SendPayment | `POST /v2/SendPayment` NotificationOption=LNK | `TransactionsStatusChanged` HMAC-SHA256-base64 csv | Per-country Bearer api_token + webhook secret |
| HitPay    | `redirect`    | id from payment-requests | `POST /v1/payment-requests` form-encoded | `HITPAY-Signature` HMAC-SHA256 of raw body | api_key + salt |
| Billplz   | `redirect`    | bill id | `POST /v3/bills` form-encoded, Basic auth | callback HMAC-SHA512 over sorted `key value` pipe-joined | api_key + collection_id + x_signature_key (sandbox account) |
| iPay88    | `form_post`   | RefNo (= session.public_id) | self-submitting form, HMAC-SHA512 signature | BackendURL POST + `RECEIVEOK` reply | merchant_code + merchant_key per country |

---

## 14. Testing strategy

### 14.1 Unit tests — pure logic

- `CheckoutSession` state machine (allowed transitions, terminal states)
- Each `CheckoutResult` subclass — `toPayload()` shape
- `CheckoutService::start()` validates plan + tenant + currency
- `CheckoutService::expire()` only touches sessions past their `expires_at`

### 14.2 Driver unit tests — mocked API

For each driver:
- `initiateCheckout()` builds the right params, posts to the right URL, returns the right `CheckoutResult` subclass
- `handleWebhook()` for `checkout.completed`-equivalent event correctly:
  - verifies signature
  - locates CheckoutSession
  - creates Subscription, Invoice, Payment
  - marks session completed
- `handleWebhook()` is idempotent — a second delivery for the same event_id is a no-op

Mock the Stripe SDK / Http facade. Don't hit real APIs.

### 14.3 Integration tests — controller + driver + DB

For each driver:
- `POST /checkout/start { plan_slug, tenant_id }` → row exists, status='pending'
- `POST /checkout/{s}/pay { gateway }` → driver called, result_kind/payload populated, status='awaiting_payment'
- `POST /webhooks/{gateway}` with a recorded fixture payload → session completed, Subscription created
- `GET /checkout/{s}/return` after webhook → 302 to dashboard

### 14.4 Browser tests (later phase — Dusk or Playwright)

- Real Stripe Checkout end-to-end with test card 4242 4242 4242 4242
- Form-post gateways: verify the form auto-submits and the user lands on the right hosted page (mocked)
- Iframe gateways: verify the postMessage from iframe triggers the return navigation

### 14.5 Webhook signature regression tests

Every driver has a "real captured webhook payload" fixture in `tests/Fixtures/webhooks/{gateway}/*.json` + the signature header used at capture time. Tests must verify those fixtures still pass signature checks — guards against accidental refactors of the verify code.

---

## 15. Open questions / decisions deferred

These don't block Phase 1 but need resolution before Phase 4 / 5:

1. **Per-tenant gateway lock vs free choice.** Should an admin be able to say "Acme Corp can only check out with Stripe"? Today the picker is plan-wide; locking down per-tenant is a row in `tenant.settings.allowed_gateways`. Easy to add when needed.

2. **Tax handling.** Stripe Tax / PayTabs VAT / etc. — does Tax happen on the gateway side, or do we compute locally before initiating checkout? Initial answer: gateway side for Stripe (Stripe Tax flag on Checkout Session), local for others until proven otherwise.

3. **Multiple invoices for one CheckoutSession.** First invoice always tied to the session. Renewal invoices have `checkout_session_id = NULL`. Refund flow: refund touches the existing Invoice + Payment + creates an adjustment Invoice — does the adjustment reference the original session? Lean towards NO; refunds are post-checkout adjustments.

4. **Failed-payment retry inside a session.** If Stripe Checkout fails the card, does the user retry inside the same session or spawn a new one? Stripe's behavior: same session is retryable. Other gateways: typically new session. Decision: per-driver, exposed as `CheckoutResult.retryable: bool` on a follow-up.

5. **Coupons / discounts.** Stripe has `promotion_code`. Others either no built-in or per-gateway custom param. Defer to Phase 6 polish.

6. **Saved payment methods.** Once a tenant has paid via Stripe, can they renew without re-entering card? Stripe yes (Customer + payment_method). For other gateways: each one has its own tokenization model. Out of scope here.

7. **Free plan path.** Free plans skip the gateway picker entirely — `CheckoutService::start` for a `price_cents=0` plan immediately marks the session `completed` and creates a free Subscription synchronously. The redirect is straight to the dashboard.

---

## 16. Quick reference — file layout

```
app/Models/
  CheckoutSession.php

app/Support/Billing/
  CheckoutGateway.php                 ← new interface
  Checkout/
    CheckoutResult.php                ← abstract base
    RedirectCheckout.php
    FormPostCheckout.php
    IframeCheckout.php
    WidgetCheckout.php
    KioskReferenceCheckout.php
    CheckoutService.php               ← session lifecycle
  PaymentGateway.php                  ← unchanged
  SubscriptionGateway.php             ← unchanged
  Stripe/StripeGateway.php            ← implements CheckoutGateway
  PayPal/PayPalGateway.php            ← implements CheckoutGateway
  ... 11 other drivers                ← implement CheckoutGateway

app/Http/Controllers/Checkout/
  CheckoutController.php              ← start/show/pay/return/status

app/Http/Requests/Checkout/
  StartCheckoutRequest.php
  PayCheckoutRequest.php

app/Jobs/
  ExpireStaleCheckouts.php

app/Events/
  CheckoutStarted.php
  CheckoutCompleted.php
  CheckoutFailed.php
  CheckoutAbandoned.php

routes/web.php                        ← new /checkout/* routes (public + guest)

database/migrations/
  YYYY_MM_DD_HHmmss_create_checkout_sessions_table.php
  YYYY_MM_DD_HHmmss_add_checkout_session_id_to_subscriptions.php
  YYYY_MM_DD_HHmmss_add_checkout_session_id_to_invoices.php

resources/js/pages/checkout/
  show.tsx                            ← gateway picker
  processing.tsx                      ← polling page

resources/js/components/checkout/
  GatewayTile.tsx
  AutoSubmittingForm.tsx
  CheckoutIframe.tsx
  ScriptedWidget.tsx
  KioskReferenceCard.tsx
  CheckoutNextStep.tsx                ← switches on result_kind

tests/Feature/Checkout/
  CheckoutStartTest.php
  CheckoutPickGatewayTest.php
  CheckoutReturnTest.php
  ReconcileStripeCheckoutTest.php
  ReconcilePayPalCheckoutTest.php
  ... 11 others

tests/Unit/Checkout/
  CheckoutSessionStateMachineTest.php
  CheckoutResultPayloadTest.php
```

---

## 17. Summary — what changes vs today

| Concept                          | Today                              | After this plan                              |
|----------------------------------|-------------------------------------|----------------------------------------------|
| Plan-click action                | POST /billing/subscribe with hidden default_gateway | POST /checkout/start, no gateway yet         |
| Gateway selection                | Implicit (default_gateway)         | Explicit user choice on /checkout page       |
| Intent record                    | None — abandons leave no trace     | `CheckoutSession` row, surfaceable in UI     |
| Polymorphism                     | Drivers return Subscription in 13 different shapes | Drivers return `CheckoutResult` discriminated union — 5 known kinds, controller renders by kind |
| Sign-up vs change-plan code path | Two — `/get-started` + `BillingController::subscribe` | One — both go through `/checkout/start`      |
| Stripe disabled handling         | Silent crash on Subscribe          | Gateway absent from picker; UI shows "no compatible gateway" if zero match |
| Webhook reconciliation           | Each driver's `createSubscription` returns local Subscription pre-completion | Webhooks own completion via `onCheckoutCompleted` — local Subscription only exists once gateway confirms |
| Resume abandoned checkout        | Not possible                       | Banner on plans page + history list          |
| Failure recovery                 | Toast + back to picker             | CheckoutSession.status=failed → spawn new session, audit trail preserved |
