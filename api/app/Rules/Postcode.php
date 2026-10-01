<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * PSČ pre Slovensko a Česko: 5 číslic, voliteľne s medzerou za treťou
 * („811 01"). Pre inú krajinu (pole `country` je vyplnené niečím iným ako
 * SK/CZ) kontrolujeme len rozumný tvar, nie slovenský formát.
 */
class Postcode implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $countryField = 'country') {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $value = is_string($value) ? trim($value) : null;

        $valid = $value !== null && ($this->isSlovakOrCzech()
            ? preg_match('/^\d{3}\s?\d{2}$/', $value) === 1
            : preg_match('/^[A-Za-z0-9][A-Za-z0-9\s\-]{1,12}$/', $value) === 1);

        if (! $valid) {
            $fail('validation.postcode')->translate();
        }
    }

    private function isSlovakOrCzech(): bool
    {
        $country = $this->data[$this->countryField] ?? null;

        if (! is_string($country) || trim($country) === '') {
            return true;
        }

        $normalized = preg_replace('/[^a-z]+/', '', strtolower(\Illuminate\Support\Str::ascii($country))) ?? '';

        return in_array($normalized, ['sk', 'svk', 'slovensko', 'slovakia', 'slowakei', 'cz', 'cze', 'cesko', 'czechia', 'czechrepublic', 'ceskarepublika', 'tschechien', 'slovenskarepublika'], true);
    }
}
