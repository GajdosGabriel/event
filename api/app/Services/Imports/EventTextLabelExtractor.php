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

        return null;
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
