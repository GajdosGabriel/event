<?php

namespace App\Services\Imports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Porovnávanie názvov pri importe.
 *
 * AI aj geokodér názov pri normalizácii prepíšu a často k nemu pripíšu
 * upresňujúcu zátvorku — "Evanjelický kostol (Liptovský Mikuláš)",
 * "Spoločenstvo evanjelických žien (SEŽ)". Uložený záznam ju teda má, ale
 * surový názov z článku pri ďalšom behu importu nie. Porovnanie na presný
 * slug preto zlyhá a založí sa duplikát.
 *
 * Riešenie: porovnávame aj "holý" slug — bez koncovej zátvorky a s
 * pomlčkami zjednotenými na ASCII. Zúženie robí SQL (LIKE na prefix),
 * presnú zhodu potvrdí PHP, takže "kostol" nikdy nesadne na
 * "kostol ČM Fatimy".
 */
class ImportedNameMatcher
{
    public static function normalize(string $value): string
    {
        // en/em dash → '-', aby "ACN – Pomoc" a "ACN - Pomoc" dali rovnaký slug
        $normalized = str_replace(["\xE2\x80\x93", "\xE2\x80\x94"], '-', $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Slug názvu bez koncovej upresňujúcej zátvorky a bez koncovky, ktorá
     * pomenúva obec miesta.
     *
     * @param  string|null  $locality  názov obce, v ktorej miesto leží
     */
    public static function baseSlug(string $value, ?string $locality = null): string
    {
        $normalized = self::normalize($value);
        $stripped = trim(preg_replace('/\s*\([^()]*\)\s*$/u', '', $normalized) ?? $normalized);
        $stripped = self::stripTrailingLocality($stripped, $locality);

        return Str::slug($stripped !== '' ? $stripped : $normalized);
    }

    /**
     * Odreže koncovku, ktorá len zopakuje obec miesta.
     *
     * Import raz uloží „Sanktuárium Božieho Milosrdenstva“ a inokedy
     * „Sanktuárium Božieho Milosrdenstva, Ladce“ — v tej istej obci, ako dve
     * rôzne miesta. Zátvorku baseSlug() orezávala, čiarku s obcou nie, takže
     * dvojica sa nikdy nestretla.
     *
     * Orezáva sa výlučne meno obce, do ktorej miesto patrí — nie hocijaký
     * chvost. Holý prefix by bol nebezpečný: „Klokoč“ a „Klokočov“ sú dve
     * rôzne obce a zlúčiť sa nesmú.
     */
    private static function stripTrailingLocality(string $value, ?string $locality): string
    {
        $locality = $locality !== null ? trim($locality) : '';

        if ($locality === '') {
            return $value;
        }

        $stripped = preg_replace(
            '/\s*[,\-–—]?\s*'.preg_quote($locality, '/').'\s*$/iu',
            '',
            $value,
        );

        if (! is_string($stripped)) {
            return $value;
        }

        $stripped = trim($stripped, " \t,-–—");

        // Keď po oreze nezostane nič, koncovka nebola prívlastok, ale samotný
        // názov miesta — „Klokočov“ v obci Klokočov.
        return $stripped !== '' ? $stripped : $value;
    }

    /**
     * Kľúč názvu organizácie — to, čo zostane po odstránení zápisu, ktorý
     * identitu nenesie.
     *
     * AI pomenuje toho istého organizátora v každom článku trochu inak a import
     * pre každý variant založil nový kanál (pri kontrole 23. 10. 2026 ich bolo
     * 269): právna forma („Musica aeterna" / „Musica aeterna o.z", „OZ KREDIV"
     * / „Občianske združenie KREDIV"), skratka v zátvorke („Ekumenická rada
     * cirkví (ERC) v SR"), „sv." / „svätého", dovetok „na Slovensku" / „v SR",
     * úvodné „Rímskokatolícka" či spojky. Kľúč tieto rozdiely zotrie, ale
     * nechá všetko, čo jednu organizáciu od druhej odlišuje (obec, diecéza,
     * skratka mimo zátvorky, čísla).
     */
    public static function canalKey(string $value): string
    {
        $value = str_replace(["\u{2013}", "\u{2014}", "\u{2018}", "\u{2019}"], ['-', '-', "'", "'"], $value);
        $value = preg_replace('/\([^()]*\)/u', ' ', $value) ?? $value;
        $key = ' '.str_replace('-', ' ', Str::slug($value)).' ';

        // Dvakrát: prekrývajúce sa zhody („o z a s") regex v jednom prechode preskočí.
        for ($i = 0; $i < 2; $i++) {
            $key = preg_replace('/ (obcianske zdruzenie|obcianske zduzenie|neziskova organizacia|spol s r o|s r o|sro|o z|oz|n o|no|a s|as|spol|o p s) /', ' ', $key) ?? $key;
            $key = preg_replace('/ (svateho|svatej|svaty|svata|svate|svatych|sv) /', ' sv ', $key) ?? $key;
        }

        $key = preg_replace('/^ (rimsko katolicka|rimskokatolicka|rimskokatolicky|rimskokatolicke|katolicka|katolicke|katolicky|zdruzenie|spolok) /', ' ', $key) ?? $key;
        $key = preg_replace('/ (na slovensku|v slovenskej republike|v sr|sr|slovensko|slovakia) $/', ' ', $key) ?? $key;

        $words = array_filter(
            explode(' ', $key),
            static fn (string $word): bool => $word !== '' && ! in_array($word, self::FILLER_WORDS, true),
        );

        return implode(' ', $words);
    }

    /**
     * Ten istý organizátor podľa kľúča názvu: rovnaký kľúč, alebo jediné slovo
     * s preklepom či iným pádom („Biskupstvo v Nitre" / „Biskupstvo Nitra",
     * „Klub kresťanských lekárov … Bratislava" / „… Bratislave").
     *
     * Krátke slová a čísla sa nesmú líšiť ani o písmeno — „Teologická fakulta
     * KU" a „TU" sú dve fakulty, „SDM 2023" a „SDM 2027" dve akcie.
     */
    public static function canalKeysMatch(string $a, string $b): bool
    {
        if (! self::isDistinctiveCanalKey($a) || ! self::isDistinctiveCanalKey($b)) {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        $wordsA = explode(' ', $a);
        $wordsB = explode(' ', $b);

        if (count($wordsA) !== count($wordsB) || min(strlen($a), strlen($b)) < 10) {
            return false;
        }

        $different = array_keys(array_filter(array_map(null, $wordsA, $wordsB), fn (array $pair) => $pair[0] !== $pair[1]));

        if (count($different) !== 1) {
            return false;
        }

        [$x, $y] = [$wordsA[$different[0]], $wordsB[$different[0]]];
        $shorter = min(strlen($x), strlen($y));

        if ($shorter < 4 || preg_match('/\d/', $x.$y)) {
            return false;
        }

        $distance = levenshtein($x, $y);

        return $distance === 1 || ($distance === 2 && $shorter >= 7 && min(strlen($a), strlen($b)) >= 20);
    }

    /**
     * Kľúč, podľa ktorého sa smie zlúčiť bez človeka. Nie je ním:
     *  - samé všeobecné slová („Katolícka cirkev" → „cirkev"),
     *  - farnosť či kostol pomenovaný po patrocíniu („Farnosť sv. Alžbety",
     *    „Farnosť Sedembolestnej Panny Márie") — taký je v mnohých obciach
     *    a obec kanála import v tej chvíli ešte nepozná.
     */
    private static function isDistinctiveCanalKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $words = explode(' ', $key);

        if (array_diff($words, self::GENERIC_WORDS) === []) {
            return false;
        }

        return array_intersect($words, self::CHURCH_WORDS) === [] || array_intersect($words, self::DEDICATION_WORDS) === [];
    }

    private const FILLER_WORDS = ['a', 'v', 'vo', 'na', 'pri', 'z', 'zo', 's', 'so', 'do', 'the', 'of', 'and'];

    private const GENERIC_WORDS = [
        'cirkev', 'farnost', 'spolocenstvo', 'komunita', 'centrum', 'zbor', 'rada', 'skola', 'hnutie', 'nadacia',
        'rehola', 'mesto', 'obec', 'kostol', 'fara', 'farsky', 'urad', 'katolicka', 'cirkevny', 'zdruzenie', 'klub',
        'dom', 'kultury',
    ];

    private const CHURCH_WORDS = ['farnost', 'farnosti', 'farsky', 'fara', 'kostol', 'chram', 'bazilika', 'katedrala'];

    private const DEDICATION_WORDS = [
        'sv', 'panny', 'marie', 'krista', 'krala', 'kralovnej', 'kralovej', 'bozieho', 'bozskeho', 'srdca', 'kriza',
        'trojice', 'ducha', 'rodiny', 'nanebovzatia', 'narodenia', 'navstevy', 'sedembolestnej', 'povysenia',
        'milosrdenstva', 'pastiera', 'archanjela', 'apostolov', 'jezisa', 'jezisovho', 'najsvatejsej',
        'najsvatejsieho', 'neposkvrnenej', 'neposkvrneneho', 'ruzencovej', 'fatimskej', 'lurdskej', 'premenenia',
        'zoslania', 'mucenikov',
    ];

    /**
     * Najstarší záznam, ktorého názov má rovnaký holý slug ako $name.
     *
     * Najstarší zámerne: import tak vždy skonverguje na prvý založený
     * záznam namiesto toho, aby preskakoval medzi kópiami.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  string|null  $locality  názov obce, v ktorej miesto leží
     * @return TModel|null
     */
    public static function firstByBaseName(Builder $query, string $name, ?string $locality = null): ?Model
    {
        $base = self::baseSlug($name, $locality);

        if ($base === '') {
            return null;
        }

        return $query
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->orderBy('id')
            ->get()
            ->first(fn (Model $model) => self::baseSlug((string) $model->getAttribute('name'), $locality) === $base);
    }
}
