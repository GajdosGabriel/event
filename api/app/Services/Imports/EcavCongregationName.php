<?php

namespace App\Services\Imports;

/**
 * Jednotný názov cirkevného zboru ECAV: „CZ ECAV Poltár".
 *
 * Ten istý zbor prichádza z ecav.sk aj z textu pozvánok v mnohých zápisoch —
 * „CZ ECAV Poltár", „Cirkevný zbor ECAV na Slovensku Modra", „Evanjelický
 * a. v. cirkevný zbor v Modre", „ECAV na Slovensku, Cirkevný zbor Betliar".
 * Import pre každý zápis zakladal nový kanál. Tu sa všetky zjednotia na jeden
 * tvar — krátky, ako zbory píše samotný ecav.sk, aby sa zmestil na kartu
 * podujatia; obec a „ECAV" v ňom ostávajú, takže sa podľa nich dá hľadať.
 * Obec ostáva tak, ako
 * bola napísaná (aj „v Sobotišti") — rozdiel v páde podchytí
 * ImportedNameMatcher::canalKeysMatch().
 *
 * Mení sa len zápis, ktorý jednoznačne hovorí o zbore ECAV. „Cirkevný zbor
 * v Ružomberku" bez ECAV môže byť zbor inej cirkvi, „Cirkevné zbory …" je
 * viac zborov a holé „ECAV Nitra" ostáva tiež — tak sa volá aj seniorát či
 * dištrikt.
 */
class EcavCongregationName
{
    public const PREFIX = 'CZ ECAV';

    private const AV = 'a\.\s*v\.?';

    private const ECAV = '(?:ECAV|Evanjelick(?:á|a|ej)\s+cirk(?:ev|vi)\s+(?:a\.\s*v\.?|augsbursk(?:é|e)ho\s+vyznania))(?:\s+na\s+Slovensku)?';

    public static function normalize(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        foreach (self::patterns() as $pattern) {
            if (! preg_match($pattern, $value, $match)) {
                continue;
            }

            $rest = trim($match['rest'], " \t,;:–-");
            // „Košice- Terasa" / „Košice -Terasa" → „Košice-Terasa"; súmerné
            // „Svätý Kríž - Lazisko" ostáva.
            $rest = preg_replace('/(?<=\S)(?:\s+-(?=\S)|-\s+)/u', '-', $rest) ?? $rest;

            if ($rest === '' || preg_match('/^(?:ECAV|na Slovensku)$/iu', $rest)) {
                return $value;
            }

            return self::PREFIX.' '.$rest;
        }

        return $value;
    }

    /** @return list<string> */
    private static function patterns(): array
    {
        $av = self::AV;
        $ecav = self::ECAV;
        $zbor = 'cirkevn(?:ý|y)\s+zbor';
        $rest = '(?:\s+na\s+Slovensku)?\s*[,–-]?\s+(?<rest>.+)$';

        return [
            // „CZ ECAV Poltár", „Cirkevný zbor ECAV na Slovensku Modra",
            // „Cirkevný zbor Evanjelickej cirkvi a. v. Betliar"
            '/^(?:CZ\.?|'.$zbor.')\s+'.$ecav.$rest.'/iu',
            // „Evanjelický a. v. cirkevný zbor v Modre", „Evanj. a. v. cirkevný
            // zbor …", „Evanjelický zbor Kobeliarovo"
            '/^Evanj(?:elick(?:ý|y)|\.)\s+(?:'.$av.'\s+)?(?:cirkevn(?:ý|y)\s+)?zbor(?:\s+'.$av.')?(?:\s+ECAV)?'.$rest.'/iu',
            // Úradný tvar „ECAV na Slovensku, Cirkevný zbor Betliar"
            '/^'.$ecav.'\s*,?\s+(?:CZ\.?|'.$zbor.')'.$rest.'/iu',
        ];
    }
}
