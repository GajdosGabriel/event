<?php

namespace App\Services\Seo;

use App\Enums\TicketTypeKind;
use App\Models\Canal;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Venue;
use App\Support\PublicUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Skladá schema.org štruktúrované dáta pre bot-render vrstvu.
 *
 * Zámerne vracia polia, nie hotový JSON — Blade si ich zakóduje sám a testy
 * vedia tvrdenia písať nad štruktúrou namiesto nad reťazcom.
 *
 * Prečo na tom záleží: bez `Event` s `startDate`, `location` a `offers`
 * podujatie nemá ako padnúť do Google Events ani do „čo robiť v okolí".
 */
class JsonLd
{
    /**
     * @return array<string, mixed>
     */
    public function event(Event $event): array
    {
        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->name,
            'url' => PublicUrl::event($event),
            'description' => $this->plainText($event->body ?? $event->body_ai, 500),
            'startDate' => $this->iso($event->start_at),
            'endDate' => $this->iso($event->end_at),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'image' => $this->images($event),
            'location' => $this->location($event),
            'organizer' => $this->organizer($event),
            'offers' => $this->offers($event),
        ], fn ($value) => $value !== null && $value !== []);

        return $data;
    }

    /**
     * Výpis podujatí ako `ItemList`. Vyhľadávač z neho vyčíta, že landing
     * stránka je zoznam konkrétnych podujatí, nie ďalšia podstránka portálu.
     *
     * @param  Collection<int, Event>  $events
     * @return array<string, mixed>
     */
    public function eventList(Collection $events, string $url, string $name): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'url' => $url,
            'numberOfItems' => $events->count(),
            'itemListElement' => $events->values()
                ->map(fn (Event $event, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => PublicUrl::event($event),
                    'name' => $event->name,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function venue(Venue $venue): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Place',
            'name' => $venue->name,
            'url' => PublicUrl::venue($venue),
            'description' => $this->plainText($venue->body, 500),
            'image' => $this->images($venue),
            'telephone' => $venue->phone ?: null,
            'address' => $this->postalAddress($venue),
            'geo' => $this->geo($venue->latitude, $venue->longitude),
            'sameAs' => $venue->website ? [$venue->website] : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function canal(Canal $canal): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $canal->name,
            'url' => PublicUrl::canal($canal),
            'description' => $this->plainText($canal->body, 500),
            'image' => $this->images($canal),
            'sameAs' => $canal->website ? [$canal->website] : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * Omrvinky dvíhajú CTR vo výsledkoch a dávajú crawlerovi cestu späť na
     * nadradený výpis.
     *
     * @param  array<int, array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                fn (array $crumb, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ],
                $crumbs,
                array_keys($crumbs),
            ),
        ];
    }

    /**
     * Cena je v `offers`, lebo bez nej Google Events ponuku nezobrazí. Zdroj je
     * typ lístka; keď ticketing nie je zapnutý, spadne sa na `price_amount`
     * na podujatí (importované a bezplatné podujatia).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function offers(Event $event): ?array
    {
        $types = $event->relationLoaded('ticketTypes')
            ? $event->getRelation('ticketTypes')
            : collect();

        $offers = $types
            // Workshop je doplnok k hlavnej vstupenke, nie samostatná ponuka —
            // v `offers` by predstieral druhú cenu za to isté podujatie.
            ->filter(fn (TicketType $type) => $type->is_active && $type->kind !== TicketTypeKind::Workshop)
            ->map(fn (TicketType $type) => array_filter([
                '@type' => 'Offer',
                'name' => $type->name,
                'url' => PublicUrl::event($event),
                'price' => $this->price($type->price_amount),
                'priceCurrency' => $type->price_currency ?: $event->price_currency ?: 'EUR',
                'availability' => $this->availability($type),
                'validFrom' => $this->iso($type->sale_starts_at),
                'validThrough' => $this->iso($type->sale_ends_at),
            ], fn ($value) => $value !== null))
            ->values();

        if ($offers->isNotEmpty()) {
            return $offers->all();
        }

        if ($event->price_amount === null) {
            return null;
        }

        return [array_filter([
            '@type' => 'Offer',
            'url' => PublicUrl::event($event),
            'price' => $this->price($event->price_amount),
            'priceCurrency' => $event->price_currency ?: 'EUR',
            'availability' => 'https://schema.org/InStock',
        ], fn ($value) => $value !== null)];
    }

    private function availability(TicketType $type): string
    {
        if (! $type->on_sale) {
            return 'https://schema.org/OutOfStock';
        }

        $remaining = $type->remaining_capacity;

        if ($remaining === null) {
            return 'https://schema.org/InStock';
        }

        return $remaining > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/SoldOut';
    }

    /**
     * Ceny sú v DB v centoch (integer), schema.org ich chce v jednotkách meny.
     */
    private function price(?int $amount): ?string
    {
        return $amount === null ? null : number_format($amount / 100, 2, '.', '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function location(Event $event): ?array
    {
        $venue = $event->venue;

        if ($venue) {
            $place = $this->venue($venue);
            unset($place['@context'], $place['description'], $place['image']);

            return $place;
        }

        // Podujatie vlastnú adresu nemá — miesto konania drží výhradne `venue`
        // (stĺpce street/latitude na `events` neexistujú). Bez miesta ostáva
        // `location` nevyplnené; Google taký Event nezaradí do „v okolí", ale
        // vymýšľať mu súradnice by bolo horšie.
        $municipality = $event->municipality;

        if (! $municipality) {
            return null;
        }

        return [
            '@type' => 'Place',
            'name' => $municipality->shortname,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $municipality->shortname,
                'addressCountry' => 'SK',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function organizer(Event $event): ?array
    {
        $canal = $event->canal;

        if (! $canal) {
            return null;
        }

        return array_filter([
            '@type' => 'Organization',
            'name' => $canal->name,
            'url' => PublicUrl::canal($canal),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function postalAddress(object $model): ?array
    {
        $municipality = $model->municipality ?? null;

        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $model->street ?: null,
            'postalCode' => $model->postcode ?: null,
            'addressLocality' => $municipality?->shortname ?: null,
            'addressCountry' => 'SK',
        ], fn ($value) => $value !== null);

        return count($address) > 2 ? $address : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function geo(mixed $latitude, mixed $longitude): ?array
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function images(object $model): ?array
    {
        if (! $model->has_primary_image) {
            return null;
        }

        $image = $model->primary_image;

        return array_values(array_unique(array_filter([
            $image['large'] ?? null,
            $image['original'] ?? null,
        ])));
    }

    /**
     * Zámerne bez časovej zóny.
     *
     * `APP_TIMEZONE` je UTC, ale časy podujatí sa ukladajú tak, ako ich zadal
     * organizátor, a aj `date_range_label` ich takto vypisuje — sú to teda
     * naivné lokálne časy. `toIso8601String()` by k nim prilepil `+00:00`
     * a Google by 19:00 zobrazil ako 21:00. Schema.org tvar bez offsetu
     * povoľuje a chápe ho ako miestny čas podujatia, čo je presne to, čo o ňom
     * naozaj vieme.
     */
    private function iso(mixed $date): ?string
    {
        return $date instanceof CarbonInterface ? $date->format('Y-m-d\TH:i:s') : null;
    }

    private function plainText(?string $html, int $limit): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');

        if ($text === '') {
            return null;
        }

        return mb_strimwidth($text, 0, $limit, '…');
    }
}
