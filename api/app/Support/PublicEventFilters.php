<?php

namespace App\Support;

use App\Models\Municipality;
use Illuminate\Http\Request;

/**
 * Filtre verejného výpisu podujatí z query.
 *
 * Používa ich zoznam, mapa aj bočné prehľady (obce, štítky) — počty vo filtri
 * sa tak rátajú z presne toho istého výberu, aký má návštevník pred sebou.
 */
final class PublicEventFilters
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        $list = $request->input('list');
        // `past` je archív — uplynulé podujatia od najnovšieho. Ich detaily
        // ostávajú verejné navždy (odkazy z Googlu a zo zdieľaní musia fungovať
        // aj o rok), takže potrebujú aj výpis, z ktorého sa na ne dá dostať.
        $list = in_array($list, ['upcoming', 'ongoing', 'all', 'past'], true) ? $list : 'upcoming';

        [$dateFrom, $dateTo] = self::range($request);
        [$latitude, $longitude, $radiusKm] = self::nearby($request);

        return [
            'municipality' => self::municipalityId($request),
            'search' => trim((string) $request->input('search', '')) ?: null,
            'list' => $list,
            'tags' => self::tagSlugs($request),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => $radiusKm,
        ];
    }

    /**
     * „V mojom okolí" — bod z prehliadača a okruh v kilometroch.
     *
     * Všetky tri hodnoty musia dávať zmysel spolu, inak sa filter ticho vypne
     * a vráti sa bežný výpis. Neplatná poloha nie je dôvod na chybu: prichádza
     * z `navigator.geolocation`, teda z prostredia, ktoré nemáme pod kontrolou,
     * a prázdny zoznam s hláškou 422 by vyzeral ako porucha portálu.
     *
     * Okruh je zhora obmedzený — nad 200 km už „okolie" nie je filter, ale celá
     * krajina, a databáze by zostalo len počítanie funkcie nad všetkým.
     *
     * @return array{0: ?float, 1: ?float, 2: ?float}
     */
    private static function nearby(Request $request): array
    {
        if (! $request->filled(['latitude', 'longitude', 'radius_km'])) {
            return [null, null, null];
        }

        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        $radiusKm = (float) $request->input('radius_km');

        $valid = $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180
            && $radiusKm > 0;

        if (! $valid) {
            return [null, null, null];
        }

        return [$latitude, $longitude, min($radiusKm, 200.0)];
    }

    /**
     * Obec chodí ako id (dashboardové filtre) alebo ako slug (landing stránka
     * `/podujatia/mesto/{slug}`). Neznámy slug zámerne nekončí 422, ale
     * prázdnym výsledkom — rovnako ako pri štítkoch je to pre zastaralý odkaz
     * správnejšie správanie než chyba.
     */
    private static function municipalityId(Request $request): ?int
    {
        $raw = trim((string) $request->input('municipality', ''));

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        return Municipality::query()->where('slug', $raw)->value('id');
    }

    /**
     * Pomenované časové okno: `weekend` (landing stránka), `today`
     * a `week` (rýchle voľby vo výpise a na mape). Výpočet drží
     * [EventTimeframe], aby SPA aj bot-render vrstva ukazovali ten istý zoznam.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function range(Request $request): array
    {
        $today = now('Europe/Bratislava')->startOfDay();

        $window = match ($request->input('range')) {
            'weekend' => EventTimeframe::thisWeekend(),
            'today' => [$today, $today->copy()->endOfDay()],
            'week' => [$today, $today->copy()->addDays(6)->endOfDay()],
            default => null,
        };

        if ($window === null) {
            return [null, null];
        }

        // Termíny sú v databáze v UTC, ale deň návštevníka je slovenský.
        // Samotný dátum by zahrnul aj začiatok nasledujúceho lokálneho dňa.
        return [$window[0]->copy()->utc()->toDateTimeString(), $window[1]->copy()->utc()->toDateTimeString()];
    }

    /**
     * Štítky chodia ako ?tags=koncert,folklor. Slugy sa nevalidujú proti
     * číselníku — neznámy slug jednoducho nič nenájde, čo je pre filter
     * správnejšie než 422 pri zastaralom odkaze.
     *
     * @return array<int, string>|null
     */
    private static function tagSlugs(Request $request): ?array
    {
        $raw = $request->input('tags');
        $raw = is_array($raw) ? $raw : explode(',', (string) $raw);

        $slugs = array_values(array_filter(
            array_map(static fn ($slug) => trim((string) $slug), $raw),
            static fn (string $slug) => $slug !== '',
        ));

        return $slugs !== [] ? array_slice(array_unique($slugs), 0, 10) : null;
    }
}
