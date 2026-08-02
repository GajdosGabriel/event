<?php

namespace App\Support;

/**
 * Overenie tokenu pre údržbové endpointy spúšťané cez URL (/cron/schedule-run,
 * /api/artisan/run). Hosting nemá shell, takže tieto routy sú jediný spôsob,
 * ako scheduler a vyčistenie cache spustiť — a zároveň jediné, čo ich chráni,
 * je tento token.
 */
class CronToken
{
    public static function isValid(?string $token): bool
    {
        $secret = (string) config('app.cron_secret');

        // Nenastavený CRON_SECRET nesmie endpoint otvoriť dokorán: bez tejto
        // podmienky by sa prázdny secret zhodoval s chýbajúcim tokenom
        // a ktokoľvek by mohol endpoint volať.
        if ($secret === '') {
            return false;
        }

        // hash_equals porovnáva v konštantnom čase — bežné `===` skončí na
        // prvom odlišnom bajte a rozdiel v čase odpovede prezradí, koľko znakov
        // tokenu útočník uhádol.
        return hash_equals($secret, (string) $token);
    }
}
