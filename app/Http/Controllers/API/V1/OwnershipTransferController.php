<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Models\User;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Initiate transfer of tenant ownership to another member. The new owner
 * accepts via the in-app/email link — this endpoint only creates the
 * pending transfer record.
 *
 * @group Tenants
 *
 * @authenticated
 */
class OwnershipTransferController extends ApiController
{
    public function __construct(private readonly TenantService $service) {}

    /**
     * POST /api/v1/tenants/{slug}/transfer-ownership
     * Ability: `tenants:write`. Owner only.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'tenants:write');

        $tenant = $this->currentApiTenant();
        $caller = $this->actor($request);

        if ($tenant->owner_id !== $caller->id) {
            abort(403, 'Only the current owner can initiate transfer.');
        }

        $data = Validator::make($request->all(), [
            'new_owner_id' => ['required_without:new_owner_email', 'integer', 'exists:users,id'],
            'new_owner_email' => ['required_without:new_owner_id', 'email', 'max:255'],
        ])->validate();

        $newOwner = isset($data['new_owner_id'])
            ? User::query()->findOrFail($data['new_owner_id'])
            : User::query()->where('email', strtolower($data['new_owner_email']))->firstOrFail();

        $transfer = $this->service->transferOwnership($tenant, $caller, $newOwner);

        return response()->json([
            'data' => [
                'id' => $transfer->id,
                'tenant_id' => $transfer->tenant_id,
                'current_owner_id' => $transfer->current_owner_id,
                'new_owner_id' => $transfer->new_owner_id,
                'token' => $transfer->token,
                'expires_at' => $transfer->expires_at?->toIso8601String(),
                'created_at' => $transfer->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
