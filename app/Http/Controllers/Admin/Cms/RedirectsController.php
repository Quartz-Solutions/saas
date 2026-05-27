<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\RedirectStoreRequest;
use App\Http\Requests\Admin\Cms\RedirectUpdateRequest;
use App\Models\NotFoundLog;
use App\Models\Redirect as RedirectRow;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RedirectsController extends Controller
{
    public function index(): Response
    {
        $redirects = RedirectRow::query()
            ->orderBy('from_path')
            ->get()
            ->map(fn (RedirectRow $r) => [
                'id' => $r->id,
                'from_path' => $r->from_path,
                'to_path' => $r->to_path,
                'status_code' => $r->status_code,
                'is_active' => $r->is_active,
                'hits' => $r->hits,
                'last_hit_at' => optional($r->last_hit_at)->toIso8601String(),
                'created_at' => optional($r->created_at)->toIso8601String(),
            ])
            ->all();

        $notFound = NotFoundLog::query()
            ->orderByDesc('last_hit_at')
            ->limit(50)
            ->get()
            ->map(fn (NotFoundLog $l) => [
                'id' => $l->id,
                'path' => $l->path,
                'hits' => $l->hits,
                'referer' => $l->referer,
                'last_hit_at' => optional($l->last_hit_at)->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/cms/redirects/index', [
            'redirects' => $redirects,
            'notFoundLog' => $notFound,
        ]);
    }

    public function store(RedirectStoreRequest $request): RedirectResponse
    {
        RedirectRow::query()->create($request->validated() + ['is_active' => true, 'hits' => 0]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect created.')]);

        return to_route('admin.cms.redirects.index');
    }

    public function update(RedirectUpdateRequest $request, RedirectRow $redirect): RedirectResponse
    {
        $redirect->fill($request->validated())->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect saved.')]);

        return to_route('admin.cms.redirects.index');
    }

    public function destroy(RedirectRow $redirect): RedirectResponse
    {
        $redirect->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect deleted.')]);

        return to_route('admin.cms.redirects.index');
    }

    /**
     * Convert a 404 log entry into a redirect (the inverse of "where did
     * this URL go?"). The log row is left in place so admins can see the
     * conversion timestamp.
     */
    public function convertNotFound(int $id, RedirectStoreRequest $request): RedirectResponse
    {
        $log = NotFoundLog::query()->findOrFail($id);

        RedirectRow::query()->updateOrCreate(
            ['from_path' => $log->path],
            [
                'to_path' => $request->input('to_path'),
                'status_code' => (int) $request->input('status_code', 301),
                'is_active' => true,
                'hits' => 0,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Converted to redirect.')]);

        return to_route('admin.cms.redirects.index');
    }
}
