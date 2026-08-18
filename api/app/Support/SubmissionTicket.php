<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Podpísaná známka „tento formulár sa naozaj otvoril a bolo to vtedy".
 *
 * Vydá ju endpoint, ktorý formulár naplní dátami, a odosielanie ju pýta späť.
 * Slúži proti botovi, ktorý adresu `/api/q/{token}/questions` našiel a búši do
 * nej priamo — bez GETu na nástenku známku nemá odkiaľ vziať, a keď si ju raz
 * vypýta, časová podmienka mu obmedzí tempo.
 *
 * Čas je vnútri podpisu, nie vedľa neho: keby ho posielal klient ako obyčajné
 * číslo (`rendered_at`), stačilo by ho v požiadavke prepísať a celá poistka by
 * bola dekorácia. Šifrovanie appkey je lacnejšie než tabuľka jednorazových
 * tokenov a nič sa nemusí upratovať — známka sama expiruje.
 *
 * Nie je to ochrana proti cielenému útočníkovi (známka sa dá vydať znova
 * a v okne opakovane použiť). To rieši limiter na IP a dedup otázok.
 */
final class SubmissionTicket
{
    public static function issue(string $scope): string
    {
        return Crypt::encryptString($scope . '|' . now()->getTimestamp() . '|' . Str::random(8));
    }

    /**
     * Je známka pre daný účel platná a stará aspoň `$minAgeSeconds`,
     * ale nie staršia než `$maxAgeSeconds`?
     */
    public static function isValid(?string $ticket, string $scope, int $minAgeSeconds, int $maxAgeSeconds): bool
    {
        if (! is_string($ticket) || $ticket === '') {
            return false;
        }

        try {
            $payload = Crypt::decryptString($ticket);
        } catch (\Throwable) {
            return false;
        }

        $parts = explode('|', $payload);

        if (count($parts) !== 3 || ! hash_equals($scope, $parts[0])) {
            return false;
        }

        $age = now()->getTimestamp() - (int) $parts[1];

        return $age >= $minAgeSeconds && $age <= $maxAgeSeconds;
    }
}
