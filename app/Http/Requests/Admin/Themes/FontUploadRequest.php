<?php

namespace App\Http\Requests\Admin\Themes;

use App\Http\Requests\Admin\AdminFormRequest;

class FontUploadRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) ceil(((int) config('themes.fonts.max_archive_bytes', 20 * 1024 * 1024)) / 1024);

        return [
            // Deep validation (zip-slip, font whitelist, zip-bomb caps) happens
            // in App\Support\Theme\FontArchiveImporter; here we only gate the
            // upload envelope.
            'archive' => [
                'required',
                'file',
                'mimes:zip',
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
                "max:{$maxKb}",
            ],
        ];
    }
}
