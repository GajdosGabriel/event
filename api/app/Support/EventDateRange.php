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

        // V rámci jedného dňa sa dátum neopakuje — „16:00 - 19:30" stačí.
        if ($start->isSameDay($end)) {
            return sprintf('%s - %s', $start->format('d. m. Y H:i'), $end->format('H:i'));
        }

        return sprintf('%s - %s', $start->format('d. m. Y H:i'), $end->format('d. m. Y H:i'));
    }
}
