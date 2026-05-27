<?php

namespace App\Http\Requests\Admin\Cms;

use App\Http\Requests\Admin\AdminFormRequest;

class MediaUploadRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB
                'mimes:jpg,jpeg,png,gif,webp,svg,avif,ico',
            ],
        ];
    }
}
