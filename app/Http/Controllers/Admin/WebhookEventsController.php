<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplayWebhookEventRequest;
use App\Models\WebhookEvent;
use App\Support\Admin\WebhookReplayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookEventsController extends Controller
{
    private const ALLOWED_SORT = ['id', 'gateway', 'event_type', 'status', 'received_at', 'created_at'];

    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $filters = (array) $request->input('filter', []);
        $sort = in_array($request->input('sort'), self::ALLOWED_SORT, true)
            ? $request->input('sort')
            : 'received_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        $query = WebhookEvent::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('gateway_event_id', 'ilike', "%{$search}%")
                    ->orWhere('event_type', 'ilike', "%{$search}%");
            });
        }

        if (! empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['received_at']) && str_contains((string) $filters['received_at'], '|')) {
            [$from, $to] = explode('|', (string) $filters['received_at'], 2);
            if ($from !== '') {
                $query->whereDate('received_at', '>=', $from);
            }
            if ($to !== '') {
                $query->whereDate('received_at', '<=', $to);
            }
        }

        $paginator = $query->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        $rows = collect($paginator->items())->map(fn (WebhookEvent $e) => [
            'id' => $e->id,
            'gateway' => $e->gateway,
            'gateway_event_id' => $e->gateway_event_id,
            'event_type' => $e->event_type,
            'status' => $e->status,
            'processing_attempts' => (int) $e->processing_attempts,
            'received_at' => $e->received_at?->toIso8601String(),
            'processed_at' => $e->processed_at?->toIso8601String(),
            'tenant_id' => $e->tenant_id,
        ])->all();

        return Inertia::render('admin/webhooks/index', [
            'webhookEvents' => [
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

    public function show(WebhookEvent $webhookEvent): Response
    {
        return Inertia::render('admin/webhooks/show', [
            'webhookEvent' => [
                'id' => $webhookEvent->id,
                'gateway' => $webhookEvent->gateway,
                'gateway_event_id' => $webhookEvent->gateway_event_id,
                'event_type' => $webhookEvent->event_type,
                'status' => $webhookEvent->status,
                'processing_attempts' => (int) $webhookEvent->processing_attempts,
                'error_message' => $webhookEvent->error_message,
                'signature' => $webhookEvent->signature,
                'headers' => $webhookEvent->headers,
                'payload' => $webhookEvent->payload,
                'received_at' => $webhookEvent->received_at?->toIso8601String(),
                'processed_at' => $webhookEvent->processed_at?->toIso8601String(),
                'tenant_id' => $webhookEvent->tenant_id,
            ],
        ]);
    }

    public function replay(ReplayWebhookEventRequest $request, WebhookEvent $webhookEvent): RedirectResponse
    {
        unset($request);

        app(WebhookReplayService::class)->replay($webhookEvent);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Webhook replay queued.'),
        ]);

        return back();
    }
}
