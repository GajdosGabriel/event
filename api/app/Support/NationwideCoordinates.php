<?php

namespace App\Support;

/**
 * Stred Slovenska — poloha, ktorú dostane miesto alebo kanál, keď o ňom nič
 * presnejšie nevieme.
 *
 * Mapa a filter „v mojom okolí" potrebujú bod pre každý záznam; záznam bez
 * súradníc z mapy ticho zmizne a nikto nevie prečo. Bod je rovnaký, aký
 * Nominatim vracia pre „Slovensko", a patrí zbernej obci „Celé Slovensko".
 *
 * Pre všetky ostatné obce je to len zástupná hodnota: geokóder ju smie kedykoľvek
 * nahradiť presnejšou (viď needsLookup()).
 */
final class NationwideCoordinates
{
    public const LATITUDE = 48.7411522;

    public const LONGITUDE = 19.4528646;

    /** Zdroj ako pri strede obce — „Celé Slovensko" je v číselníku tiež obec. */
    public const SOURCE = 'municipality';

    public static function isPlaceholder(mixed $latitude, mixed $longitude): bool
    {
        return is_numeric($latitude)
            && is_numeric($longitude)
            && abs((float) $latitude - self::LATITUDE) < 0.000001
            && abs((float) $longitude - self::LONGITUDE) < 0.000001;
    }

    /** Chýbajú súradnice, alebo je tam len zástupný stred Slovenska? */
    public static function needsLookup(mixed $latitude, mixed $longitude): bool
    {
        return $latitude === null
            || $longitude === null
            || self::isPlaceholder($latitude, $longitude);
    }
}
