<?php

namespace App\Services\Imports;

use App\Services\OpenAI\ChatGPT;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportedCanalNameResolver
{
    public function __construct(
        private readonly ChatGPT $chatGPT = new ChatGPT(),
        private readonly EventTextLabelExtractor $labelExtractor = new EventTextLabelExtractor(),
    ) {}

    /**
     * @return array{
     *   name: string,
     *   detected_name: string|null,
     *   source_origin: string,
     *   detected_venue_name: string|null,
     *   detected_venue_city: string|null,
     *   detected_venue_street: string|null,
     *   ai_start_at: Carbon|null,
     *   ai_end_at: Carbon|null,
     *   ai_email: string|null,
     *   ai_phone: string|null,
     * }
     */
    public function resolve(string $sourceUrl, string $title, string $text, bool $startAtFound = false, ?Carbon $referenceDate = null): array
    {
        $title = $this->normalizeEncoding($title);
        $text  = $this->normalizeEncoding($text);

        // Priority 1: explicit label ("Organizátor:", "Usporiadateľ:", …) — source-agnostic
        // Priority 2: domain-specific heuristic patterns (ECAV, KBS, …)
        $detectedName = $this->labelExtractor->extractOrganizerName($text)
            ?? $this->extractByHeuristics($title, $text);

        // Heuristic venue extraction — source-agnostic, works without AI
        $heuristicVenue      = $this->labelExtractor->extractVenue($text);
        $detectedVenueName   = $heuristicVenue['name'] ?? null;
        $detectedVenueCity   = $heuristicVenue['city'] ?? null;
        $detectedVenueStreet = null;

        $aiStartAt = null;
        $aiEndAt   = null;
        $aiEmail   = null;
        $aiPhone   = null;

        // AI activates only when regex left something missing
        $somethingMissing = $detectedName === null || $detectedVenueName === null || ! $startAtFound;

        if ((bool) config('services.imports.detect_canal_with_ai', false) && $somethingMissing) {
            try {
                $aiData = $this->chatGPT->extractData($title . "\n\n" . $text, $referenceDate);

                // Fill only what regex could not find — never override a found value
                if ($detectedName === null) {
                    $detectedName = $this->resolveOrganizerFromAiData($aiData);
                }

                $venueRaw = $aiData['venue'] ?? null;
                if (is_array($venueRaw)) {
                    $vn = is_string($venueRaw['name'] ?? null) ? trim((string) $venueRaw['name']) : null;
                    $vc = is_string($venueRaw['city'] ?? null) ? trim((string) $venueRaw['city']) : null;
                    $vs = is_string($venueRaw['street_and_number'] ?? null) ? trim((string) $venueRaw['street_and_number']) : null;
                    if ($detectedVenueName === null && $vn !== null && $vn !== '') {
                        $detectedVenueName = $vn;
                    }
                    if ($detectedVenueCity === null && $vc !== null && $vc !== '') {
                        $detectedVenueCity = $vc;
                    }
                    if ($vs !== null && $vs !== '') {
                        $detectedVenueStreet = $vs;
                    }
                }

                // Dates — only used when start_at was not found by regex
                if (! $startAtFound) {
                    $aiStartAt = $this->parseAiDateTime($aiData['start_at'] ?? null);
                    $aiEndAt   = $this->parseAiDateTime($aiData['end_at'] ?? null);
                }

                $aiEmail = $this->normalizeString($aiData['email'] ?? null);
                $aiPhone = $this->normalizeString($aiData['phone'] ?? null);
            } catch (\Throwable $e) {
                Log::warning('ImportedCanalNameResolver: AI fallback failed, regex results preserved.', [
                    'source_url' => $sourceUrl,
                    'title'      => $title,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $sourceOrigin = $this->extractOrigin($sourceUrl);

        return [
            'name'                  => $detectedName ?? $this->hostLabel($sourceUrl),
            'detected_name'         => $detectedName,
            'source_origin'         => $sourceOrigin,
            'detected_venue_name'   => $detectedVenueName,
            'detected_venue_city'   => $detectedVenueCity,
            'detected_venue_street' => $detectedVenueStreet,
            'ai_start_at'           => $aiStartAt,
            'ai_end_at'             => $aiEndAt,
            'ai_email'              => $aiEmail,
            'ai_phone'              => $aiPhone,
        ];
    }

    private function parseAiDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', trim($value), 'Europe/Bratislava')?->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);
        return $v !== '' ? $v : null;
    }

    private function resolveOrganizerFromAiData(array $aiData): ?string
    {
        $organizer = $aiData['organizer'] ?? null;
        if (is_array($organizer)) {
            $name = is_string($organizer['name'] ?? null) ? trim((string) $organizer['name']) : null;
            if ($name !== null && $name !== '') {
                return $this->sanitizeName($name);
            }
        }

        $venue = $aiData['venue'] ?? null;
        if (is_array($venue)) {
            $name = is_string($venue['name'] ?? null) ? trim((string) $venue['name']) : null;
            if ($name !== null && $name !== '') {
                return $this->sanitizeName($name);
            }
        }

        return null;
    }

    private function extractByHeuristics(string $title, string $text): ?string
    {
        $haystacks = [$title, $text];

        $patterns = [
            '/\b(Cirkevn[ýy]\s+zbor\s+ECAV\s+[^,.\n]{2,120})/iu',
            '/\b(CZ\s+ECAV\s+[^,.\n]{2,120})/iu',
            '/\b(Modlitebn[ée]\s+spoločenstvo\s+ECAV)\b/iu',
            '/\b(Modlitebn\p{L}*\s+spoločenstv\p{L}*\s+ECAV)\b/iu',
            '/\b(MOS\s+ECAV)\b/iu',
            '/\b(VD\s+ECAV)\b/iu',
            '/\b(ZD\s+ECAV)\b/iu',
            '/\b(EMC\s+ECAV)\b/iu',
            '/\b(TK\s+KBS)\b/iu',
            '/\b(Tlačov[aá]\s+kancel[aá]ria\s+KBS)\b/iu',
            '/\b(Konferencia\s+biskupov\s+Slovenska)\b/iu',
            '/\b(Evanjelick[áa]\s+cirkev\s+a\.\s*v\.\s+na\s+Slovensku)\b/iu',
        ];

        foreach ($haystacks as $haystack) {
            foreach ($patterns as $pattern) {
                if (! preg_match($pattern, $haystack, $matches)) {
                    continue;
                }

                $name = $this->sanitizeName($matches[1]);
                if ($name !== null) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function sanitizeName(string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = trim($value, " \t\n\r\0\x0B,.;:-/");

        if ($value === '') {
            return null;
        }

        return Str::limit($value, 250, '');
    }

    private function hostLabel(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        return $host !== '' ? $host : 'imported-source';
    }

    private function extractOrigin(string $url): string
    {
        $scheme = (string) (parse_url($url, PHP_URL_SCHEME) ?: 'https');
        $host   = (string) parse_url($url, PHP_URL_HOST);

        return $host !== '' ? $scheme . '://' . $host : $url;
    }

    private function normalizeEncoding(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        foreach (['Windows-1250', 'ISO-8859-2', 'Windows-1252'] as $encoding) {
            $converted = null;

            if (function_exists('mb_convert_encoding')) {
                try {
                    $converted = mb_convert_encoding($value, 'UTF-8', $encoding);
                } catch (\ValueError) {
                    $converted = null;
                }
            }

            if (! is_string($converted) || $converted === '') {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $value) ?: null;
            }

            if (is_string($converted) && $converted !== '' && (! function_exists('mb_check_encoding') || mb_check_encoding($converted, 'UTF-8'))) {
                return $converted;
            }
        }

        return $value;
    }
}
