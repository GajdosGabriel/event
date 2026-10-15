<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Ľudský zápis termínu — „5. 9. 2026 16:00 - 19:30".
 *
 * Rovnaká veta sa skladala na troch miestach naraz (EventResource, RSVP stránka,
 * zoznam termínov série) a zakaždým vlastnou kópiou kódu. Formát je pritom
 * súčasť toho, ako portál vyzerá: keď sa raz zmení, má sa zmeniť všade.
 *
 * Frontend má vlastnú dvojičku ([ui/src/utils/dateFormat.ts]) — tá pracuje
 * s lokalizáciou prehliadača a tu ju nahradiť nevieme.
 */
final class EventDateRange
{
    /** Zóna, v ktorej sa termíny zobrazujú; v DB je UTC. */
    private const TIMEZONE = 'Europe/Bratislava';

    /** Jeden okamih v zobrazovacej zóne — „5. 9. 2026 16:00". */
    public static function moment(?CarbonInterface $value): ?string
    {
        return $value?->copy()->setTimezone(self::TIMEZONE)->format('d. m. Y H:i');
    }

    public static function label(?CarbonInterface $start, ?CarbonInterface $end): ?string
    {
        if (! $start instanceof CarbonInterface) {
            return null;
        }

        $start = $start->copy()->setTimezone(self::TIMEZONE);
        $end = $end?->copy()->setTimezone(self::TIMEZONE);

        if (! $end instanceof CarbonInterface) {
            return $start->format('d. m. Y H:i');
        }

        // Celodenné podujatie (importy ho zapisujú ako 00:00 – 23:59) — časy
        // by len hovorili „od polnoci do polnoci", stačí dátum.
        if (self::isAllDay($start, $end)) {
            // Koniec o polnoci patrí ešte predchádzajúcemu dňu.
            $lastDay = $end->format('H:i') === '00:00' ? $end->copy()->subMinute() : $end;

            return $start->isSameDay($lastDay)
                ? $start->format('d. m. Y')
                : sprintf('%s - %s', $start->format('d. m. Y'), $lastDay->format('d. m. Y'));
        }

        // V rámci jedného dňa sa dátum neopakuje — „16:00 - 19:30" stačí.
        if ($start->isSameDay($end)) {
            return sprintf('%s - %s', $start->format('d. m. Y H:i'), $end->format('H:i'));
        }

        return sprintf('%s - %s', $start->format('d. m. Y H:i'), $end->format('d. m. Y H:i'));
    }

    /** Začína o polnoci a končí 23:59 (alebo polnocou ďalšieho dňa) v zobrazovacej zóne. */
    private static function isAllDay(CarbonInterface $start, CarbonInterface $end): bool
    {
        if ($start->format('H:i') !== '00:00') {
            return false;
        }

        return $end->format('H:i') === '23:59'
            || ($end->format('H:i') === '00:00' && $end->greaterThan($start));
    }
}
