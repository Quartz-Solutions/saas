<?php

namespace App\Http\Requests\Admin\Themes;

use App\Http\Requests\Admin\AdminFormRequest;

class ThemeStoreRequest extends AdminFormRequest
{
    /**
     * A safe CSS color value: oklch()/rgb()/hsl()/hex/keyword. Deliberately
     * excludes the declaration/rule-breaking chars (`;{}<>@`) the compiler
     * also strips — defence in depth.
     */
    public const COLOR_REGEX = '/^[#a-zA-Z0-9 ().,%\/_-]+$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'mode_hint' => ['nullable', 'in:light,dark,both'],
            'radius' => ['nullable', 'string', 'max:16', 'regex:/^[0-9.]+(px|rem|em)?$/'],
            'font_family' => ['nullable', 'string', 'max:120'],
            'tokens' => ['nullable', 'array'],
            'tokens.light' => ['nullable', 'array'],
            'tokens.dark' => ['nullable', 'array'],
            'tokens.light.*' => ['nullable', 'string', 'max:64', 'regex:'.self::COLOR_REGEX],
            'tokens.dark.*' => ['nullable', 'string', 'max:64', 'regex:'.self::COLOR_REGEX],
        ];
    }
}
