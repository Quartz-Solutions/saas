<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Controllers\API\V1\Concerns\ScopesApiQuery;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Stripe\StripeGateway;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invoices.
 *
 * @group Billing
 *
 * @authenticated
 */
class InvoicesController extends ApiController
{
    use ScopesApiQuery;

    /**
     * Paginated invoices. Ability: `billing:read`.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'billing:read');

        $tenant = $this->currentApiTenant();

        $query = Invoice::query()->where('tenant_id', $tenant->id);
        $query = $this->applyFilters($query, $request, ['status', 'gateway', 'currency']);
        $query = $this->applySort($query, $request, ['issued_at', 'paid_at', 'total_cents', 'created_at'], 'issued_at');

        $paginator = $query->paginate($this->perPage($request));

        return InvoiceResource::collection($paginator)->response();
    }

    /**
     * Show one invoice. Ability: `billing:read`.
     */
    public function show(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'billing:read');

        $invoice = $this->resolveInvoice($id);

        return InvoiceResource::make($invoice)->response();
    }

    /**
     * Stream the invoice PDF. Ability: `billing:read`. Prefers the
     * gateway-hosted PDF when available; falls back to DomPDF render.
     */
    public function pdf(Request $request, string $slug, int $id, GatewayRegistry $registry): Response|RedirectResponse
    {
        $this->requireAbility($request, 'billing:read');

        $invoice = $this->resolveInvoice($id);

        $gateway = $registry->find((string) $invoice->gateway);
        if ($gateway instanceof StripeGateway) {
            $hostedUrl = $gateway->gatewayPdfUrl($invoice);
            if (filled($hostedUrl)) {
                return redirect()->away($hostedUrl);
            }
        }

        $tenant = $this->currentApiTenant();
        $invoice->loadMissing(['tenant', 'lines', 'subscription.plan']);

        $pdf = Pdf::loadView('billing.invoice_pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
        ]);

        return $pdf->download("invoice-{$invoice->number}.pdf");
    }

    private function resolveInvoice(int $id): Invoice
    {
        $tenant = $this->currentApiTenant();

        $invoice = Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($id)
            ->first();

        if ($invoice === null) {
            abort(404, "Invoice [{$id}] not found.");
        }

        return $invoice;
    }
}
