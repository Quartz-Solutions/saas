<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\CmsFormRequest;
use App\Models\CmsForm;
use App\Models\CmsFormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;

class FormsController extends Controller
{
    public function index(): \Inertia\Response
    {
        $forms = CmsForm::query()
            ->withCount('submissions')
            ->orderBy('name')
            ->get()
            ->map(fn (CmsForm $f) => [
                'id' => $f->id,
                'slug' => $f->slug,
                'name' => $f->name,
                'is_active' => $f->is_active,
                'submissions_count' => $f->submissions_count,
            ])
            ->all();

        return Inertia::render('admin/cms/forms/index', ['forms' => $forms]);
    }

    public function create(): \Inertia\Response
    {
        return Inertia::render('admin/cms/forms/edit', ['form' => null]);
    }

    public function store(CmsFormRequest $request): RedirectResponse
    {
        $form = CmsForm::query()->create($request->validated() + ['is_active' => true, 'store_submissions' => true]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form created.')]);

        return to_route('admin.cms.forms.edit', ['form' => $form->id]);
    }

    public function edit(CmsForm $form): \Inertia\Response
    {
        return Inertia::render('admin/cms/forms/edit', [
            'form' => [
                'id' => $form->id,
                'slug' => $form->slug,
                'name' => $form->name,
                'fields' => $form->fields ?? [],
                'success_message' => $form->success_message,
                'notify_email' => $form->notify_email,
                'webhook_url' => $form->webhook_url,
                'store_submissions' => $form->store_submissions,
                'is_active' => $form->is_active,
            ],
        ]);
    }

    public function update(CmsFormRequest $request, CmsForm $form): RedirectResponse
    {
        $form->fill($request->validated())->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form saved.')]);

        return to_route('admin.cms.forms.edit', ['form' => $form->id]);
    }

    public function destroy(CmsForm $form): RedirectResponse
    {
        $form->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Form deleted.')]);

        return to_route('admin.cms.forms.index');
    }

    public function submissions(CmsForm $form): \Inertia\Response
    {
        $submissions = CmsFormSubmission::query()
            ->where('cms_form_id', $form->id)
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/cms/forms/submissions', [
            'form' => ['id' => $form->id, 'slug' => $form->slug, 'name' => $form->name, 'fields' => $form->fields ?? []],
            'submissions' => [
                'data' => $submissions->getCollection()->map(fn (CmsFormSubmission $s) => [
                    'id' => $s->id,
                    'payload' => $s->payload,
                    'ip' => $s->ip,
                    'created_at' => optional($s->created_at)->toIso8601String(),
                ])->all(),
                'meta' => [
                    'current_page' => $submissions->currentPage(),
                    'last_page' => $submissions->lastPage(),
                    'total' => $submissions->total(),
                ],
            ],
        ]);
    }

    public function submissionsCsv(CmsForm $form): Response
    {
        $fields = collect($form->fields ?? [])->pluck('key')->all();
        $rows = $form->submissions()->orderBy('id')->get();

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            abort(500, 'CSV stream open failed.');
        }
        fputcsv($handle, array_merge(['id', 'submitted_at', 'ip'], $fields));
        foreach ($rows as $row) {
            $line = [$row->id, optional($row->created_at)->toIso8601String(), $row->ip];
            foreach ($fields as $key) {
                $line[] = $row->payload[$key] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$form->slug.'-submissions.csv"',
        ]);
    }
}
