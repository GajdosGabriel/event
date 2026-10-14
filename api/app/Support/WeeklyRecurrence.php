<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Týždenne opakované podujatie z textu oznamu: „Modlitba sa koná každý
 * pondelok o 18:00", „Prednášky sa konajú pravidelne v pondelky o 18:00".
 *
 * Takýto článok nemá konkrétny dátum, takže ho regex aj AI nechali bez
 * začiatku a import ho založil ako koncept, z ktorého sa už nikdy nepohol.
 * Najbližší výskyt je skutočný termín, na ktorý sa dá prísť.
 *
 * Bez času sa opakovanie nerozpoznáva — „každú nedeľu" bez hodiny je skôr
 * opis farnosti než pozvánka a vymyslený čas by na webe klamal.
 *
 * Žije na jednom mieste, aby ho používal import aj migrácia, ktorá opravuje
 * už uložené koncepty.
 */
class WeeklyRecurrence
{
    private const TIMEZONE = 'Europe/Bratislava';

    /** Kmeň názvu dňa (všetky pády a množné číslo) → ISO deň v týždni. */
    private const WEEKDAYS = [
        'pondel' => CarbonInterface::MONDAY,
        'utor' => CarbonInterface::TUESDAY,
        'stred' => CarbonInterface::WEDNESDAY,
        'štvrt' => CarbonInterface::THURSDAY,
        'piat' => CarbonInterface::FRIDAY,
        'sobot' => CarbonInterface::SATURDAY,
        'nede' => CarbonInterface::SUNDAY,
    ];

    private const PATTERN = '/(?:každ[ýúé]|pravidelne\s+(?:v|vo|každ[ýúé])|vždy\s+(?:v|vo))\s+'
        .'(pondel\p{L}*|utor\p{L}*|stred\p{L}*|štvrt\p{L}*|piat\p{L}*|sobot\p{L}*|nede[lľ]\p{L}*)'
        .'[^.\d]{0,30}?\bo\s+(\d{1,2})[:.](\d{2})\b/iu';

    /**
     * Najbližší výskyt po `$after` (v UTC), alebo null, keď text o týždennom
     * opakovaní nehovorí.
     */
    public static function nextOccurrence(string $text, CarbonInterface $after): ?CarbonImmutable
    {
        if (! preg_match(self::PATTERN, $text, $m)) {
            return null;
        }

        $weekday = self::weekday($m[1]);
        $hour = (int) $m[2];
        $minute = (int) $m[3];

        if ($weekday === null || $hour > 23 || $minute > 59) {
            return null;
        }

        $local = CarbonImmutable::instance($after)->setTimezone(self::TIMEZONE);
        $candidate = $local->setTime($hour, $minute);

        while ($candidate->dayOfWeek !== $weekday || $candidate->lessThanOrEqualTo($local)) {
            $candidate = $candidate->addDay();
        }

        return $candidate->utc();
    }

    private static function weekday(string $word): ?int
    {
        $word = str_replace('ľ', 'l', mb_strtolower($word));

        foreach (self::WEEKDAYS as $stem => $day) {
            if (str_starts_with($word, $stem)) {
                return $day;
            }
        }

        return null;
    }
}
