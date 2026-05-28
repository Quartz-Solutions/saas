<?php

namespace App\Http\Requests\Admin\Themes;

use App\Http\Requests\Admin\AdminFormRequest;

class CssUpdateRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxBytes = (int) config('themes.custom_css.max_bytes', 262144);
        $maxKb = (int) ceil($maxBytes / 1024);

        return [
            // Inline editor posts raw CSS in `css`; file upload posts `file`.
            // Either may be present; both are optional (empty clears the CSS).
            'css' => ['nullable', 'string', "max:{$maxBytes}"],
            'file' => ['nullable', 'file', 'mimetypes:text/plain,text/css', "max:{$maxKb}"],
        ];
    }
}
