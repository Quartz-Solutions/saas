<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\CmsPage;
use App\Rules\ValidBlockTree;

class CmsPageStoreRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:160', 'unique:cms_pages,slug'],
            'title' => ['required', 'string', 'max:240'],
            'locale' => ['required', 'string', 'max:8'],
            'parent_id' => ['nullable', 'integer', 'exists:cms_pages,id'],
            'route_name' => ['nullable', 'string', 'max:64'],
            'template' => ['required', 'in:'.implode(',', [
                CmsPage::TEMPLATE_DEFAULT,
                CmsPage::TEMPLATE_LANDING,
                CmsPage::TEMPLATE_DOCS,
                CmsPage::TEMPLATE_LEGAL,
            ])],
            'status' => ['required', 'in:'.implode(',', [
                CmsPage::STATUS_DRAFT,
                CmsPage::STATUS_PUBLISHED,
                CmsPage::STATUS_ARCHIVED,
            ])],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'no_index' => ['boolean'],
            'body_blocks' => ['nullable', 'array', new ValidBlockTree],
            'publish_at' => ['nullable', 'date'],
            'unpublish_at' => ['nullable', 'date', 'after:publish_at'],
        ];
    }
}
