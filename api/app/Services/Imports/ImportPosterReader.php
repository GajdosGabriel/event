<?php

namespace App\Services\Imports;

use App\Services\OpenAI\ChatGPT;
use App\Services\Security\SafeRemoteFetcher;
use Illuminate\Support\Facades\Log;

/**
 * Prepis plagátu pri importe.
 *
 * Zdroje (najmä kalendár ecav.sk) majú pri podujatí často len vetu
 * „Srdečne pozývame." a všetko podstatné — program, účinkujúci, čas,
 * vstupné — je iba na plagáte. spisor.eu číta len PDF, obrázkový plagát
 * doteraz ostal nevyužitý. Tu ho prečíta vision tým istým volaním ako
 * ručné nahratie plagátu (ChatGPT::extractDataFromPoster → `poster_text`).
 *
 * Číta sa len hlavný obrázok a len pri krátkom texte článku: dlhý článok
 * plagát spravidla opisuje a vision stojí rádovo viac než textové volanie.
 */
class ImportPosterReader
{
    /** Pod túto dĺžku textu článku (bez HTML) sa plagát oplatí čítať. */
    public const SHORT_TEXT_CHARS = 400;

    /** Väčší obrázok sa na vision neposiela (zhodne s nahraním plagátu). */
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        private readonly ?ChatGPT $chatGPT = null,
        private readonly SafeRemoteFetcher $fetcher = new SafeRemoteFetcher,
    ) {}

    /** Z kontajnera až pri volaní — import ho vytvára bez neho a test ho podvrhne. */
    private function chatGPT(): ChatGPT
    {
        return $this->chatGPT ?? app(ChatGPT::class);
    }

    public function enabled(): bool
    {
        return (bool) config('services.imports.read_posters', false);
    }

    public function shouldRead(string $articleText): bool
    {
        return $this->enabled() && mb_strlen(trim($articleText)) < self::SHORT_TEXT_CHARS;
    }

    /**
     * Doslovný prepis textu z plagátu, alebo null (nedá sa stiahnuť, nie je to
     * obrázok, AI zlyhala, na plagáte nie je text). Chyba import nezastaví.
     */
    public function transcribe(string $imageUrl): ?string
    {
        try {
            $dataUrl = $this->dataUrl($imageUrl);

            if ($dataUrl === null) {
                return null;
            }

            $data = $this->chatGPT()->extractDataFromPoster('', [$dataUrl]);
            $text = is_string($data['poster_text'] ?? null) ? trim($data['poster_text']) : '';

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('ImportPosterReader: plagát sa nepodarilo prečítať.', [
                'image_url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function dataUrl(string $imageUrl): ?string
    {
        $result = $this->fetcher->get(
            $imageUrl,
            accept: 'image/*',
            userAgent: (string) config('services.imports.user_agent'),
            timeout: 30,
            maxBytes: self::MAX_IMAGE_BYTES,
        );

        $response = $result['response'];
        if (! $response->successful()) {
            return null;
        }

        $binary = $response->body();
        $mime = $this->detectMime($binary) ?? strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if ($binary === '' || ! in_array($mime, self::IMAGE_MIMES, true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    /** Typ podľa obsahu — hlavička zdroja býva `application/octet-stream`. */
    private function detectMime(string $binary): ?string
    {
        $info = $binary !== '' ? @getimagesizefromstring($binary) : false;

        return is_array($info) && is_string($info['mime'] ?? null) ? strtolower($info['mime']) : null;
    }
}
