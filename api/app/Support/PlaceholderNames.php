<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Zástupné hodnoty, ktoré AI vracia namiesto vynechaného údaja.
 *
 * JSON `null` zachytí už kontrola `is_string()`, ale model občas pošle reťazec
 * "null" — a ten prejde ako plnohodnotná hodnota. V produkcii takto vznikol
 * kanál s menom aj slugom „null“ a popisom „null — organizátor podujatí“.
 *
 * Zoznam je spoločný pre názvy aj obce: obec „neuvedené“ by sa inak poslala
 * geokóderu, ktorý na ňu minie dotaz do siete a vráti hocičo.
 */
class PlaceholderNames
{
    private const VALUES = [
        'null', 'nil', 'none', 'n/a', 'na', 'undefined', 'unknown', 'false',
        'neznamy', 'neznama', 'nezname', 'neuvedene', 'neuvedeny', 'bez organizatora',
    ];

    /**
     * Porovnáva sa bezdiakritická, malými písmenami písaná podoba bez
     * interpunkcie, takže sadne aj „N/A“, „neznámy“ či „NULL.“.
     */
    public static function matches(string $value): bool
    {
        $ascii = Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9\/]+/', ' ')->trim()->value();

        return $ascii === '' || in_array($ascii, self::VALUES, true);
    }
}
