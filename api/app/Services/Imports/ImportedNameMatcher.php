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
            '/\s*[,\-–—]?\s*' . preg_quote($locality, '/') . '\s*$/iu',
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
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', $base . '-%'))
            ->orderBy('id')
            ->get()
            ->first(fn (Model $model) => self::baseSlug((string) $model->getAttribute('name'), $locality) === $base);
    }
}
