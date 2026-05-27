<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;

class CmsCollectionItemRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->route('type');

        return match ($type) {
            'features' => [
                'title' => ['required', 'string', 'max:240'],
                'description' => ['nullable', 'string', 'max:5000'],
                'icon' => ['nullable', 'string', 'max:64'],
                'slug' => ['nullable', 'string', 'alpha_dash', 'max:120'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            ],
            'testimonials' => [
                'quote' => ['required', 'string', 'max:2000'],
                'author_name' => ['required', 'string', 'max:120'],
                'author_role' => ['nullable', 'string', 'max:120'],
                'company' => ['nullable', 'string', 'max:120'],
                'avatar_url' => ['nullable', 'string', 'max:2048'],
                'logo_url' => ['nullable', 'string', 'max:2048'],
                'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            ],
            'faqs' => [
                'group_slug' => ['required', 'string', 'alpha_dash', 'max:120'],
                'question' => ['required', 'string', 'max:500'],
                'answer_html' => ['nullable', 'string', 'max:20000'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            ],
            'logos' => [
                'group_slug' => ['required', 'string', 'alpha_dash', 'max:120'],
                'name' => ['required', 'string', 'max:120'],
                'image_url' => ['nullable', 'string', 'max:2048'],
                'url' => ['nullable', 'string', 'max:2048'],
                'is_active' => ['boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            ],
            default => abort(404),
        };
    }
}
