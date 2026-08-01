<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Časové okná verejných výpisov.
 *
 * Landing „tento víkend" existuje dvakrát — raz v SPA (cez `/api/events`)
 * a raz v bot-render vrstve. Keby si každá počítala víkend sama, crawler by
 * indexoval iný zoznam, než akým sa mu stránka odmení po kliknutí.
 */
final class EventTimeframe
{
    /**
     * Víkend počítame od piatka rána do nedele polnoci. V piatok a cez víkend
     * teda ide o prebiehajúci víkend, nie o nasledujúci — človek, ktorý si
     * v sobotu otvorí „čo je tento víkend", chce dnešok, nie o týždeň.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function thisWeekend(): array
    {
        $from = now()->startOfWeek()->addDays(4)->startOfDay();
        $to = now()->startOfWeek()->addDays(6)->endOfDay();

        if (now()->greaterThan($to)) {
            $from = $from->addWeek();
            $to = $to->addWeek();
        }

        return [$from, $to];
    }
}
