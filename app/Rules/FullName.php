<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person's name is a name, not a username. It must be built only from
 * letters (any script) and the separators names genuinely use — spaces,
 * apostrophes, hyphens and periods — and must carry at least two letters.
 * This refuses values that hide digits or symbols ("Ali Halisi775658",
 * "5672894", "----", "bob_the_builder") while still accepting accented and
 * non-Latin scripts. Unicode-aware on the server; the PATTERN constant is
 * the latin subset reused verbatim as the HTML5 pattern so the field flags
 * as the reader types.
 */
class FullName implements ValidationRule
{
    /**
     * HTML-safe client pattern (no delimiters, implicitly anchored by HTML5):
     * a leading letter then letters, spaces, apostrophes, periods or hyphens.
     */
    public const PATTERN = '[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\'. -]*';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // 'required' / 'nullable' decide that, not this rule.
        }

        $value = trim($value);

        if (preg_match('/^[\p{L}\p{M} \'’.-]+$/u', $value) !== 1) {
            $fail('The :attribute must be a name made of letters only, without numbers or symbols.');

            return;
        }

        if (preg_match('/[\p{L}]{2}/u', $value) !== 1) {
            $fail('The :attribute must contain at least two letters.');
        }
    }
}
