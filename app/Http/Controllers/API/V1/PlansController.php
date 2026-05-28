<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plan catalog.
 *
 * @group Billing
 *
 * @authenticated
 */
class PlansController extends ApiController
{
    /**
     * GET /api/v1/plans. Ability: `billing:read`. Token-gated by design —
     * the public pricing page lives at /pricing (Inertia).
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'billing:read');

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get();

        return PlanResource::collection($plans)->response();
    }
}
