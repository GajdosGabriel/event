<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class ValidUrlRule implements ValidationRule
{

    public function __construct(bool $requireHttps = false, bool $checkReachability = false)
    {
        $this->requireHttps = $requireHttps;
        $this->checkReachability = $checkReachability;
    }
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Povoliť prázdne hodnoty
        if (empty($value)) {
            return;
        }

        // Základná validácia formátu URL
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid URL.');
            return;
        }

        // Kontrola HTTPS (iba ak je požadované)
        if ($this->requireHttps && !str_starts_with(strtolower($value), 'https://')) {
            $fail('The :attribute must start with https://');
            return;
        }

        // Voliteľná kontrola dostupnosti
        if ($this->checkReachability) {
            try {
                $response = Http::timeout(3)->head($value);
                if (!$response->successful()) {
                    $fail('The :attribute is not reachable.');
                }
            } catch (\Exception $e) {
                $fail('The :attribute could not be verified.');
            }
        }
    }
}
