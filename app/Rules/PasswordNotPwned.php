<?php

namespace App\Rules;

use App\Support\Auth\PwnedPasswords;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject any password that appears in the HaveIBeenPwned breach corpus.
 *
 * Use as `new PasswordNotPwned` in any rule set. Bound to
 * `App\Support\Auth\PwnedPasswords`; mock that service to keep tests
 * off the network.
 */
class PasswordNotPwned implements ValidationRule
{
    public function __construct(protected ?PwnedPasswords $pwned = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $service = $this->pwned ?? app(PwnedPasswords::class);

        if ($service->isCompromised($value)) {
            $fail(__('This password has appeared in a data breach. Please choose a different one.'));
        }
    }
}
