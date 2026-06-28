<?php

namespace App\Services\OpenAI;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Services\OpenAI\{PromptCanal, PromptCopywriter, PromptData, PromptTextEditor, PromptVenue};
use OpenAI\Laravel\Facades\OpenAI;

class ChatGPT
{
    public function __construct(
        private readonly PromptData $promptData = new PromptData(),
        private readonly PromptCopywriter $promptCopywriter = new PromptCopywriter(),
        private readonly PromptVenue $promptVenue = new PromptVenue(),
        private readonly PromptCanal $promptCanal = new PromptCanal(),
        private readonly PromptTextEditor $promptTextEditor = new PromptTextEditor(),
    ) {}

    public function extractData(array|string $input): array
    {
        $text = $this->normalizeInput($input);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            // 'model' => 'gpt-4o',
            'temperature' => 0,
            'response_format' => $this->promptData->jsonSchema(),
            'messages' => $this->promptData->prompt($text),
        ]);

        $content = $response->choices[0]->message->content ?? null;
        if (!$content) {
            throw new \RuntimeException('Prazdna odpoved od OpenAI');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Neplatny JSON: ' . json_last_error_msg());
        }

        $data = $this->normalizeResponseData($data);
        $data = $this->applyEventDateTimeFallbackFromText($data, $text);

        $validator = Validator::make($data, $this->promptData->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        return $data;
    }

    public function extractCopywriter(array|string $input): array
    {
        $text = $this->normalizeInput($input);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'temperature' => 0,
            'response_format' => $this->promptCopywriter->jsonSchema(),
            'messages' => $this->promptCopywriter->prompt($text),
        ]);

        $content = $response->choices[0]->message->content ?? null;
        if (!$content) {
            throw new \RuntimeException('Prazdna odpoved od OpenAI');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Neplatny JSON: ' . json_last_error_msg());
        }

        $data = $this->normalizeResponseData($data);

        $validator = Validator::make($data, $this->promptCopywriter->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        // zakomentováno, protože teraz chcem len čistý text
        // if (!empty($data['event_body']) && is_string($data['event_body'])) {
        //     $data['event_body'] = $this->addEventClasses($data['event_body']);
        // }

        return $data;
    }

    public function extractTextEdit(string $text, array $modes): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'temperature' => 0.3,
            'response_format' => $this->promptTextEditor->jsonSchema(),
            'messages' => $this->promptTextEditor->prompt($text, $modes),
        ]);

        $content = $response->choices[0]->message->content ?? null;
        if (!$content) {
            throw new \RuntimeException('Prázdna odpoveď od OpenAI');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Neplatný JSON: ' . json_last_error_msg());
        }

        $validator = Validator::make($data, $this->promptTextEditor->validator());
        if ($validator->fails()) {
            throw new \RuntimeException('Neplatná štruktúra dát: ' . $validator->errors()->toJson());
        }

        return $data;
    }

    public function extractVenueDetails(array|string $input): array
    {
        $text = $this->normalizeInput($input);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'temperature' => 0,
            'response_format' => $this->promptVenue->jsonSchema(),
            'messages' => $this->promptVenue->prompt($text),
        ]);

        $content = $response->choices[0]->message->content ?? null;
        if (!$content) {
            throw new \RuntimeException('Prazdna odpoved od OpenAI');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Neplatny JSON: ' . json_last_error_msg());
        }

        $data = $this->normalizeResponseData($data);
        $data = $this->applyVenueFallbackFromText($data, $text);

        $validator = Validator::make($data, $this->promptVenue->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        return $data;
    }

    public function extractCanalName(array|string $input): ?string
    {
        $text = $this->normalizeInput($input);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'temperature' => 0,
            'response_format' => $this->promptCanal->jsonSchema(),
            'messages' => $this->promptCanal->prompt($text),
        ]);

        $content = $response->choices[0]->message->content ?? null;
        if (!$content) {
            throw new \RuntimeException('Prazdna odpoved od OpenAI');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Neplatny JSON: ' . json_last_error_msg());
        }

        $validator = Validator::make($data, $this->promptCanal->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatna struktura dat: ' . $validator->errors()->toJson());
        }

        $name = $data['canal_name'] ?? null;

        if (! is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name !== '' ? $name : null;
    }

    private function applyEventDateTimeFallbackFromText(array $data, string $text): array
    {
        // Regex fallback — only fills in what AI left null; never overrides a found value
        if (($data['start_at'] ?? null) === null) {
            $explicitStart = $this->extractExplicitStartDateTime($text);
            if ($explicitStart instanceof Carbon) {
                $data['start_at'] = $explicitStart->format('Y-m-d H:i:s');
            }
        }

        $startAt = $this->parseDateTime($data['start_at'] ?? null);
        $endAt = $this->parseDateTime($data['end_at'] ?? null);

        if ($startAt instanceof Carbon && $endAt instanceof Carbon && $endAt->lessThanOrEqualTo($startAt)) {
            $data['end_at'] = null;
        }

        return $data;
    }

    private function extractExplicitStartDateTime(string $text): ?Carbon
    {
        $patterns = [
            '/\b(?:v\s+)?(?:pondelok|utorok|streda|štvrtok|piatok|sobota|nedeľa)?\s*(\d{1,2})\.\s*([[:alpha:]áäčďéíĺľňóôŕšťúýž]+)\s+(\d{4})\s+(?:o\s*)?(\d{1,2}):(\d{2})\b/iu',
            '/\b(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(?:o\s*)?(\d{1,2}):(\d{2})\b/u',
        ];

        foreach ($patterns as $index => $pattern) {
            if (! preg_match($pattern, $text, $match)) {
                continue;
            }

            if ($index === 0) {
                $month = $this->slovakMonthToNumber((string) $match[2]);
                if ($month === null) {
                    continue;
                }

                return $this->safeCreateDateTime((int) $match[3], $month, (int) $match[1], (int) $match[4], (int) $match[5]);
            }

            return $this->safeCreateDateTime((int) $match[3], (int) $match[2], (int) $match[1], (int) $match[4], (int) $match[5]);
        }

        return null;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, config('app.timezone', 'Europe/Bratislava'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeCreateDateTime(int $year, int $month, int $day, int $hour, int $minute): ?Carbon
    {
        try {
            return Carbon::create(
                $year,
                $month,
                $day,
                $hour,
                $minute,
                0,
                config('app.timezone', 'Europe/Bratislava')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function slovakMonthToNumber(string $month): ?int
    {
        $normalized = mb_strtolower(trim($month, ". \t\n\r\0\x0B"));

        return match ($normalized) {
            'januar', 'januára', 'januara' => 1,
            'februar', 'februára', 'februara' => 2,
            'marec', 'marca' => 3,
            'april', 'apríla', 'aprila' => 4,
            'maj', 'mája', 'maja' => 5,
            'jun', 'júna', 'juna' => 6,
            'jul', 'júla', 'jula' => 7,
            'august', 'augusta' => 8,
            'september', 'septembra' => 9,
            'oktober', 'októbra', 'oktobra' => 10,
            'november', 'novembra' => 11,
            'december', 'decembra' => 12,
            default => null,
        };
    }

    private function addEventClasses(string $html): string
    {

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//p') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-text');
            }
        }

        foreach ($xpath->query('//h3') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-section-title');
            }
        }

        foreach ($xpath->query('//ul') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-list');
            }
        }

        foreach ($xpath->query('//li') as $node) {
            if ($node instanceof \DOMElement) {
                $node->setAttribute('class', 'event-list-item');
            }
        }

        libxml_clear_errors();

        return $dom->saveHTML();
    }

    private function applyVenueFallbackFromText(array $data, string $text): array
    {
        $venueLine = $this->extractVenueLine($text);
        if ($venueLine === null) {
            return $data;
        }

        $parts = preg_split('/\s*,\s*/u', $venueLine) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return $data;
        }

        $fallbackName = $parts[0] ?? null;
        if (($data['name'] ?? null) === null) {
            $data['name'] = $fallbackName;
        }

        if (($data['city'] ?? null) === null) {
            $data['city'] = $parts[1] ?? null;
        }

        if (($data['street'] ?? null) === null && count($parts) >= 3) {
            $data['street'] = $parts[2];
        }

        // If AI returns only city as venue name, prefer the explicit place from "Miesto konania".
        if (
            is_string($fallbackName)
            && $fallbackName !== ''
            && is_string($data['name'] ?? null)
            && is_string($data['city'] ?? null)
            && mb_strtolower(trim((string) $data['name'])) === mb_strtolower(trim((string) $data['city']))
        ) {
            $data['name'] = $fallbackName;
        }

        if (($data['country'] ?? null) === null && ($data['city'] ?? null) !== null) {
            $data['country'] = 'Slovensko';
        }

        return $data;
    }

    private function extractVenueLine(string $text): ?string
    {
        if (!preg_match('/Miesto\s+konania\s*:\s*([^\n\r]+)/iu', $text, $match)) {
            return null;
        }

        $line = trim($match[1]);

        return $line !== '' ? $line : null;
    }

    private function normalizeInput(array|string $input): string
    {
        if (is_array($input)) {
            if (isset($input['text']) && is_string($input['text'])) {
                return $this->sanitizeUtf8($input['text']);
            }

            $json = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            return $this->sanitizeUtf8($json === false ? '' : $json);
        }

        return $this->sanitizeUtf8($input);
    }

    private function sanitizeUtf8(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('//u', $value) === 1) {
            return trim($value);
        }

        $converted = mb_convert_encoding(
            $value,
            'UTF-8',
            'UTF-8, Windows-1250, ISO-8859-2, ISO-8859-1, Windows-1252'
        );

        if (!is_string($converted)) {
            $converted = $value;
        }

        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $converted);
        if ($clean !== false) {
            return trim($clean);
        }

        return trim($converted);
    }

    private function normalizeResponseData(array $data): array
    {
        $stringFields = [
            'title',
            'start_at',
            'end_at',
            'organization',
            'building',
            'name',
            'street',
            'street_and_number',
            'city',
            'postcode',
            'country',
            'email',
            'phone',
            'event_body',
        ];

        foreach ($stringFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->normalizeStringValue($data[$field]);
        }

        return $data;
    }

    private function normalizeStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $this->decodeEscapedString(trim($value));
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_string($item) || is_int($item) || is_float($item) || is_bool($item)) {
                    $parts[] = trim((string) $item);
                }
            }

            return $parts ? implode(', ', $parts) : null;
        }

        return null;
    }

    private function decodeEscapedString(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!preg_match('/\\\\u[0-9a-fA-F]{4}|\\\\[nrtf"\\\\\\/]/', $value)) {
            return $value;
        }

        $value = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static function (array $match): string {
                $bytes = pack('H*', $match[1]);
                $char = @mb_convert_encoding($bytes, 'UTF-8', 'UCS-2BE');
                return is_string($char) ? $char : $match[0];
            },
            $value
        ) ?? $value;

        return strtr($value, [
            '\\n' => "\n",
            '\\r' => "\r",
            '\\t' => "\t",
            '\\f' => "\f",
            '\\"' => '"',
            "\\'" => "'",
            '\\/' => '/',
            '\\\\' => '\\',
        ]);
    }
}
