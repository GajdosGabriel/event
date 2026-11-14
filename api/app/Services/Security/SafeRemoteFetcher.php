<?php

namespace App\Services\Security;

use App\Support\Url;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * GET na adresu, ktorú zadal cudzí človek alebo priniesol import.
 *
 * Každá zastávka presmerovania sa kontroluje rovnako ako v sonde webov
 * (Url::isSafeToProbe) — inak by sa cez `Location: http://127.0.0.1/…` dal
 * dotaz zaviesť do vnútornej siete. Preto presmerovania nenasleduje HTTP
 * klient sám, ale táto slučka krok po kroku.
 *
 * Zostatkové riziko: medzi kontrolou DNS a samotným spojením sa záznam môže
 * zmeniť (DNS rebinding). Na to by bolo treba pripnúť IP cez CURLOPT_RESOLVE.
 */
class SafeRemoteFetcher
{
    public const MAX_REDIRECTS = 4;

    /**
     * @param  int|null  $maxBytes  telo nad limit sa preruší už pri sťahovaní
     * @return array{url: string, response: Response} posledná (nepresmerovacia) odpoveď, aj neúspešná
     *
     * @throws RemoteFetchRefused cieľ mimo verejného webu, priveľa presmerovaní, priveľké telo
     * @throws Throwable chyba spojenia
     */
    public function get(
        string $url,
        string $accept = '*/*',
        ?string $userAgent = null,
        int $timeout = 10,
        ?int $connectTimeout = null,
        ?int $maxBytes = null,
    ): array {
        $url = Url::normalize($url) ?? $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || ! Url::isSafeToProbe($url)) {
                throw new RemoteFetchRefused('Remote address is not a public web address: '.$url);
            }

            $tooLarge = false;
            $request = Http::withUserAgent($userAgent ?? (string) config('attribute_checks.user_agent'))
                ->withHeaders(['Accept' => $accept])
                ->timeout($timeout)
                ->connectTimeout($connectTimeout ?? $timeout)
                ->withoutRedirecting();

            if ($maxBytes !== null) {
                // Hlavička môže klamať alebo chýbať — preto aj priebežná
                // kontrola stiahnutých bajtov, nielen Content-Length.
                $request = $request->withOptions([
                    'on_headers' => function ($response) use ($maxBytes, &$tooLarge) {
                        if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                            $tooLarge = true;
                            throw new RemoteFetchRefused('Remote file is too large');
                        }
                    },
                    'progress' => function ($downloadTotal, $downloaded) use ($maxBytes, &$tooLarge) {
                        if ($downloaded > $maxBytes) {
                            $tooLarge = true;
                            throw new RemoteFetchRefused('Remote file is too large');
                        }
                    },
                ]);
            }

            try {
                $response = $request->get($url);
            } catch (Throwable $e) {
                if ($tooLarge) {
                    throw new RemoteFetchRefused('Remote file is too large: '.$url);
                }

                throw $e;
            }

            if ($response->redirect()) {
                $next = self::absolute($url, (string) $response->header('Location'));

                if ($next === null) {
                    throw new RemoteFetchRefused('Invalid redirect target from: '.$url);
                }

                $url = $next;

                continue;
            }

            if ($maxBytes !== null && strlen($response->body()) > $maxBytes) {
                throw new RemoteFetchRefused('Remote file is too large: '.$url);
            }

            return ['url' => $url, 'response' => $response];
        }

        throw new RemoteFetchRefused('Too many redirects: '.$url);
    }

    /** Odkaz (href, Location) voči základnej adrese ako absolútna http(s) adresa. */
    public static function absolute(string $base, string $href): ?string
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
