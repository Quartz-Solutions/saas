<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Http\Controllers\API\V1\Concerns\ScopesApiQuery;
use App\Http\Resources\AuditEntryResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant audit log.
 *
 * @group Audit
 *
 * @authenticated
 */
class AuditLogController extends ApiController
{
    use ScopesApiQuery;

    /**
     * Cursor-paginated list. Filterable by ?action and ?user_id.
     * Ability: `audit:read`.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $this->requireAbility($request, 'audit:read');

        $tenant = $this->currentApiTenant();

        $query = AuditLog::query()->where('tenant_id', $tenant->id);
        $query = $this->applyFilters($query, $request, ['action', 'user_id', 'auditable_type']);

        if ($request->filled('since')) {
            $query->where('created_at', '>=', $request->input('since'));
        }

        $query->orderByDesc('id');

        $perPage = $this->perPage($request);
        $paginator = $request->filled('cursor')
            ? $query->cursorPaginate($perPage)
            : $query->paginate($perPage);

        return AuditEntryResource::collection($paginator)->response();
    }

    /**
     * Single entry. Ability: `audit:read`.
     */
    public function show(Request $request, string $slug, int $id): JsonResponse
    {
        $this->requireAbility($request, 'audit:read');

        $tenant = $this->currentApiTenant();

        $entry = AuditLog::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($id)
            ->first();

        if ($entry === null) {
            abort(404, "Audit entry [{$id}] not found.");
        }

        return AuditEntryResource::make($entry)->response();
    }
}
