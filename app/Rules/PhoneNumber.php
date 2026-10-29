<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number has to actually be a phone number: at least seven digits and
 * nothing beyond the usual separators ("afjdhgaj" used to pass because the
 * field only checked string/max). The same string is mirrored as the HTML
 * `pattern` attribute on the phone inputs, so the browser complains while the
 * reader types and this rule stays the authority on the server.
 */
class PhoneNumber implements ValidationRule
{
    /**
     * Digit groups separated by at most one space, dash, dot or bracket pair,
     * with an optional international prefix. No delimiters so it can also be
     * dropped straight into an HTML `pattern` attribute.
     */
    public const PATTERN = '\+?[0-9][0-9 .()\-]*[0-9]';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // 'required' / 'nullable' decide that, not this rule.
        }

        $matches = preg_match('/^'.self::PATTERN.'$/', trim($value));
        $digits = preg_match_all('/[0-9]/', $value);

        if ($matches !== 1 || $digits < 7) {
            $fail('The :attribute must be a real phone number with at least 7 digits — letters are not allowed.');
        }
    }
}
