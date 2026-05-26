<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvoiceManualPaymentRequest;
use App\Http\Requests\Admin\PaymentRefundRequest;
use App\Http\Requests\Admin\SubscriptionApplyCreditRequest;
use App\Http\Requests\Admin\SubscriptionCancelRequest;
use App\Http\Requests\Admin\SubscriptionChangePlanRequest;
use App\Http\Requests\Admin\SubscriptionCompMonthsRequest;
use App\Http\Requests\Admin\SubscriptionReactivateRequest;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Billing\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Throwable;

/**
 * Super Admin overrides on Subscriptions/Payments/Invoices.
 *
 * Every action goes through BillingService (the canonical single seam)
 * and is appended to audit_logs via the model observers + an extra
 * explicit AuditLog row that captures the admin note + payload.
 *
 * Cancel/reactivate map directly to existing BillingService methods.
 * Credit/comp/refund/manual-payment land here as new BillingService methods.
 */
class SubscriptionActionsController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function changePlan(SubscriptionChangePlanRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();
        $newPlan = Plan::query()->findOrFail($data['plan_id']);

        try {
            $this->billing->changePlan(
                $subscription,
                $newPlan,
                ['prorate' => (bool) ($data['prorate'] ?? true)],
            );
        } catch (Throwable $e) {
            return $this->error('change-plan failed: '.$e->getMessage());
        }

        $this->recordAdminAction(
            $subscription,
            'admin.subscription.plan_changed',
            ['plan_id_old' => $subscription->plan_id, 'plan_id_new' => $newPlan->id],
            $data['admin_note'] ?? null,
        );

        return $this->ok(__('Plan changed.'), $subscription);
    }

    public function cancel(SubscriptionCancelRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->billing->cancel(
                $subscription,
                $data['reason'],
                ['immediately' => (bool) ($data['immediately'] ?? false)],
            );
        } catch (Throwable $e) {
            return $this->error('cancel failed: '.$e->getMessage());
        }

        $this->recordAdminAction(
            $subscription,
            'admin.subscription.canceled',
            ['reason' => $data['reason'], 'immediately' => (bool) ($data['immediately'] ?? false)],
            $data['admin_note'] ?? null,
        );

        return $this->ok(__('Subscription canceled.'), $subscription);
    }

    public function reactivate(SubscriptionReactivateRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();

        if (! $subscription->cancel_at_period_end) {
            return $this->error(__('This subscription is not pending cancellation.'));
        }

        try {
            $this->billing->resume($subscription);
        } catch (Throwable $e) {
            return $this->error('reactivate failed: '.$e->getMessage());
        }

        $this->recordAdminAction(
            $subscription,
            'admin.subscription.reactivated',
            [],
            $data['admin_note'] ?? null,
        );

        return $this->ok(__('Subscription reactivated.'), $subscription);
    }

    public function applyCredit(SubscriptionApplyCreditRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->billing->applyCredit(
                $subscription,
                (int) $data['amount_cents'],
                (string) $data['reason'],
            );
        } catch (Throwable $e) {
            return $this->error('apply credit failed: '.$e->getMessage());
        }

        $this->recordAdminAction(
            $subscription,
            'admin.subscription.credit_applied',
            ['amount_cents' => (int) $data['amount_cents'], 'reason' => $data['reason']],
            $data['admin_note'] ?? null,
        );

        return $this->ok(__('Credit applied.'), $subscription);
    }

    public function compMonths(SubscriptionCompMonthsRequest $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->billing->compMonths(
                $subscription,
                (int) $data['months'],
                (string) $data['reason'],
            );
        } catch (Throwable $e) {
            return $this->error('comp months failed: '.$e->getMessage());
        }

        $this->recordAdminAction(
            $subscription,
            'admin.subscription.comped',
            ['months' => (int) $data['months'], 'reason' => $data['reason']],
            $data['admin_note'] ?? null,
        );

        return $this->ok(__(':months month(s) comped.', ['months' => (int) $data['months']]), $subscription);
    }

    public function refundPayment(PaymentRefundRequest $request, Payment $payment): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->billing->refundPayment(
                $payment,
                isset($data['amount_cents']) ? (int) $data['amount_cents'] : null,
                (string) $data['reason'],
            );
        } catch (Throwable $e) {
            return $this->error('refund failed: '.$e->getMessage());
        }

        $subscription = $this->subscriptionFor($payment);
        if ($subscription !== null) {
            $this->recordAdminAction(
                $subscription,
                'admin.payment.refunded',
                [
                    'payment_id' => $payment->id,
                    'amount_cents' => $data['amount_cents'] ?? $payment->amount_cents,
                    'reason' => $data['reason'],
                ],
                $data['admin_note'] ?? null,
            );
        }

        return $this->ok(__('Payment refunded.'), $subscription);
    }

    public function recordManualPayment(InvoiceManualPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validated();

        try {
            $payment = $this->billing->recordManualPayment(
                $invoice,
                (int) $data['amount_cents'],
                (string) $data['method'],
                $data['reference'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->error('record manual payment failed: '.$e->getMessage());
        }

        $subscription = $invoice->subscription;
        if ($subscription !== null) {
            $this->recordAdminAction(
                $subscription,
                'admin.invoice.manual_payment_recorded',
                [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'amount_cents' => (int) $data['amount_cents'],
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                ],
                $data['admin_note'] ?? null,
            );
        }

        return $this->ok(__('Manual payment recorded.'), $subscription);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordAdminAction(Subscription $subscription, string $action, array $payload, ?string $note): void
    {
        AuditLog::query()->create([
            'tenant_id' => $subscription->tenant_id,
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->id,
            'old_values' => null,
            'new_values' => array_merge($payload, $note !== null ? ['admin_note' => $note] : []),
        ]);
    }

    protected function subscriptionFor(Payment $payment): ?Subscription
    {
        if ($payment->invoice_id === null) {
            return null;
        }
        $invoice = Invoice::query()->find($payment->invoice_id);

        return $invoice?->subscription;
    }

    protected function ok(string $message, ?Subscription $subscription): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $subscription
            ? to_route('admin.subscriptions.show', ['subscription' => $subscription->id])
            : back();
    }

    protected function error(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
