<?php

namespace App\Services\Attributes\Probes;

use App\Contracts\AttributeProbe;
use App\Models\AttributeCheck;
use App\Services\Attributes\ProbeResult;
use App\Support\Url;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Overí, či zadaná webová adresa niekomu odpovedá.
 *
 * Čo sa považuje za funkčný web — a prečo tak zhovievavo:
 *
 *   • 2xx/3xx  → v poriadku;
 *   • 401, 403, 405, 429 → tiež v poriadku. Server odpovedal, teda žije;
 *     len sa mu nepáči robot. Označiť takú stránku za nefunkčnú a napísať
 *     majiteľovi, že má pokazený web, by bola nezmyselná otrava — a stalo by
 *     sa to pri každom väčšom webe za Cloudflare;
 *   • 404, 410 → chyba. Doména žije, ale konkrétna stránka nie — presne to,
 *     čo majiteľ chce vedieť (typicky presunutá podstránka s programom);
 *   • 5xx → chyba, ale zvyčajne dočasná. Preto sa upozorňuje až po opakovaní;
 *   • DNS/spojenie/timeout → chyba.
 *
 * Skúša sa najprv HEAD (nesťahuje obsah), a keď ho server nezvláda, GET.
 * Presmerovania sa prechádzajú ručne, aby sa dala každá zastávka skontrolovať
 * — inak by sa cez `Location: http://127.0.0.1/...` dal dotaz zaviesť do
 * vnútornej siete napriek kontrole pôvodnej adresy.
 */
class WebsiteProbe implements AttributeProbe
{
    /** Odpovede, ktoré znamenajú „žije, len nie pre robota". */
    private const TOLERATED = [401, 403, 405, 406, 429];

    /** Odpovede, ktoré znamenajú „táto stránka už neexistuje". */
    private const MISSING = [404, 410];

    private const MAX_REDIRECTS = 5;

    public function attribute(): string
    {
        return AttributeCheck::WEBSITE;
    }

    public function probe(string $value): ProbeResult
    {
        $url = Url::normalize($value);

        if ($url === null) {
            return ProbeResult::failed('invalid');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);

        // Doména s diakritikou sa musí previesť na punycode, inak ju DNS nenájde.
        // Bez rozšírenia `intl` to nevieme — a mlčať je jediná čestná možnosť,
        // lebo „nenašli sme" by v tomto prípade hovorilo o nás, nie o webe.
        if (! Url::isAsciiHost($host) && Url::toAscii($host) === $host) {
            return ProbeResult::skipped('idn_unsupported');
        }

        return $this->follow($url, self::MAX_REDIRECTS);
    }

    private function follow(string $url, int $hopsLeft): ProbeResult
    {
        if (! Url::isSafeToProbe($url)) {
            // Rozlíšenie je dôležité: pri prvej adrese ide takmer vždy
            // o neexistujúcu doménu, čo je pre majiteľa zrozumiteľná správa.
            return ProbeResult::failed(
                Url::resolve((string) parse_url($url, PHP_URL_HOST)) === [] ? 'dns' : 'blocked',
            );
        }

        try {
            $response = $this->request($url, 'head');

            // Časť serverov HEAD nepodporuje alebo ho odbaví chybou. Skúsi sa
            // teda ešte GET — až jeho výsledok rozhoduje.
            if ($response->status() >= 400) {
                $response = $this->request($url, 'get');
            }
        } catch (ConnectionException $e) {
            return ProbeResult::failed($this->connectionReason($e));
        } catch (\Throwable) {
            return ProbeResult::failed('unreachable');
        }

        $status = $response->status();

        if ($response->redirect()) {
            $location = $this->absoluteLocation($url, (string) $response->header('Location'));

            if ($location === null) {
                return ProbeResult::failed('redirect', $status);
            }

            if ($hopsLeft <= 0) {
                return ProbeResult::failed('redirect_loop', $status);
            }

            return $this->follow($location, $hopsLeft - 1);
        }

        if ($status < 400 || in_array($status, self::TOLERATED, true)) {
            return ProbeResult::ok($status);
        }

        if (in_array($status, self::MISSING, true)) {
            return ProbeResult::failed('not_found', $status);
        }

        return ProbeResult::failed($status >= 500 ? 'server_error' : 'http_error', $status);
    }

    private function request(string $url, string $method): Response
    {
        return Http::withUserAgent((string) config('attribute_checks.user_agent'))
            ->withHeaders(['Accept' => '*/*'])
            ->timeout(max(1, (int) config('attribute_checks.timeout', 8)))
            ->connectTimeout(max(1, (int) config('attribute_checks.timeout', 8)))
            // Presmerovania si vedie sonda sama (viď follow()).
            ->withoutRedirecting()
            ->{$method}($url);
    }

    /** Cieľ presmerovania ako absolútna adresa; null, keď sa nedá použiť. */
    private function absoluteLocation(string $from, string $location): ?string
    {
        $location = trim($location);

        if ($location === '') {
            return null;
        }

        // Relatívne presmerovanie (`/sk/program`) je bežné a platné.
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            $base = parse_url($from);

            if (empty($base['host'])) {
                return null;
            }

            $location = ($base['scheme'] ?? 'https').'://'.$base['host']
                .(isset($base['port']) ? ':'.$base['port'] : '')
                .'/'.ltrim($location, '/');
        }

        // redirectTarget(), nie normalize(): cesta musí ostať presne taká,
        // akú poslal server. Viď Url::redirectTarget().
        return Url::redirectTarget($location);
    }

    private function connectionReason(ConnectionException $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'timeout',
            str_contains($message, 'could not resolve'), str_contains($message, 'name or service not known') => 'dns',
            str_contains($message, 'ssl'), str_contains($message, 'certificate') => 'ssl',
            default => 'unreachable',
        };
    }
}
