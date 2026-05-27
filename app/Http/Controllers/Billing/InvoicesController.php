<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Billing\GatewayRegistry;
use App\Support\Billing\Stripe\StripeGateway;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class InvoicesController extends Controller
{
    private const ALLOWED_SORT = ['id', 'number', 'status', 'total_cents', 'issued_at', 'due_at', 'paid_at', 'created_at'];

    private const PER_PAGE = 15;

    /**
     * Tenant-scoped invoices index.
     */
    public function index(Request $request, string $tenantSlug): Response
    {
        $tenant = app('currentTenant');

        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'issued_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = Invoice::query()->where('tenant_id', $tenant->id);

        if ($search !== '') {
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('number', $likeOperator, "%{$search}%")
                    ->orWhere('gateway_invoice_id', $likeOperator, "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('billing/invoices', [
            'invoices' => [
                'data' => collect($paginator->items())->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'status' => $invoice->status,
                    'gateway' => $invoice->gateway,
                    'currency' => $invoice->currency,
                    'total_cents' => (int) $invoice->total_cents,
                    'amount_paid_cents' => (int) $invoice->amount_paid_cents,
                    'amount_due_cents' => (int) $invoice->amount_due_cents,
                    'issued_at' => optional($invoice->issued_at)->toIso8601String(),
                    'due_at' => optional($invoice->due_at)->toIso8601String(),
                    'paid_at' => optional($invoice->paid_at)->toIso8601String(),
                    'hosted_invoice_url' => $invoice->hosted_invoice_url,
                ])->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                ],
            ],
            'tableState' => [
                'search' => $search,
                'filters' => (object) $filters,
                'sort' => ['column' => $sort, 'direction' => $direction],
            ],
        ]);
    }

    /**
     * Download a PDF of a single invoice. Refuses cross-tenant access.
     *
     * Prefers the gateway-hosted PDF (signed, branded) when the driver
     * exposes one; falls back to a locally-rendered DomPDF.
     */
    public function pdf(Request $request, string $tenantSlug, Invoice $invoice, GatewayRegistry $registry): HttpResponse|RedirectResponse
    {
        $tenant = app('currentTenant');

        if ($invoice->tenant_id !== $tenant->id) {
            throw new AccessDeniedHttpException('Invoice does not belong to this tenant.');
        }

        // Prefer the gateway-hosted PDF when available.
        $gateway = $registry->find((string) $invoice->gateway);
        if ($gateway instanceof StripeGateway) {
            $hostedUrl = $gateway->gatewayPdfUrl($invoice);
            if (filled($hostedUrl)) {
                return redirect()->away($hostedUrl);
            }
        }

        $invoice->loadMissing(['tenant', 'lines', 'subscription.plan']);

        $pdf = Pdf::loadView('billing.invoice_pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
        ]);

        return $pdf->download("invoice-{$invoice->number}.pdf");
    }
}
