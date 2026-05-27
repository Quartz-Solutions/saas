<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\CmsGlobalUpdateRequest;
use App\Support\Cms\GlobalsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class GlobalsController extends Controller
{
    public function __construct(private readonly GlobalsService $globals) {}

    public function index(): Response
    {
        $list = collect($this->globals->keys())
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => (string) (config("cms.globals.{$key}.label") ?? $key),
                'description' => (string) (config("cms.globals.{$key}.description") ?? ''),
            ])
            ->all();

        return Inertia::render('admin/cms/globals/index', [
            'globals' => $list,
        ]);
    }

    public function edit(string $key): Response
    {
        $schema = $this->globals->schema($key);
        if ($schema === null) {
            abort(ResponseAlias::HTTP_NOT_FOUND);
        }

        return Inertia::render('admin/cms/globals/edit', [
            'global' => [
                'key' => $key,
                'label' => $schema['label'] ?? $key,
                'description' => $schema['description'] ?? '',
                'fields' => $schema['fields'] ?? [],
                'payload' => $this->globals->get($key),
            ],
        ]);
    }

    public function update(CmsGlobalUpdateRequest $request, string $key): RedirectResponse
    {
        if ($this->globals->schema($key) === null) {
            abort(ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->globals->save(
            $key,
            (array) $request->input('payload', []),
            $request->user()?->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('admin.cms.globals.edit', ['key' => $key]);
    }
}
