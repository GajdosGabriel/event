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
     * Slug názvu bez koncovej upresňujúcej zátvorky.
     */
    public static function baseSlug(string $value): string
    {
        $normalized = self::normalize($value);
        $stripped = trim(preg_replace('/\s*\([^()]*\)\s*$/u', '', $normalized) ?? $normalized);

        return Str::slug($stripped !== '' ? $stripped : $normalized);
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
     * @return TModel|null
     */
    public static function firstByBaseName(Builder $query, string $name): ?Model
    {
        $base = self::baseSlug($name);

        if ($base === '') {
            return null;
        }

        return $query
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', $base . '-%'))
            ->orderBy('id')
            ->get()
            ->first(fn (Model $model) => self::baseSlug((string) $model->getAttribute('name')) === $base);
    }
}
