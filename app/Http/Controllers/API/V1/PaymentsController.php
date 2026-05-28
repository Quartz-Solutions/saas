<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Controllers\API\V1\Concerns\ScopesApiQuery;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payments.
 *
 * @group Billing
 *
 * @authenticated
 */
class PaymentsController extends ApiController
{
    use ScopesApiQuery;

    /**
     * Paginated payment list. Ability: `billing:read`.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'billing:read');

        $tenant = $this->currentApiTenant();

        $query = Payment::query()->where('tenant_id', $tenant->id);
        $query = $this->applyFilters($query, $request, ['status', 'gateway', 'currency']);
        $query = $this->applySort($query, $request, ['captured_at', 'authorized_at', 'amount_cents', 'created_at'], 'created_at');

        $paginator = $query->paginate($this->perPage($request));

        return PaymentResource::collection($paginator)->response();
    }

    /**
     * Show one payment. Ability: `billing:read`.
     */
    public function show(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'billing:read');

        $tenant = $this->currentApiTenant();

        $payment = Payment::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($id)
            ->first();

        if ($payment === null) {
            abort(404, "Payment [{$id}] not found.");
        }

        return PaymentResource::make($payment)->response();
    }
}
