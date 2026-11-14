<?php

namespace App\Services\Canals;

use App\Services\Security\SafeRemoteFetcher;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Stiahne verejnú stránku webu kanála pre CanalEmailFinder a CanalLogoFinder.
 *
 * Adresu zadal cudzí človek alebo import, takže ide cez SafeRemoteFetcher —
 * každá zastávka presmerovania sa kontroluje (Url::isSafeToProbe), inak by sa
 * cez `Location: http://127.0.0.1/…` dal dotaz zaviesť do vnútornej siete.
 */
class CanalWebsitePages
{
    private const MAX_BYTES = 1_500_000;

    /** HTML stránky, alebo null, keď sa nedá stiahnuť. */
    public function fetch(string $url): ?string
    {
        return $this->fetchPage($url)['body'] ?? null;
    }

    /**
     * HTML stránky spolu s adresou, na ktorej po presmerovaniach skončila —
     * relatívne odkazy (logo, obrázky) treba skladať od nej, nie od zadanej.
     *
     * @return array{url: string, body: string}|null
     */
    public function fetchPage(string $url): ?array
    {
        $result = $this->get($url, 'text/html,*/*;q=0.5');

        return $result === null ? null : ['url' => $result['url'], 'body' => substr($result['response']->body(), 0, self::MAX_BYTES)];
    }

    /**
     * Obrázok z webu (logo kanála) s rovnakou kontrolou presmerovaní ako stránka.
     *
     * @return array{url: string, body: string, mime: string}|null
     */
    public function fetchImage(string $url, int $maxBytes): ?array
    {
        $result = $this->get($url, 'image/webp,image/png,image/*;q=0.8');

        if ($result === null) {
            return null;
        }

        $body = $result['response']->body();

        if ($body === '' || strlen($body) > $maxBytes) {
            return null;
        }

        $mime = strtolower(trim(explode(';', (string) $result['response']->header('Content-Type'))[0]));

        return ['url' => $result['url'], 'body' => $body, 'mime' => $mime];
    }

    /** @return array{url: string, response: Response}|null */
    private function get(string $url, string $accept): ?array
    {
        $timeout = max(1, (int) config('canals.emails.discovery.timeout', 8));

        try {
            $result = app(SafeRemoteFetcher::class)->get($url, $accept, timeout: $timeout);
        } catch (Throwable) {
            return null;
        }

        return $result['response']->successful() ? $result : null;
    }

    /** Odkaz zo stránky ako absolútna adresa. */
    public function absolute(string $base, string $href): ?string
    {
        return SafeRemoteFetcher::absolute($base, $href);
    }
}
