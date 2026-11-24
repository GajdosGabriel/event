<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;

/**
 * Oficiálny názov registrovanej cirkvi namiesto tvaru, ktorý stál v článku.
 *
 * Organizátor sa z textu číta tak, ako je napísaný — teda aj v inom páde:
 * „v kostole Cirkvi bratskej" dal kanál „Cirkvi bratskej" vedľa už
 * existujúcej „Cirkev bratská" (kľúč názvu znesie rozdiel len v jednom
 * slove, tu sa líšia obe). Cirkví registrovaných na Slovensku je pár
 * (register MK SR), takže ich tvary sa dajú vymenovať a zjednotiť na úradný
 * názov — rovnako ako to pre zbory ECAV robí EcavCongregationName.
 *
 * Cirkvi so zbormi (`local`) majú aj miestny tvar „Cirkev bratská Prešov":
 * „zbor Cirkvi bratskej v Prešove", „Cirkev bratská, zbor Prešov" aj
 * „Zbor CB Prešov" sú ten istý zbor. Obec tu ostáva tak, ako bola napísaná —
 * preklad na základný tvar z číselníka robí ImportedCanalNameResolver, tu sa
 * na databázu nesiaha (canalKey beží nad každým kanálom).
 *
 * Katolícke cirkvi a ECAV miestny tvar nemajú: farnosti sa volajú po
 * patrocíniu a zbory ECAV rieši EcavCongregationName. Ich názov sa zjednotí
 * len vtedy, keď je celý reťazec názvom cirkvi — „Rímskokatolícka cirkev,
 * farnosť …" ostáva nedotknutá.
 */
class ChurchName
{
    /**
     * Vzory sú bez diakritiky a malými písmenami — porovnáva sa s ASCII
     * tvarom vstupu, takže prejde aj „Cirkvi bratskej" bez mäkčeňov.
     *
     * @var list<array{name: string, short: string, local: bool, pattern: string, abbr?: string}>
     */
    private const CHURCHES = [
        [
            'name' => 'Cirkev bratská',
            'short' => 'Cirkev bratská',
            'local' => true,
            'pattern' => 'cirk(?:ev|vi|vou)\s+bratsk(?:a|ej|ou|u|e)',
            'abbr' => 'cb',
        ],
        [
            'name' => 'Apoštolská cirkev na Slovensku',
            'short' => 'Apoštolská cirkev',
            'local' => true,
            'pattern' => 'apostolsk(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
            'abbr' => 'ac',
        ],
        [
            'name' => 'Bratská jednota baptistov v Slovenskej republike',
            'short' => 'Bratská jednota baptistov',
            'local' => true,
            'pattern' => 'bratsk(?:a|ej|ou|u)\s+jednot(?:a|y|e|ou|u)\s+baptistov|baptistick(?:y|eho|om)\s+zbor(?:u|e|om)?',
            'abbr' => 'bjb',
        ],
        [
            'name' => 'Cirkev adventistov siedmeho dňa',
            'short' => 'Cirkev adventistov siedmeho dňa',
            'local' => true,
            'pattern' => 'cirk(?:ev|vi|vou)\s+adventistov(?:\s+siedmeho\s+dna)?(?:\s*,\s*slovensk(?:e|eho)\s+zdruzeni(?:e|a))?',
            'abbr' => 'casd',
        ],
        [
            'name' => 'Evanjelická cirkev metodistická',
            'short' => 'Evanjelická cirkev metodistická',
            'local' => true,
            'pattern' => 'evanjelick(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)\s+metodistick(?:a|ej|ou|u)(?:\s*,\s*slovensk(?:a|ej)\s+oblast(?:i)?)?',
            'abbr' => 'ecm',
        ],
        [
            'name' => 'Reformovaná kresťanská cirkev na Slovensku',
            'short' => 'Reformovaná kresťanská cirkev',
            'local' => true,
            'pattern' => 'reformovan(?:a|ej|ou|u)\s+krestansk(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
        ],
        [
            'name' => 'Pravoslávna cirkev na Slovensku',
            'short' => 'Pravoslávna cirkev',
            'local' => true,
            'pattern' => 'pravoslavn(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
        ],
        [
            'name' => 'Československá husitská cirkev',
            'short' => 'Československá husitská cirkev',
            'local' => true,
            'pattern' => 'ceskoslovensk(?:a|ej|ou|u)\s+husitsk(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
        ],
        [
            'name' => 'Starokatolícka cirkev na Slovensku',
            'short' => 'Starokatolícka cirkev',
            'local' => true,
            'pattern' => 'starokatolick(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
        ],
        [
            'name' => 'Novoapoštolská cirkev',
            'short' => 'Novoapoštolská cirkev',
            'local' => true,
            'pattern' => 'novoapostolsk(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
        ],
        // „Kresťanský zbor" býva aj spevácky — miestny tvar sa preto netvorí.
        [
            'name' => 'Kresťanské zbory na Slovensku',
            'short' => 'Kresťanské zbory',
            'local' => false,
            'pattern' => 'krestansk(?:e|ych)\s+zbor(?:y|ov|och|om)',
        ],
        [
            'name' => 'Gréckokatolícka cirkev na Slovensku',
            'short' => 'Gréckokatolícka cirkev',
            'local' => false,
            'pattern' => 'greckokatolick(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
        ],
        [
            'name' => 'Rímskokatolícka cirkev',
            'short' => 'Rímskokatolícka cirkev',
            'local' => false,
            'pattern' => 'rimsko-?\s?katolick(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)',
        ],
        [
            'name' => 'Evanjelická cirkev a. v. na Slovensku',
            'short' => 'Evanjelická cirkev a. v.',
            'local' => false,
            'pattern' => 'evanjelick(?:a|ej|ou|u)\s+cirk(?:ev|vi|vou)\s+(?:a\.\s*v\.?|augsbursk(?:eho)\s+vyznania)',
        ],
        [
            'name' => 'Náboženská spoločnosť Jehovovi svedkovia',
            'short' => 'Jehovovi svedkovia',
            'local' => false,
            'pattern' => '(?:nabozensk(?:a|ej)\s+spolocnost(?:i)?\s+)?jehovov(?:i|ych)\s+svedk(?:ovia|ov|om)',
        ],
        [
            'name' => 'Cirkev Ježiša Krista Svätých neskorších dní',
            'short' => 'Cirkev Ježiša Krista Svätých neskorších dní',
            'local' => false,
            'pattern' => 'cirk(?:ev|vi|vou)\s+jezisa\s+krista\s+svatych\s+neskorsich\s+dni',
        ],
    ];

    /** Dovetok krajiny k úradnému názvu — identitu nenesie. */
    private const COUNTRY = '(?:\s+(?:na\s+slovensku|v\s+slovenskej\s+republike|v\s+sr))?';

    /** „zbor", „cirkevný zbor", „zboru" pred názvom cirkvi alebo za čiarkou. */
    private const CONGREGATION = '(?:cirkevn(?:y|eho)\s+)?zbor(?:u|e)?';

    /**
     * Zjednotený názov: úradný pre celú cirkev, „Cirkev bratská v Prešove"
     * pre zbor. Čo nie je názvom registrovanej cirkvi, vráti nezmenené.
     */
    public static function normalize(string $value): string
    {
        $parsed = self::parse($value);

        if ($parsed === null) {
            return $value;
        }

        return $parsed['rest'] === '' ? $parsed['name'] : $parsed['short'].' '.$parsed['rest'];
    }

    /**
     * Rozbor názvu na cirkev a miestnu časť („v Prešove", „Prešov").
     *
     * @return array{name: string, short: string, local: bool, rest: string}|null
     */
    public static function parse(string $value): ?array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $ascii = mb_strtolower(Str::ascii($value));

        // Zvyšok sa z originálu berie podľa počtu znakov — ASCII tvar musí
        // mať rovnakú dĺžku (slovenské písmená sa prepíšu po jednom).
        if ($value === '' || mb_strlen($ascii) !== mb_strlen($value)) {
            return null;
        }

        foreach (self::CHURCHES as $church) {
            $forms = [$church['pattern']];

            // Skratka len za slovom „zbor" — holé „CB" či „AC" je príliš mnohoznačné.
            if (isset($church['abbr'])) {
                $forms[] = '(?<=zbor |zboru |zbore )'.$church['abbr'];
            }

            $pattern = '/^(?:'.self::CONGREGATION.'\s+)?(?:'.implode('|', $forms).')'.self::COUNTRY.'(?=$|[\s,;:\-–])/u';

            if (! preg_match($pattern, $ascii, $match)) {
                continue;
            }

            $rest = self::localPart(mb_substr($value, mb_strlen($match[0])));

            if ($rest === null || ($rest !== '' && ! $church['local'])) {
                return null;
            }

            return [
                'name' => $church['name'],
                'short' => $church['short'],
                'local' => $church['local'],
                'rest' => $rest,
            ];
        }

        return null;
    }

    /**
     * Cirkev so zbormi, ktorej názov stojí v názve miesta konania —
     * „Modlitebňa Cirkvi bratskej", „Zborový dom Apoštolskej cirkvi".
     *
     * @return array{name: string, short: string, local: bool, rest: string}|null
     */
    public static function inVenueName(string $venueName): ?array
    {
        $ascii = mb_strtolower(Str::ascii(trim($venueName)));

        foreach (self::CHURCHES as $church) {
            if ($church['local'] && preg_match('/(?:^|\s)(?:'.$church['pattern'].')(?=$|[\s,;:\-–])/u', $ascii)) {
                return [
                    'name' => $church['name'],
                    'short' => $church['short'],
                    'local' => true,
                    'rest' => '',
                ];
            }
        }

        return null;
    }

    /**
     * Miestna časť za názvom cirkvi, bez „, zbor" a pomlčiek — alebo null,
     * keď za názvom nasleduje niečo iné než obec či zbor (ďalší subjekt
     * „… a ECAV", zvyšok vety).
     */
    private static function localPart(string $rest): ?string
    {
        $rest = trim($rest, " \t,;:–-");
        $rest = preg_replace('/^(?:cirkevn(?:ý|y|ého|eho)\s+)?zbor(?:u|e)?\b\s*/iu', '', $rest) ?? $rest;
        $rest = trim($rest, " \t,;:–-");

        if ($rest === '') {
            return '';
        }

        // „Cirkev bratská a ECAV" — dva subjekty, nie zbor.
        if (preg_match('/^(?:a|aj|i|&|alebo|spolu|v\s+spolupr)\b/iu', $rest)) {
            return null;
        }

        // Zbor je obec, nanajvýš s mestskou časťou („Bratislava - Palisády",
        // „v Novom Meste nad Váhom") — dlhší chvost je zvyšok vety.
        if (count(preg_split('/\s+/u', $rest) ?: []) > 6) {
            return null;
        }

        return preg_replace('/(?<=\S)\s*[–—]\s*(?=\S)/u', ' - ', $rest) ?? $rest;
    }
}
