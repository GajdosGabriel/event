<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;

/**
 * Extracts structured data (organizer, venue) from plain-text event body
 * using label patterns. Source-agnostic — works for Vyveska, ECAV, TKKBS, etc.
 */
class EventTextLabelExtractor
{
    // Labels that identify the organizer/canal, ordered by specificity
    private const ORGANIZER_PATTERNS = [
        '/\bOrganiz[aá]tor(?:i|ov|ka)?\s+pozv[aá]nky\s*:\s*([^,\n\r]+)/iu',
        '/\bOrganiz[aá]tor(?:i|ov|ka)?\s*:\s*([^,\n\r]+)/iu',
        '/\bUsporiadateľ(?:ia|ov|ka)?\s*:\s*([^,\n\r]+)/iu',
        '/\bUsporad[uú]va(?:teľ|júci)?\s*:\s*([^,\n\r]+)/iu',
        '/\bOrganizuje\s*:\s*([^,\n\r]+)/iu',
        '/\bOrganizuj[uú]\s*:\s*([^,\n\r]+)/iu',
        '/\bVydáva\s*:\s*([^,\n\r]+)/iu',
        '/\bPoriadateľ\s*:\s*([^,\n\r]+)/iu',
    ];

    // Labels that identify the venue location, ordered by specificity
    private const VENUE_PATTERNS = [
        '/\bMiesto\s+konania\s*:\s*([^\n\r]+)/iu',
        '/\bMiesto\s+stretnutia\s*:\s*([^\n\r]+)/iu',
        '/\bMiesto\s+podujatia\s*:\s*([^\n\r]+)/iu',
        '/\bMiesto\s*:\s*([^\n\r]+)/iu',
        '/\bAdresa\s+konania\s*:\s*([^\n\r]+)/iu',
        '/\bKde\s*:\s*([^\n\r]+)/iu',
    ];

    // Prose patterns: venue mentioned inline (e.g. "o 18:00 v Katedrále sv. Martina v Bratislave")
    // Group 1 = venue name, Group 2 (optional) = city name
    private const PROSE_VENUE_PATTERNS = [
        // "o HH:MM v [Venue...] v [City]" — venue + city after time
        '/o\s+\d{1,2}[:.]\d{2}\s+v\s+(\p{Lu}[^,\n]+?)\s+v\s+(\p{Lu}[^,.\n\s]+)/u',
        // "o HH:MM v [Venue]." — venue only after time
        '/o\s+\d{1,2}[:.]\d{2}\s+v\s+(\p{Lu}[^,.\n]+?)(?=[.,])/u',
    ];

    // Location prepositions that introduce a place/city segment after the time.
    private const LOCATION_PREPOSITIONS = ['na', 'vo', 've', 'v', 'pri'];

    // Words that mark a segment as the venue (building) rather than the city.
    private const VENUE_KEYWORD_PATTERN =
        '/\b(?:centr\w+|dom\b|kult\w+\s+dom|kostol\w*|chr[aá]m\w*|katedr\w+|bazilik\w+|kaplnk\w+|synag\w+|'
        . 'farsk\w*|farnos\w*|pastora\w+|divadl\w*|kino\w*|hal[ae]\b|aren[ay]\b|[sš]tadi[oó]n\w*|amfite\w+|'
        . 'm[uú]ze\w+|gal[eé]ri\w+|kni[zž]nic\w+|[sš]kol\w*|gymn[aá]zi\w+|aula\w*|univerz\w+|hotel\w*|penzi[oó]n\w*|'
        . 'reštaur\w*|restaur\w*|klub\w*|s[aá]l[ae]\b|centre\b|amfiteat\w+)/iu';

    /**
     * Returns the organizer name extracted from the text, or null.
     */
    public function extractOrganizerName(string $text): ?string
    {
        foreach (self::ORGANIZER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $name = $this->sanitize(trim($match[1]));
                if ($name !== null && $name !== '') {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Returns ['name' => string|null, 'city' => string|null] or null.
     *
     * Slovak event format: "label: [city/municipality], [specific venue name]"
     * First comma-delimited segment is treated as the municipality/city,
     * the remaining segments form the venue name.
     * Falls back to prose detection ("o 18:00 v Venue v City").
     */
    public function extractVenue(string $text): ?array
    {
        foreach (self::VENUE_PATTERNS as $pattern) {
            if (! preg_match($pattern, $text, $match)) {
                continue;
            }

            $line = trim($match[1]);
            $parts = array_values(array_filter(
                array_map('trim', preg_split('/\s*,\s*/u', $line) ?: []),
                static fn (string $p): bool => $p !== ''
            ));

            if ($parts === []) {
                continue;
            }

            if (count($parts) === 1) {
                // Single token: could be city-only or venue-only — treat as venue name
                return ['name' => $parts[0], 'city' => null];
            }

            // "city, venue" — first token is the municipality, rest is the venue
            $city = $parts[0];
            $name = implode(', ', array_slice($parts, 1));

            return ['name' => $name, 'city' => $city];
        }

        return $this->extractVenueFromProse($text);
    }

    /**
     * Extracts venue mentioned inline in prose, e.g.:
     * "... o 18:00 v Katedrále svätého Martina v Bratislave."
     */
    private function extractVenueFromProse(string $text): ?array
    {
        foreach (self::PROSE_VENUE_PATTERNS as $index => $pattern) {
            if (! preg_match($pattern, $text, $match)) {
                continue;
            }

            $name = $this->sanitize(trim($match[1]));
            if ($name === null || $name === '') {
                continue;
            }

            $city = isset($match[2]) ? ($this->sanitize(trim($match[2])) ?? null) : null;

            return ['name' => $name, 'city' => $city];
        }

        return $this->extractVenueFromChainedPrepositions($text);
    }

    /**
     * Handles announcements where the venue and city are spread over a chain of
     * prepositional phrases after the time, e.g.:
     *   "… o 18.00 na Kalvárii v Nitre vo Farskom pastoračnom centre (vchod …"
     *
     * The plain "o HH:MM v [Venue]" patterns above only accept a single "v" and
     * a single line, so they miss "na/vo" connectors and text broken across lines.
     * Here the segment carrying a venue keyword (centrum, kostol, dom, …) is the
     * venue; the nearest neighbouring segment is treated as the city.
     */
    private function extractVenueFromChainedPrepositions(string $text): ?array
    {
        // Collapse newlines/spaces so a multi-line address becomes one clause,
        // then read a bounded window after the time (up to a "(" or ~160 chars)
        // to avoid running into unrelated later sentences.
        $normalized = preg_replace('/\s+/u', ' ', $text) ?? $text;

        if (! preg_match('/o\s+\d{1,2}[:.]\d{2}\s+(.+)/u', $normalized, $m)) {
            return null;
        }

        $tail = $m[1];
        $stop = mb_strpos($tail, '(');
        if ($stop !== false) {
            $tail = mb_substr($tail, 0, $stop);
        }
        $tail = mb_substr($tail, 0, 160);

        $prepositions = implode('|', self::LOCATION_PREPOSITIONS);
        $stopwords = $prepositions . '|a|i|o|s|so|z|zo|do|od|za|k|ku|pre|po|cez|u';

        // Each segment: a preposition + a phrase that starts with a capitalized
        // word and continues with words that are not another preposition/conjunction.
        $segmentPattern = '/\b(?:' . $prepositions . ')\s+'
            . '(\p{Lu}[\p{L}.\-]*(?:\s+(?!(?:' . $stopwords . ')\b)\p{L}[\p{L}.\-]*)*)/u';

        if (! preg_match_all($segmentPattern, $tail, $matches) || count($matches[1]) < 2) {
            return null;
        }

        $segments = array_values(array_filter(
            array_map(fn (string $s): ?string => $this->sanitize(trim($s)), $matches[1]),
            static fn (?string $s): bool => $s !== null && $s !== ''
        ));

        if (count($segments) < 2) {
            return null;
        }

        // Venue = last segment matching a venue keyword; otherwise the last segment.
        $venueIndex = null;
        foreach ($segments as $i => $segment) {
            if (preg_match(self::VENUE_KEYWORD_PATTERN, $segment) === 1) {
                $venueIndex = $i;
            }
        }
        if ($venueIndex === null) {
            $venueIndex = count($segments) - 1;
        }

        $venueName = $segments[$venueIndex];

        // City = nearest neighbouring segment (prefer the preceding one) that is
        // not itself a venue-like phrase.
        $city = null;
        foreach ([$venueIndex - 1, $venueIndex + 1] as $candidateIndex) {
            if (! isset($segments[$candidateIndex])) {
                continue;
            }
            if (preg_match(self::VENUE_KEYWORD_PATTERN, $segments[$candidateIndex]) === 1) {
                continue;
            }
            $city = $segments[$candidateIndex];
            break;
        }

        return ['name' => $venueName, 'city' => $city];
    }

    private function sanitize(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = trim($value, " \t\n\r\0\x0B,.;:-/");

        if ($value === '') {
            return null;
        }

        return Str::limit($value, 250, '');
    }
}
