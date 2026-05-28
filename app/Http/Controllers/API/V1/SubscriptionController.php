<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Controllers\API\V1\Concerns\HandlesIdempotency;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Tenant subscription lifecycle.
 *
 * @group Billing
 *
 * @authenticated
 */
class SubscriptionController extends ApiController
{
    use HandlesIdempotency;

    public function __construct(private readonly BillingService $billing) {}

    /**
     * Current non-terminal subscription. Ability: `billing:read`.
     */
    public function current(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'billing:read');

        $tenant = $this->currentApiTenant();
        $sub = $this->billing->currentSubscription($tenant);

        if ($sub === null) {
            return response()->json(['data' => null]);
        }

        return SubscriptionResource::make($sub->load('plan'))->response();
    }

    /**
     * Paginated subscription history. Ability: `billing:read`.
     */
    public function history(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'billing:read');

        $tenant = $this->currentApiTenant();

        $paginator = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->with('plan')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return SubscriptionResource::collection($paginator)->response();
    }

    /**
     * POST /subscription/change-plan. Ability: `billing:write`. Owner/Admin.
     */
    public function changePlan(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'billing:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        return $this->withIdempotency($request, function () use ($request, $tenant) {
            $data = Validator::make($request->all(), [
                'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            ])->validate();

            $current = $this->billing->currentSubscription($tenant);
            if ($current === null) {
                return response()->json([
                    'message' => 'No active subscription to change.',
                ], 422);
            }

            $newPlan = Plan::query()->where('slug', $data['plan_slug'])->firstOrFail();
            $updated = $this->billing->changePlan($current, $newPlan);

            return SubscriptionResource::make($updated->load('plan'))->response();
        });
    }

    /**
     * POST /subscription/cancel. Body: at_period_end, reason. Ability: `billing:write`.
     */
    public function cancel(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'billing:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        return $this->withIdempotency($request, function () use ($request, $tenant) {
            $data = Validator::make($request->all(), [
                'at_period_end' => ['nullable', 'boolean'],
                'reason' => ['nullable', 'string', 'max:500'],
            ])->validate();

            $current = $this->billing->currentSubscription($tenant);
            if ($current === null) {
                return response()->json([
                    'message' => 'No active subscription to cancel.',
                ], 422);
            }

            $immediately = ! (bool) ($data['at_period_end'] ?? true);
            $canceled = $this->billing->cancel($current, $data['reason'] ?? null, ['immediately' => $immediately]);

            return SubscriptionResource::make($canceled->load('plan'))->response();
        });
    }

    /**
     * POST /subscription/reactivate. Ability: `billing:write`.
     */
    public function reactivate(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'billing:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        return $this->withIdempotency($request, function () use ($tenant) {
            $current = $this->billing->currentSubscription($tenant);
            if ($current === null || ! $current->cancel_at_period_end) {
                return response()->json([
                    'message' => 'Nothing to reactivate.',
                ], 422);
            }

            $resumed = $this->billing->resume($current);

            return SubscriptionResource::make($resumed->load('plan'))->response();
        });
    }

    private function assertOwnerOrAdmin(Request $request, Tenant $tenant): void
    {
        $user = $this->actor($request);
        if ($tenant->owner_id === $user->id) {
            return;
        }

        setPermissionsTeamId($tenant->id);
        if ($user->hasAnyRole(['Owner', 'Admin'])) {
            return;
        }

        abort(403, 'Owner or Admin role required.');
    }
}
