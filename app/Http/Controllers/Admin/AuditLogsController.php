<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogsController extends Controller
{
    private const ALLOWED_SORT = ['id', 'action', 'created_at'];

    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $query = AuditLog::query()
            ->with(['user:id,name,email', 'tenant:id,slug,name']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                    ->orWhere('auditable_type', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (! empty($filters['created_at']) && str_contains((string) $filters['created_at'], '|')) {
            [$from, $to] = explode('|', (string) $filters['created_at'], 2);
            if ($from !== '') {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to !== '') {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        $rows = collect($paginator->items())->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'ip' => $log->ip,
            'created_at' => $log->created_at?->toIso8601String(),
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
            'tenant' => $log->tenant ? [
                'id' => $log->tenant->id,
                'slug' => $log->tenant->slug,
                'name' => $log->tenant->name,
            ] : null,
        ])->all();

        return Inertia::render('admin/audit/index', [
            'auditLogs' => [
                'data' => $rows,
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
}
