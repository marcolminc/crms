<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number has to actually be a phone number: ten digits beginning with
 * 0 ("afjdhgaj" and "0712345" both used to pass because the field only checked
 * string/max). Formatting is tolerated on input — spaces, dashes, brackets and
 * a +255 country code are normal in the wild — but the digits underneath must
 * form a local ten-digit number. The same intent is mirrored by the HTML
 * `pattern` attribute on the phone inputs so the browser complains while the
 * reader types; this rule stays the authority on the server.
 */
class PhoneNumber implements ValidationRule
{
    /**
     * Client-side shape: ten digits, leading zero, no separators. Kept strict
     * so a half-typed number is flagged immediately; this rule is more lenient
     * about formatting because paste and redial habits vary.
     */
    public const PATTERN = '0[0-9]{9}';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // 'required' / 'nullable' decide that, not this rule.
        }

        // Letters or any character that is not a digit or a plain separator is
        // junk, whatever the digit count looks like.
        if (preg_match('/[^0-9 .()+\-]/', $value) === 1) {
            $fail('The :attribute must be ten digits beginning with 0, like 0123456789.');

            return;
        }

        $digits = preg_replace('/\D/', '', $value);

        // Accept the +255 country code (255 + nine subscriber digits = 12),
        // then insist on a ten-digit local number.
        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        }

        if (preg_match('/^0[0-9]{9}$/', $digits) !== 1) {
            $fail('The :attribute must be ten digits beginning with 0, like 0123456789.');
        }
    }
}
