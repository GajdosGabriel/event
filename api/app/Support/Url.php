<?php

namespace App\Support;

/**
 * Jediné miesto, ktoré vie, ako sa u nás zaobchádza s adresou zadanou človekom.
 *
 * Používa ho cast (App\Casts\Website) pri ukladaní, validačné pravidlo
 * (App\Rules\WebsiteUrl) pri kontrole formulára aj sonda pri overovaní. Keby si
 * to každý riešil sám, formulár by prijal niečo iné, než čo sa uloží, a overila
 * by sa tretia vec.
 *
 * Do formulárov ľudia píšu adresy tak, ako ich vidia — `www.divadlo.sk`,
 * `Divadlo.SK/program `, občas aj s celým `https://`. Odmietnuť to ako
 * „neplatnú URL" je pohodlné pre program a nepríjemné pre človeka, preto sa
 * chýbajúca schéma dopĺňa a zvyšok normalizuje.
 */
final class Url
{
    /** Schémy, ktoré má zmysel ukladať ako web. Nič iné sa nepustí ďalej. */
    private const SCHEMES = ['http', 'https'];

    /**
     * Uvedie adresu do tvaru, v akom sa ukladá a overuje.
     *
     * @return string|null `null`, keď z hodnoty nezostane použiteľná adresa
     */
    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Doplní schému, ak chýba. `//example.sk` je platný „scheme-relative"
        // zápis, ale v databáze by z neho nikto nič nemal.
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');

        if (! in_array($scheme, self::SCHEMES, true)) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (! self::isPlausibleHost($host)) {
            return null;
        }

        // Prihlasovacie údaje v adrese (`https://user:pass@web.sk`) sú buď
        // omyl, alebo pokus o zamaskovanie cieľa — do verejného odkazu nepatria.
        $normalized = $scheme.'://'.$host;

        if (isset($parts['port']) && ! self::isDefaultPort($scheme, (int) $parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        // Cesta a query sa zachovávajú: veľa organizátorov nemá vlastný web,
        // len podstránku alebo profil (`facebook.com/nase-divadlo`). Orezať ich
        // na doménu by z odkazu urobilo niečo úplne iné.
        $path = rtrim($parts['path'] ?? '', '/');

        if ($path !== '' && $path !== '/') {
            $normalized .= $path;
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $normalized .= '?'.$parts['query'];
        }

        // Fragment (`#program`) je vec prehliadača, server ho nikdy nevidí —
        // v uloženej adrese je len šum.

        return $normalized;
    }

    /**
     * Cieľ presmerovania — tá istá bezpečnostná kontrola ako normalize(),
     * ale cesta sa nechá presne tak, ako ju poslal server.
     *
     * Rozdiel je jediný znak a stál 114 falošných hlásení o pokazenom webe:
     * normalize() zámerne orezáva lomku na konci cesty, lebo v uloženej
     * hodnote je `…/kurz` a `…/kurz/` tá istá adresa. Pri nasledovaní
     * presmerovania je to ale spor so serverom — WordPress (a s ním väčšina
     * webov, ktoré organizátori uvádzajú) presmeruje `…/kurz` na kanonické
     * `…/kurz/`, sonda si lomku zase odreže, server znova presmeruje, a po
     * piatich skokoch to celé skončí ako `redirect_loop` na úplne funkčnom
     * webe. Reťaz sa dá odkrokovať na hociktorej adrese z vyveska.sk.
     *
     * Čo z normalize() ostáva: povolená schéma, vierohodný host a zahodenie
     * prihlasovacích údajov z adresy. Práve to je dôvod, prečo cieľ
     * presmerovania cez túto kontrolu vôbec ide.
     *
     * @return string|null `null`, keď cieľ nie je použiteľná adresa
     */
    public static function redirectTarget(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, self::SCHEMES, true)) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (! self::isPlausibleHost($host)) {
            return null;
        }

        $target = $scheme.'://'.$host;

        if (isset($parts['port']) && ! self::isDefaultPort($scheme, (int) $parts['port'])) {
            $target .= ':'.$parts['port'];
        }

        // Cesta doslova, vrátane lomky na konci — o to tu celé ide.
        $target .= $parts['path'] ?? '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $target .= '?'.$parts['query'];
        }

        return $target;
    }

    public static function host(?string $url): ?string
    {
        $normalized = self::normalize($url);

        return $normalized === null ? null : (parse_url($normalized, PHP_URL_HOST) ?: null);
    }

    /**
     * Vyzerá host ako verejné doménové meno?
     *
     * Zámerne nekontroluje, či doména existuje — to je práca sondy, nie
     * formulára. Odmieta len to, čo verejnou adresou byť nemôže: hosty bez
     * bodky (`localhost`, `intranet`) a zjavné nezmysly.
     */
    public static function isPlausibleHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Adresa napísaná ako IP je pre verejný web taká výnimka, že je
            // pravdepodobnejšie omylom — a zároveň to je najkratšia cesta,
            // ako sondu poslať do vnútornej siete.
            return false;
        }

        if (! str_contains($host, '.') || str_starts_with($host, '.') || str_ends_with($host, '.')) {
            return false;
        }

        // IDN (`kúpele.sk`) sa pre kontrolu prevedie na ASCII podobu, ak je
        // rozšírenie `intl` k dispozícii. Vzor je aj tak unicode: bez `intl`
        // by inak diakritika v doméne padla na „neplatná adresa", hoci ide
        // o úplne bežnú slovenskú doménu.
        // Pomlčky vnútri návestia áno, na kraji nie. Dvojica uprostred je
        // v poriadku — punycode z prevodu IDN vyzerá práve tak (`xn--kpele-7ua`).
        return (bool) preg_match(
            '/^(?=.{1,253}$)([\p{L}\p{N}_]([\p{L}\p{N}_-]*[\p{L}\p{N}_])?\.)+(\p{L}{2,63}|xn--[\p{N}a-z-]{2,59})$/ui',
            self::toAscii($host),
        );
    }

    /** Host v ASCII podobe (punycode), ak to prostredie vie; inak nezmenený. */
    public static function toAscii(string $host): string
    {
        if (! function_exists('idn_to_ascii')) {
            return $host;
        }

        return idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
    }

    /** Dá sa host preložiť na adresu bez rozšírenia `intl`? */
    public static function isAsciiHost(string $host): bool
    {
        return (bool) preg_match('/^[\x20-\x7E]+$/', $host);
    }

    /**
     * Smie sa na túto adresu poslať dotaz zo servera?
     *
     * Overovanie znamená, že náš server ide na adresu, ktorú zadal cudzí
     * človek — teda presne situácia, v ktorej sa dá server prehovoriť, aby
     * siahol do vnútornej siete (SSRF). Preto musí byť host verejné doménové
     * meno a všetky IP, na ktoré sa preloží, musia byť verejné.
     */
    public static function isSafeToProbe(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! self::isPlausibleHost($host)) {
            return false;
        }

        $addresses = self::resolve($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                return false;
            }
        }

        return true;
    }

    /**
     * IP adresy hosta. Prázdne pole znamená, že doména neexistuje alebo DNS
     * neodpovedá — sonda to hlási ako nedostupnú doménu.
     *
     * @return array<int, string>
     */
    public static function resolve(string $host): array
    {
        $records = @dns_get_record(self::toAscii($host), DNS_A + DNS_AAAA) ?: [];

        $addresses = [];

        foreach ($records as $record) {
            $addresses[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($addresses));
    }

    public static function isPublicAddress(string $address): bool
    {
        return (bool) filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
    }
}
