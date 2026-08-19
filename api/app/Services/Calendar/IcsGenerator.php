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

    /**
     * Predvolený predstih pripomienky v kalendári. Dve hodiny sú kompromis:
     * dosť na to, aby sa dalo vyraziť, a ešte nie tak skoro, aby sa na ňu
     * do začiatku stihlo zabudnúť.
     */
    private const ALARM_DEFAULT_HOURS = 2;

    /**
     * Celodenné podujatie má DTSTART o polnoci, takže dvojhodinový predstih by
     * zazvonil o desiatej večer predtým. Šesť hodín z toho spraví 18:00
     * predošlého dňa — čas, keď sa plánuje ďalší deň.
     */
    private const ALARM_ALL_DAY_HOURS = 6;

    /** RFC 5545: riadok má najviac 75 oktetov, zvyšok pokračuje po medzere. */
    private const LINE_OCTETS = 74;

    /**
     * Termíny sú v DB v UTC (config app.timezone), ale „je to celý deň?" je
     * otázka o miestnom čase: celodenné podujatie 15. 8. sa v lete uloží ako
     * 14. 8. 22:00 → 16. 8. 21:59 UTC. Rovnaká konštanta ako v
     * App\Services\Tags\EventAttributeDeriver.
     */
    private const DISPLAY_TIMEZONE = 'Europe/Bratislava';

    /**
     * Vracia null, keď podujatie nemá začiatok — bez termínu nie je čo
     * zapisovať do kalendára a volajúci má tak jednoduchý test, či odkaz
     * vôbec ponúknuť.
     */
    public function forEvent(Event $event): ?string
    {
        $window = $this->window($event);

        if ($window === null) {
            return null;
        }

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
            ...$this->dateLines($window),
            // SEQUENCE rastie s každou úpravou termínu — kalendár tak vie, že
            // novší súbor má prepísať starší záznam s rovnakým UID.
            'SEQUENCE:'.$this->sequence($event),
            'STATUS:CONFIRMED',
            // Celodenné podujatie nemá človeku zablokovať celý deň ako „zaneprázdnený" —
            // pri bežnom termíne s časom je blokovanie naopak správne.
            'TRANSP:'.($window['all_day'] ? 'TRANSPARENT' : 'OPAQUE'),
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

        array_push($lines, ...$this->alarmLines($event, $window));

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // CRLF je v RFC 5545 povinné — Outlook so samotným \n súbor odmietne.
        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * Pripomienka priamo v súbore. Bez nej je „Pridať do kalendára" iba zápis
     * termínu — človek ho v kalendári má, ale nikto mu nič nepovie.
     *
     * VALARM pripomenie jeho vlastný kalendár, takže od návštevníka
     * nepotrebujeme e-mail ani žiadny iný údaj a funguje to aj offline. Je to
     * jediná pripomienka, ktorú nemusíme doručiť my.
     *
     * @param  array{start: CarbonInterface, end: CarbonInterface, all_day: bool}  $window
     * @return array<int, string>
     */
    private function alarmLines(Event $event, array $window): array
    {
        $hours = $this->alarmLeadHours($event, $window);

        if ($hours <= 0) {
            return [];
        }

        return [
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            // RELATED=START je síce default, ale píšeme ho naplno: časť klientov
            // pri jeho absencii vyhodnotí trigger voči DTEND a pri viacdňovom
            // podujatí by pripomienka prišla až po jeho konci.
            'TRIGGER;RELATED=START:-PT'.$hours.'H',
            // DESCRIPTION je pri ACTION:DISPLAY povinné a je to text, ktorý
            // človek uvidí v notifikácii — teda názov podujatia, nie popis.
            'DESCRIPTION:'.$this->escape((string) $event->name),
            'END:VALARM',
        ];
    }

    /**
     * Koľko hodín pred začiatkom pripomenúť. Organizátorov
     * `reminder_hours_before` má prednosť — je to ten istý úmysel („kedy má
     * zmysel ozvať sa") a dve nezávislé čísla pre e-mail a pre kalendár by sa
     * časom rozišli.
     *
     * @param  array{start: CarbonInterface, end: CarbonInterface, all_day: bool}  $window
     */
    private function alarmLeadHours(Event $event, array $window): int
    {
        $configured = $event->reminder_hours_before;

        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        return $window['all_day'] ? self::ALARM_ALL_DAY_HOURS : self::ALARM_DEFAULT_HOURS;
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
        $window = $this->window($event);

        if ($window === null) {
            return null;
        }

        $params = [
            'action' => 'TEMPLATE',
            'text' => (string) $event->name,
            'dates' => $window['all_day']
                ? $this->localDay($window['start'])->format('Ymd').'/'.$this->dayAfter($window['end'])->format('Ymd')
                : $this->stamp($window['start']).'/'.$this->stamp($window['end']),
            'details' => $this->description($event, PublicUrl::event($event)),
            'location' => $this->location($event) ?? '',
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter($params));
    }

    /**
     * Odkaz do webového Outlooku. Rovnaký dôvod ako pri Googli: kto má kalendár
     * v prehliadači, so stiahnutým `.ics` nespraví nič. Desktopový Outlook aj
     * Office 365 zostávajú na súbore.
     */
    public function outlookUrl(Event $event): ?string
    {
        $window = $this->window($event);

        if ($window === null) {
            return null;
        }

        $params = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => (string) $event->name,
            'body' => $this->description($event, PublicUrl::event($event)),
            'location' => $this->location($event) ?? '',
        ];

        if ($window['all_day']) {
            $params['allday'] = 'true';
            $params['startdt'] = $this->localDay($window['start'])->format('Y-m-d');
            $params['enddt'] = $this->dayAfter($window['end'])->format('Y-m-d');
        } else {
            $params['startdt'] = $window['start']->format('Y-m-d\TH:i:s\Z');
            $params['enddt'] = $window['end']->format('Y-m-d\TH:i:s\Z');
        }

        return 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query(array_filter($params));
    }

    /** Verejná adresa `.ics` súboru — rovnaká pre e-mail aj pre web. */
    public function downloadUrl(Event $event): string
    {
        return route('public.events.calendar', ['id' => $event->id]);
    }

    /**
     * Všetky cesty do kalendára naraz — pre verejný detail podujatia aj pre
     * e-mail. Bez termínu je to null a volajúci sekciu vynechá.
     *
     * @return array{download: string, google: string, outlook: string}|null
     */
    public function links(Event $event): ?array
    {
        if ($this->window($event) === null) {
            return null;
        }

        return [
            'download' => $this->downloadUrl($event),
            'google' => (string) $this->googleUrl($event),
            'outlook' => (string) $this->outlookUrl($event),
        ];
    }

    /**
     * Termín podujatia tak, ako ho uvidí kalendár. Podujatie bez konca dostane
     * dvojhodinové okno, podujatie bez začiatku sa do kalendára nedá zapísať
     * vôbec (null).
     *
     * @return array{start: CarbonInterface, end: CarbonInterface, all_day: bool}|null
     */
    private function window(Event $event): ?array
    {
        if (! $event->start_at instanceof CarbonInterface) {
            return null;
        }

        $start = $event->start_at->copy()->utc();
        $end = $event->end_at instanceof CarbonInterface && $event->end_at->greaterThan($event->start_at)
            ? $event->end_at->copy()->utc()
            : $start->copy()->addHours(self::DEFAULT_DURATION_HOURS);

        return ['start' => $start, 'end' => $end, 'all_day' => $this->isAllDay($event)];
    }

    /**
     * Celodenné podujatie sa v editore aj v AI extrakcii ukladá ako 00:00–23:59
     * miestneho času (viď PromptData). Do kalendára patrí ako dátumový záznam:
     * s časom by z neho bol dvojdňový blok cez celý deň namiesto čistej
     * celodennej udalosti.
     */
    private function isAllDay(Event $event): bool
    {
        if (! $event->start_at instanceof CarbonInterface || ! $event->end_at instanceof CarbonInterface) {
            return false;
        }

        return $this->localDay($event->start_at)->format('H:i') === '00:00'
            && $this->localDay($event->end_at)->format('H:i') === '23:59';
    }

    /**
     * DTSTART/DTEND. Celodenné podujatie ide ako VALUE=DATE — koniec je v RFC
     * 5545 (aj v Google odkaze) exkluzívny, teda deň po poslednom dni.
     *
     * @param  array{start: CarbonInterface, end: CarbonInterface, all_day: bool}  $window
     * @return array<int, string>
     */
    private function dateLines(array $window): array
    {
        if ($window['all_day']) {
            return [
                'DTSTART;VALUE=DATE:'.$this->localDay($window['start'])->format('Ymd'),
                'DTEND;VALUE=DATE:'.$this->dayAfter($window['end'])->format('Ymd'),
            ];
        }

        return [
            'DTSTART:'.$this->stamp($window['start']),
            'DTEND:'.$this->stamp($window['end']),
        ];
    }

    /** Ten istý okamih v miestnom čase — na dátumové (celodenné) záznamy. */
    private function localDay(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->tz(self::DISPLAY_TIMEZONE);
    }

    private function dayAfter(CarbonInterface $date): CarbonInterface
    {
        return $this->localDay($date)->addDay();
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
