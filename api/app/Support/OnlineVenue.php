<?php

namespace App\Support;

/**
 * Miesto, ktoré nie je miesto: online platforma (Zoom, Teams, YouTube…).
 *
 * Geokóder by „Zoom Communications" našiel v Kalifornii a mapa podujatia by sa
 * oddialila na celý svet. Takéto miesto súradnice nemá a hľadať sa nesmie.
 */
class OnlineVenue
{
    public const PATTERN =
        '/\b(?:zoom\w*|ms\s*teams|microsoft\s+teams|google\s+meet|webex|skype|discord|twitch|'
        .'youtube|facebook\s+live|instagram\s+live|livestream\w*|live\s*stream\w*|webin[aá]r\w*|online|on-line|'
        .'on\s+line|virtu[aá]ln\w*|streamovan\w*|vo?\s+vysielan\w*)\b/iu';

    public static function matches(?string $name): bool
    {
        $name = trim((string) $name);

        return $name !== '' && preg_match(self::PATTERN, $name) === 1;
    }
}
