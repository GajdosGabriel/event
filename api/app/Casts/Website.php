<?php

namespace App\Casts;

use App\Support\Url;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Webová adresa sa do databázy zapisuje vždy v jednom tvare (App\Support\Url).
 *
 * Vďaka castu je jedno, či hodnotu zapísal formulár, import alebo konzola —
 * uloží sa rovnako. Bez toho by v tabuľke ležalo `www.divadlo.sk`,
 * `http://Divadlo.sk/` aj `https://divadlo.sk` ako tri rôzne weby a overovanie
 * dostupnosti (App\Services\Attributes) by ich riešilo trikrát.
 *
 * Pozor, cast **cestu zachováva**. Do jesene 2026 orezával adresu na doménu,
 * čo tichšie ničilo odkazy organizátorov bez vlastného webu — z profilu
 * `facebook.com/nase-divadlo` ostalo `facebook.com`, teda odkaz na niečo úplne
 * iné, než čo človek zadal.
 */
class Website implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        return Url::normalize(is_string($value) ? $value : null);
    }
}
