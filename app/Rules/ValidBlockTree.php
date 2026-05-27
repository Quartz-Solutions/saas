<?php

namespace App\Rules;

use App\Support\Cms\BlockTypeRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;

/**
 * Validates a body_blocks array by delegating to the BlockTypeRegistry.
 * Catches the registry's internal ValidationException and rewrites it
 * onto this attribute so the admin form can surface a single helpful
 * error string per-block.
 */
class ValidBlockTree implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $fail('The block list must be an array.');

            return;
        }

        try {
            app(BlockTypeRegistry::class)->validateTree($value, $attribute);
        } catch (ValidationException $e) {
            $messages = $e->validator->errors()->all();
            $fail($messages[0] ?? 'Invalid block tree.');
        }
    }
}
