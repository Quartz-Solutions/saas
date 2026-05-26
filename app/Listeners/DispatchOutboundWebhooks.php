<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Events\SubscriptionUpdated;
use App\Events\TenantMemberInvited;
use App\Events\TenantMemberJoined;
use App\Support\Webhooks\OutboundWebhookDispatcher;

/**
 * Single bridge that translates Laravel-domain events into outbound webhook
 * fan-outs. Each domain event implements `toWebhookPayload()` so we don't have
 * to know its internals here.
 */
class DispatchOutboundWebhooks
{
    public function __construct(
        private readonly OutboundWebhookDispatcher $dispatcher,
    ) {}

    public function handleTenantMemberInvited(TenantMemberInvited $event): void
    {
        $this->dispatcher->dispatch(
            'tenant.member.invited',
            $event->toWebhookPayload(),
            $event->tenant,
        );
    }

    public function handleTenantMemberJoined(TenantMemberJoined $event): void
    {
        $this->dispatcher->dispatch(
            'tenant.member.joined',
            $event->toWebhookPayload(),
            $event->tenant,
        );
    }

    public function handleSubscriptionUpdated(SubscriptionUpdated $event): void
    {
        $this->dispatcher->dispatch(
            'subscription.updated',
            $event->toWebhookPayload(),
            $event->tenant,
        );
    }

    public function handlePaymentSucceeded(PaymentSucceeded $event): void
    {
        $this->dispatcher->dispatch(
            'payment.succeeded',
            $event->toWebhookPayload(),
            $event->tenant,
        );
    }

    /**
     * Register listeners.
     *
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            TenantMemberInvited::class => 'handleTenantMemberInvited',
            TenantMemberJoined::class => 'handleTenantMemberJoined',
            SubscriptionUpdated::class => 'handleSubscriptionUpdated',
            PaymentSucceeded::class => 'handlePaymentSucceeded',
        ];
    }
}
