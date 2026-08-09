<?php

namespace App\Rules;

use App\Support\Url;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Jednotné pravidlo pre pole „Web" vo všetkých formulároch.
 *
 * Kontroluje **len tvar** adresy, nikdy nie jej dostupnosť. Je to zámer, nie
 * zjednodušenie: cudzí server môže byť v momente ukladania na minútu vypnutý,
 * za firewallom alebo len pomalý — a odmietnuť kvôli tomu celý formulár by
 * bolo nepochopiteľné. Či adresa naozaj odpovedá, sa zisťuje potom, na pozadí
 * (App\Services\Attributes\AttributeCheckService), a rieši sa e-mailom
 * majiteľovi, nie červeným poľom pod prstami.
 *
 * Zhovievavosť voči tvaru je druhá strana tej istej mince: `divadlo.sk` je pre
 * človeka platná adresa, tak ju prijmeme a schému doplníme sami (rovnako ako
 * cast pri ukladaní — obe cesty vedú cez App\Support\Url).
 *
 * Laravelovské `url` pravidlo sa nepoužíva práve preto, že `divadlo.sk` bez
 * schémy odmieta, a `active_url` zase robí DNS dotaz priamo v requeste.
 */
class WebsiteUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (! is_string($value) || Url::normalize($value) === null) {
            $fail('validation.website_url')->translate();
        }
    }
}
