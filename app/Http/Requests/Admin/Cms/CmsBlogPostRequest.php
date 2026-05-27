<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\CmsBlogPost;
use App\Rules\ValidBlockTree;
use Illuminate\Validation\Rule;

class CmsBlogPostRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('post');

        return [
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:160', Rule::unique('cms_blog_posts', 'slug')->ignore($id)],
            'title' => ['required', 'string', 'max:240'],
            'locale' => ['nullable', 'string', 'max:8'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'cover_image_url' => ['nullable', 'string', 'max:2048'],
            'body_blocks' => ['nullable', 'array', new ValidBlockTree],
            'status' => ['required', 'in:'.implode(',', [
                CmsBlogPost::STATUS_DRAFT,
                CmsBlogPost::STATUS_PUBLISHED,
                CmsBlogPost::STATUS_ARCHIVED,
            ])],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'no_index' => ['boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:cms_blog_categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:cms_blog_tags,id'],
        ];
    }
}
