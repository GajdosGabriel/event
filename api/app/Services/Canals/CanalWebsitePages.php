<?php

namespace App\Services\Canals;

use App\Support\Url;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Stiahne verejnú stránku webu kanála pre CanalEmailFinder a CanalLogoFinder.
 *
 * Adresu zadal cudzí človek alebo import, takže každá zastávka presmerovania
 * sa kontroluje rovnako ako v sonde webov (Url::isSafeToProbe) — inak by sa
 * cez `Location: http://127.0.0.1/…` dal dotaz zaviesť do vnútornej siete.
 */
class CanalWebsitePages
{
    private const MAX_REDIRECTS = 4;

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
        $url = Url::normalize($url) ?? $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || ! Url::isSafeToProbe($url)) {
                return null;
            }

            try {
                $timeout = max(1, (int) config('canals.emails.discovery.timeout', 8));
                $response = Http::withUserAgent((string) config('attribute_checks.user_agent'))
                    ->withHeaders(['Accept' => $accept])
                    ->timeout($timeout)->connectTimeout($timeout)
                    ->withoutRedirecting()
                    ->get($url);
            } catch (Throwable) {
                return null;
            }

            if ($response->redirect()) {
                $url = $this->absolute($url, (string) $response->header('Location'));

                if ($url === null) {
                    return null;
                }

                continue;
            }

            return $response->successful() ? ['url' => $url, 'response' => $response] : null;
        }

        return null;
    }

    /** Odkaz zo stránky ako absolútna adresa. */
    public function absolute(string $base, string $href): ?string
    {
        $href = trim(html_entity_decode($href));

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return in_array(strtolower((string) parse_url($href, PHP_URL_SCHEME)), ['http', 'https'], true) ? Url::redirectTarget($href) : null;
        }

        $parts = parse_url($base);

        if (empty($parts['host'])) {
            return null;
        }

        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '//')) {
            return Url::redirectTarget(($parts['scheme'] ?? 'https').':'.$href);
        }

        if (! str_starts_with($href, '/')) {
            $dir = rtrim(preg_replace('#/[^/]*$#', '', $parts['path'] ?? ''), '/');
            $href = $dir.'/'.$href;
        }

        return Url::redirectTarget($origin.$href);
    }
}
