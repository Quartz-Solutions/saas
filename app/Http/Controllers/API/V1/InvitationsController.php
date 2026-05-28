<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Controllers\API\V1\Concerns\HandlesIdempotency;
use App\Http\Resources\InvitationResource;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Support\Tenancy\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Tenant invitations.
 *
 * @group Tenant membership
 *
 * @authenticated
 */
class InvitationsController extends ApiController
{
    use HandlesIdempotency;

    public function __construct(private readonly TenantService $service) {}

    /**
     * Pending invitations. Ability: `members:read`.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'members:read');

        $tenant = $this->currentApiTenant();

        $paginator = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return InvitationResource::collection($paginator)->response();
    }

    /**
     * Invite by email + role. Ability: `members:write`.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'members:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        return $this->withIdempotency($request, function () use ($request, $tenant) {
            $data = Validator::make($request->all(), [
                'email' => ['required', 'email', 'max:255'],
                'role' => ['required', 'string', Rule::in(TenantService::ROLES)],
            ])->validate();

            $invitation = $this->service->invite(
                $tenant,
                $this->actor($request),
                $data['email'],
                $data['role'],
            );

            return InvitationResource::make($invitation)->response()->setStatusCode(201);
        });
    }

    /**
     * Revoke a pending invitation. Ability: `members:write`.
     */
    public function destroy(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'members:write');

        $tenant = $this->currentApiTenant();
        $this->assertOwnerOrAdmin($request, $tenant);

        $invitation = TenantInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($id)
            ->first();

        if ($invitation === null) {
            abort(404, "Invitation [{$id}] not found.");
        }

        if ($invitation->accepted_at !== null) {
            return response()->json([
                'message' => 'Cannot revoke an accepted invitation.',
            ], 422);
        }

        $this->service->revokeInvitation($invitation);

        return response()->json([], 204);
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
