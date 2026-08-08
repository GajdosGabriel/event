<?php

namespace App\Services\Calendar;

use App\Models\Event;
use App\Support\PublicUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Podujatie ako záznam do kalendára (iCalendar, RFC 5545).
 *
 * Slúži dvom veciam naraz: príloha `.ics` v e-maile (Gmail aj Apple Mail nad
 * ňou samy ponúknu „Pridať do kalendára") a stiahnuteľný súbor na verejnej
 * route (public.events.calendar) pre klientov, ktorí prílohu neponúknu.
 *
 * METHOD:PUBLISH, nie REQUEST — nejde o pozvánku s odpoveďou. REQUEST by z
 * e-mailu spravil meeting invite a účastníci by organizátorovi posielali RSVP
 * do schránky, o ktoré nikto nežiadal.
 *
 * Časy sú v UTC (`Z`): termíny sú tak uložené aj v DB (config app.timezone),
 * takže netreba do súboru pribaľovať VTIMEZONE ani riešiť prechod na letný čas.
 */
final class IcsGenerator
{
    /** Podujatie bez konca — do kalendára ho dáme ako dvojhodinové. */
    private const DEFAULT_DURATION_HOURS = 2;

    /** RFC 5545: riadok má najviac 75 oktetov, zvyšok pokračuje po medzere. */
    private const LINE_OCTETS = 74;

    /**
     * Vracia null, keď podujatie nemá začiatok — bez termínu nie je čo
     * zapisovať do kalendára a volajúci má tak jednoduchý test, či odkaz
     * vôbec ponúknuť.
     */
    public function forEvent(Event $event): ?string
    {
        if (! $event->start_at instanceof CarbonInterface) {
            return null;
        }

        $start = $event->start_at->copy()->utc();
        $end = $event->end_at instanceof CarbonInterface && $event->end_at->greaterThan($event->start_at)
            ? $event->end_at->copy()->utc()
            : $start->copy()->addHours(self::DEFAULT_DURATION_HOURS);

        $url = PublicUrl::event($event);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->productName().'//Podujatia//SK',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$this->uid($event),
            'DTSTAMP:'.$this->stamp(now()->utc()),
            'DTSTART:'.$this->stamp($start),
            'DTEND:'.$this->stamp($end),
            // SEQUENCE rastie s každou úpravou termínu — kalendár tak vie, že
            // novší súbor má prepísať starší záznam s rovnakým UID.
            'SEQUENCE:'.$this->sequence($event),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'SUMMARY:'.$this->escape((string) $event->name),
            'DESCRIPTION:'.$this->escape($this->description($event, $url)),
            'URL:'.$this->escape($url),
        ];

        if ($location = $this->location($event)) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        if ($geo = $this->geo($event)) {
            $lines[] = 'GEO:'.$geo;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // CRLF je v RFC 5545 povinné — Outlook so samotným \n súbor odmietne.
        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /** Názov prílohy aj sťahovaného súboru. */
    public function filename(Event $event): string
    {
        $slug = trim((string) ($event->slug ?: Str::slug((string) $event->name)), '-');

        return ($slug !== '' ? $slug : 'podujatie').'.ics';
    }

    /**
     * Odkaz „Pridať do Google Kalendára". Pre klientov, ktorí prílohu ani
     * stiahnutý súbor nespracujú (typicky Gmail v mobilnom prehliadači).
     */
    public function googleUrl(Event $event): ?string
    {
        if (! $event->start_at instanceof CarbonInterface) {
            return null;
        }

        $start = $event->start_at->copy()->utc();
        $end = $event->end_at instanceof CarbonInterface && $event->end_at->greaterThan($event->start_at)
            ? $event->end_at->copy()->utc()
            : $start->copy()->addHours(self::DEFAULT_DURATION_HOURS);

        $params = [
            'action' => 'TEMPLATE',
            'text' => (string) $event->name,
            'dates' => $this->stamp($start).'/'.$this->stamp($end),
            'details' => $this->description($event, PublicUrl::event($event)),
            'location' => $this->location($event) ?? '',
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter($params));
    }

    /** Verejná adresa `.ics` súboru — rovnaká pre e-mail aj pre web. */
    public function downloadUrl(Event $event): string
    {
        return route('public.events.calendar', ['id' => $event->id]);
    }

    /**
     * UID musí byť stabilné naprieč e-mailmi o tom istom podujatí — inak si
     * účastník po každej notifikácii založí do kalendára ďalšiu kópiu.
     */
    private function uid(Event $event): string
    {
        $host = parse_url(PublicUrl::base(), PHP_URL_HOST) ?: 'localhost';

        return 'event-'.$event->id.'@'.$host;
    }

    private function sequence(Event $event): int
    {
        return $event->updated_at instanceof CarbonInterface
            ? $event->updated_at->getTimestamp()
            : 0;
    }

    private function stamp(CarbonInterface $date): string
    {
        return $date->format('Ymd\THis\Z');
    }

    /**
     * Popis držíme krátky: HTML tela podujatia sa do kalendára nezmestí čitateľne
     * a väčšina klientov ho aj tak vykreslí ako holý text.
     */
    private function description(Event $event, string $url): string
    {
        $body = trim(html_entity_decode(strip_tags((string) ($event->body ?: $event->body_ai)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $body = trim((string) preg_replace('/\s+/u', ' ', $body));

        $parts = array_filter([
            $body !== '' ? Str::limit($body, 300) : null,
            $url,
        ]);

        return implode("\n\n", $parts);
    }

    private function location(Event $event): ?string
    {
        $venue = $event->venue;

        if ($venue === null) {
            return null;
        }

        $municipality = $event->municipality;

        $parts = [
            $venue->name,
            $venue->street,
            trim(implode(' ', array_filter([$venue->postcode, $municipality?->shortname]))),
        ];

        // Miesto sa často volá rovnako ako obec („Celé Slovensko, Celé
        // Slovensko"). Rovnaký údaj dvakrát v adrese len mätie.
        $unique = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part === '') {
                continue;
            }

            foreach ($unique as $seen) {
                if (mb_stripos($seen, $part) !== false || mb_stripos($part, $seen) !== false) {
                    continue 2;
                }
            }

            $unique[] = $part;
        }

        return $unique !== [] ? implode(', ', $unique) : null;
    }

    private function geo(Event $event): ?string
    {
        $venue = $event->venue;

        if ($venue === null || $venue->latitude === null || $venue->longitude === null) {
            return null;
        }

        return sprintf('%.6F;%.6F', (float) $venue->latitude, (float) $venue->longitude);
    }

    /** RFC 5545 escapovanie hodnôt: spätná lomka, bodkočiarka, čiarka, nový riadok. */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value,
        );
    }

    /**
     * Zalomenie dlhého riadku. Počíta sa v oktetoch, preto delíme po bajtoch a
     * nie po znakoch — diakritika v názve podujatia je viacbajtová a zlom v
     * strede znaku by súbor rozbil.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_OCTETS) {
            return $line;
        }

        $chunks = [];
        $current = '';

        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > self::LINE_OCTETS) {
                $chunks[] = $current;
                $current = '';
            }

            $current .= $char;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return implode("\r\n ", $chunks);
    }

    private function productName(): string
    {
        return str_replace([';', ','], '', (string) config('app.name', 'Event'));
    }
}
