<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A document/registration reference (national ID, passport number, driving
 * licence number) is a code, not free text. It must carry at least one letter
 * or digit and may only be built from the characters such codes actually use —
 * alphanumerics with spaces, slashes and hyphens. This blocks obviously junky
 * values ("----", "<x>", stray punctuation) while staying lenient enough for
 * the many real-world shapes these identifiers take. Mirrored on the client by
 * the IDENTIFIER pattern so the field flags as the reader types.
 */
class Identifier implements ValidationRule
{
    /**
     * HTML-safe pattern (no delimiters): letters/digits, optionally separated
     * by single spaces, slashes or hyphens. Anchored implicitly by HTML5.
     */
    public const PATTERN = '[A-Za-z0-9]+([ \/-][A-Za-z0-9]+)*';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return; // 'required' / 'nullable' decide that, not this rule.
        }

        if (preg_match('/^'.self::PATTERN.'$/', $value) !== 1) {
            $fail('The :attribute must be a reference code — letters and numbers, optionally with spaces, / or -.');
        }
    }
}
