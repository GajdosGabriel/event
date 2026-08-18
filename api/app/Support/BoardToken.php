<?php

namespace App\Support;

/**
 * Token nástenky otázok — zároveň neuhádnuteľná autorizácia adresy
 * `/q/{token}` aj kód, ktorý si človek v zadnom rade prepíše z plátna.
 *
 * Tieto dve úlohy si protirečia, preto abeceda a dĺžka:
 *
 * - **Bez 0/O/1/I/L/U.** Nula a písmeno O sú na projektore na desiatich metroch
 *   nerozoznateľné, rovnako jednotka a I. U vypadlo, aby z tokenu nemohlo
 *   náhodou vzniknúť čitateľné vulgárne slovo (Crockford Base32 to rieši
 *   rovnako).
 * - **Desať znakov** z 32-znakovej abecedy je 2^50 kombinácií. Pri limiteri
 *   `questions` (8/min na IP) je hádanie beznádejné, a zároveň je to adresa,
 *   ktorá sa ešte dá odpísať.
 *
 * Na zobrazenie sa token láme na dve päťice (`A7K2M-9QXBF`). Do URL ide vždy
 * bez pomlčky a porovnáva sa case-insensitive — človek, čo prepisuje z plátna,
 * nemá riešiť veľkosť písmen ani to, či pomlčka patrí do adresy.
 */
final class BoardToken
{
    public const LENGTH = 10;

    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generate(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $token = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }

        return $token;
    }

    /**
     * Token z toho, čo prišlo v URL alebo čo človek naťukal: veľké písmená,
     * bez pomlčiek a medzier. Vracia null, keď to token byť nemôže — routa
     * potom rovno 404-uje a nejde sa ani do databázy.
     */
    public static function normalize(?string $raw): ?string
    {
        $token = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $raw) ?? '');

        if (strlen($token) !== self::LENGTH) {
            return null;
        }

        return strspn($token, self::ALPHABET) === self::LENGTH ? $token : null;
    }

    /** Tvar na plátno a do dashboardu: `A7K2M-9QXBF`. */
    public static function forDisplay(string $token): string
    {
        return implode('-', str_split($token, 5));
    }
}
