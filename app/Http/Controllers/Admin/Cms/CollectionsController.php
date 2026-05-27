<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\CmsCollectionItemRequest;
use App\Models\CmsFaq;
use App\Models\CmsFeature;
use App\Models\CmsLogo;
use App\Models\CmsTestimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * Generic admin CRUD for the four CMS collections. The `type` route
 * param picks which underlying table to operate on. Field-level
 * validation rules are declared by the FormRequest's `rulesFor()`.
 */
class CollectionsController extends Controller
{
    private const MODELS = [
        'features' => CmsFeature::class,
        'testimonials' => CmsTestimonial::class,
        'faqs' => CmsFaq::class,
        'logos' => CmsLogo::class,
    ];

    private const LABELS = [
        'features' => 'Features',
        'testimonials' => 'Testimonials',
        'faqs' => 'FAQs',
        'logos' => 'Logos',
    ];

    private const DESCRIPTIONS = [
        'features' => 'Marketing feature bullets used by feature_grid blocks.',
        'testimonials' => 'Customer quotes used by testimonials blocks.',
        'faqs' => 'FAQ entries grouped by slug. Used by faq blocks.',
        'logos' => 'Customer / partner logos grouped by slug. Used by logo_cloud blocks.',
    ];

    public function index(Request $request, string $type): Response
    {
        $modelClass = $this->resolveModel($type);

        $items = $modelClass::query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->all();

        return Inertia::render('admin/cms/collections/index', [
            'type' => $type,
            'label' => self::LABELS[$type],
            'description' => self::DESCRIPTIONS[$type],
            'items' => $items,
            'fields' => $this->fieldsFor($type),
        ]);
    }

    public function store(CmsCollectionItemRequest $request, string $type): RedirectResponse
    {
        $modelClass = $this->resolveModel($type);
        $modelClass::query()->create($this->payloadFor($type, $request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Created.')]);

        return to_route('admin.cms.collections.index', ['type' => $type]);
    }

    public function update(CmsCollectionItemRequest $request, string $type, int $id): RedirectResponse
    {
        $modelClass = $this->resolveModel($type);
        $row = $modelClass::query()->findOrFail($id);
        $row->fill($this->payloadFor($type, $request));
        $row->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return to_route('admin.cms.collections.index', ['type' => $type]);
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $modelClass = $this->resolveModel($type);
        $row = $modelClass::query()->findOrFail($id);
        $row->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return to_route('admin.cms.collections.index', ['type' => $type]);
    }

    /**
     * @return class-string<Model>
     */
    private function resolveModel(string $type): string
    {
        if (! array_key_exists($type, self::MODELS)) {
            abort(ResponseAlias::HTTP_NOT_FOUND);
        }

        return self::MODELS[$type];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool, options?: array<int, string>}>
     */
    private function fieldsFor(string $type): array
    {
        return match ($type) {
            'features' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'icon', 'label' => 'Lucide icon (e.g. shield, code)', 'type' => 'text'],
                ['key' => 'slug', 'label' => 'Slug (optional)', 'type' => 'text'],
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
                ['key' => 'sort_order', 'label' => 'Sort order', 'type' => 'number'],
            ],
            'testimonials' => [
                ['key' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'required' => true],
                ['key' => 'author_name', 'label' => 'Author name', 'type' => 'text', 'required' => true],
                ['key' => 'author_role', 'label' => 'Author role', 'type' => 'text'],
                ['key' => 'company', 'label' => 'Company', 'type' => 'text'],
                ['key' => 'avatar_url', 'label' => 'Avatar URL', 'type' => 'url'],
                ['key' => 'logo_url', 'label' => 'Logo URL', 'type' => 'url'],
                ['key' => 'rating', 'label' => 'Rating (1–5)', 'type' => 'number'],
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
                ['key' => 'sort_order', 'label' => 'Sort order', 'type' => 'number'],
            ],
            'faqs' => [
                ['key' => 'group_slug', 'label' => 'Group slug', 'type' => 'text', 'required' => true],
                ['key' => 'question', 'label' => 'Question', 'type' => 'text', 'required' => true],
                ['key' => 'answer_html', 'label' => 'Answer (HTML allowed)', 'type' => 'textarea'],
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
                ['key' => 'sort_order', 'label' => 'Sort order', 'type' => 'number'],
            ],
            'logos' => [
                ['key' => 'group_slug', 'label' => 'Group slug', 'type' => 'text', 'required' => true],
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['key' => 'image_url', 'label' => 'Image URL', 'type' => 'url'],
                ['key' => 'url', 'label' => 'Link URL', 'type' => 'url'],
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
                ['key' => 'sort_order', 'label' => 'Sort order', 'type' => 'number'],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $type, Request $request): array
    {
        $defaults = ['is_active' => true, 'sort_order' => 0];
        $payload = array_merge($defaults, $request->validated());

        if ($type === 'faqs' || $type === 'logos') {
            $payload['group_slug'] = $payload['group_slug'] ?? 'default';
        }

        return $payload;
    }
}
