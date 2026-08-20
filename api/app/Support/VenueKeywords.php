<?php

namespace App\Support;

/**
 * Slova, ktore pomenuvaju budovu alebo priestor, nie obec.
 *
 * Jeden zdroj pravdy pre dvoch pouzivatelov, ktori by inak drzali dve kopie
 * toho isteho regexu:
 *   - EventTextLabelExtractor rozhoduje, ktory usek vety je miesto a ktory mesto
 *   - MunicipalityGeocodeResolver nesmie na taky retazec pustit odvodzovanie
 *     obce z kmena, inak "Kaplnka" skonci v obci Kaplna a "Kostole" v Kostolci
 */
class VenueKeywords
{
    public const PATTERN =
        '/\b(?:centr\w+|dom\b|kult\w+\s+dom|kostol\w*|chr[aá]m\w*|katedr\w+|bazilik\w+|kaplnk\w+|synag\w+|'
        . 'farsk\w*|farnos\w*|pastora\w+|divadl\w*|kino\w*|hal[ae]\b|aren[ay]\b|[sš]tadi[oó]n\w*|amfite\w+|'
        . 'm[uú]ze\w+|gal[eé]ri\w+|kni[zž]nic\w+|[sš]kol\w*|gymn[aá]zi\w+|aula\w*|univerz\w+|hotel\w*|penzi[oó]n\w*|'
        . 'reštaur\w*|restaur\w*|klub\w*|s[aá]l[ae]\b|centre\b|amfiteat\w+)/iu';

    public static function matches(string $text): bool
    {
        return preg_match(self::PATTERN, $text) === 1;
    }
}
