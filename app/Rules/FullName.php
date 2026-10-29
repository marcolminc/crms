<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person's name must contain at least one letter, so accounts cannot be
 * created with junk values that are only digits or punctuation ("5672894",
 * "----"). Unicode-aware: accented and non-Latin scripts still count.
 */
class FullName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[^\pN\pP\pS\s]/u', $value) !== 1) {
            $fail('The :attribute must contain at least one letter.');
        }
    }
}
