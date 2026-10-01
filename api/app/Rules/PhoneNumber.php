<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Jednotné pravidlo pre telefón vo všetkých formulároch: číslice, `+`, medzery,
 * pomlčky a zátvorky, 6–20 znakov. Rovnaký tvar kontroluje klient
 * (`ui/src/utils/phone.ts`) — pri zmene upravte oba.
 */
class PhoneNumber implements ValidationRule
{
    public const PATTERN = '/^[0-9+\s()\-]{6,20}$/u';

    public static function passes(mixed $value): bool
    {
        return is_string($value)
            && preg_match(self::PATTERN, $value) === 1
            && preg_match_all('/\d/', $value) >= 6;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (! self::passes($value)) {
            $fail('validation.phone')->translate();
        }
    }
}
