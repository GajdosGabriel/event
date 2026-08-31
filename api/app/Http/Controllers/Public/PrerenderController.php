<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Question;
use App\Models\Tag;
use App\Models\Venue;
use App\Services\Imports\HtmlBodyCleaner;
use App\Services\Seo\JsonLd;
use App\Support\EventTimeframe;
use App\Support\PublicUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Bot-render vrstva.
 *
 * SPA sa renderuje až v prehliadači, takže Facebook, Messenger, WhatsApp,
 * LinkedIn ani vyhľadávače z nej nevidia nič — každé zdieľanie podujatia
 * vyzeralo ako holý odkaz. Táto routa vráti tú istú stránku ako serverom
 * vykreslené HTML: plnú `<head>` s OG/Twitter, JSON-LD a čitateľné telo.
 *
 * Apache sem podľa `User-Agent` presmeruje crawlerov, ľudia idú do SPA
 * (pravidlá a postup nasadenia: `deploy/htaccess.md` v koreni repozitára).
 * Cesta chodí v `?path=`, aby routa nemusela zrkadliť router SPA.
 *
 * `canonical` je vždy slugová adresa — crawler smie doraziť aj na starú
 * `/events/42`, indexovať sa má nová.
 */
class PrerenderController extends Controller
{
    /** Koľko podujatí ukáže výpis. Crawler potrebuje odkazy, nie stránkovanie. */
    private const LIST_LIMIT = 60;

    /**
     * Koľko uplynulých podujatí visí na profile miesta/organizátora. Je to
     * cesta crawlera k archívu, nie samotný archív — ten má vlastnú adresu.
     */
    private const PAST_ON_PROFILE = 20;

    /**
     * Koľko zodpovedaných otázok ide do `FAQPage`. Google z nich aj tak
     * zobrazí len hŕstku a stovka otázok by z tela stránky spravila archív,
     * v ktorom sa stratí samotné podujatie.
     */
    private const MAX_FAQ_ENTRIES = 20;

    /**
     * Facebook si pri zdieľaní ťahá stránku opakovane a v nárazoch. Minútová
     * cache drží náraz mimo DB a zároveň je dosť krátka na to, aby sa oprava
     * názvu prejavila skôr, než si ju stihne niekto všimnúť.
     */
    private const CACHE_SECONDS = 300;

    public function __invoke(Request $request, JsonLd $jsonLd): Response
    {
        // Zámerne bez ohľadu na Accept-Language klienta. Jedna adresa má
        // v indexe jeden jazyk — inak by o jazyku indexovanej stránky rozhodla
        // hlavička crawlera a cache nižšie by ho ešte zafixovala pre všetkých
        // ostatných (kľúčom je len cesta). Jazyk portálu určuje APP_LOCALE;
        // `app.locale` už môže byť prepísaná middlewarom, preto `default_locale`.
        app()->setLocale(config('app.default_locale'));

        $path = $this->normalizePath((string) $request->query('path', '/'));

        $render = fn () => $this->render($path, $jsonLd);

        [$html, $status] = app()->hasDebugModeEnabled()
            ? $render()
            : Cache::remember("prerender:{$path}", self::CACHE_SECONDS, $render);

        return response($html, $status)
            ->header('Content-Type', 'text/html; charset=utf-8')
            // Crawler nikdy nie je prihlásený a odpoveď nezávisí od cookie —
            // nech ju smie držať aj proxy pred aplikáciou.
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function render(string $path, JsonLd $jsonLd): array
    {
        $view = $this->resolve($path, $jsonLd);

        return $view === null
            ? [$this->notFound()->render(), 404]
            : [$view->render(), 200];
    }

    private function resolve(string $path, JsonLd $jsonLd): ?View
    {
        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? '';

        return match (true) {
            $segments === [], $first === PublicUrl::EVENTS => $this->eventsBranch($segments, $jsonLd),
            $first === PublicUrl::VENUES || $first === 'venues' => $this->venue($segments[1] ?? '', $jsonLd),
            $first === PublicUrl::CANALS || $first === 'canals' => $this->canal($segments[1] ?? '', $jsonLd),
            $first === 'events' => $this->event($segments[1] ?? '', $jsonLd),
            default => null,
        };
    }

    /**
     * `/podujatia/*` nesie detail aj tri landing výpisy. Rozlišuje ich druhý
     * segment: `mesto`/`tema`/`tento-vikend` sú výpisy, čokoľvek končiace na
     * `-{id}` je detail.
     */
    private function eventsBranch(array $segments, JsonLd $jsonLd): ?View
    {
        $second = $segments[1] ?? null;

        return match (true) {
            $second === null => $this->eventList($jsonLd),
            $second === PublicUrl::THIS_WEEKEND => $this->weekendList($jsonLd),
            $second === PublicUrl::ARCHIVE => $this->archiveList($jsonLd),
            $second === PublicUrl::BY_MUNICIPALITY => $this->municipalityList($segments[2] ?? '', $jsonLd),
            $second === PublicUrl::BY_TAG => $this->tagList($segments[2] ?? '', $jsonLd),
            default => $this->event($second, $jsonLd),
        };
    }

    private function event(string $segment, JsonLd $jsonLd): ?View
    {
        $id = PublicUrl::idFromSegment($segment);

        if ($id === null) {
            return null;
        }

        /** @var Event|null $event */
        $event = Event::query()
            ->with([
                'canal:id,name,slug,website',
                'venue' => fn ($query) => $query->with('municipality'),
                'files',
                'tags',
                'ticketTypes' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            ])
            // Archivované sem patria rovnako ako publikované. Desať minút po
            // skončení podujatia ho archivuje app:events-archive-finished, a keby
            // sa filtrovalo len na `published`, crawler by na každom minulom
            // podujatí dostal 404 — na tej istej adrese, na ktorej človek v SPA
            // stránku normálne vidí. Odkaz zdieľaný na Facebooku by sa deň po
            // akcii zmenil na „stránka sa nenašla".
            ->whereIn('status', ModelStatus::publiclyReadableValues())
            ->find($id);

        if (! $event) {
            return null;
        }

        $description = $this->description(
            $event->body,
            trim(implode(' · ', array_filter([
                $event->start_at?->translatedFormat('j. F Y, H:i'),
                $event->venue?->name,
                $event->municipality?->shortname,
            ]))),
        );

        // Zodpovedané otázky publika. Crawler SPA obsah nevidí, takže bez tohto
        // by z Q&A na verejnom detaile nebol žiadny SEO úžitok — a práve ten je
        // dôvod, prečo sa nástenka vystavuje aj mimo QR kódu v sále.
        $faq = $this->answeredQuestions($event);

        return view('prerender.event', [
            'meta' => $this->meta(
                title: $event->name,
                description: $description,
                canonical: PublicUrl::event($event),
                image: $event->has_primary_image ? $event->primary_image['large'] : null,
                type: 'article',
            ),
            'event' => $event,
            'bodyHtml' => $this->safeBody($event->body),
            'faq' => $faq,
            // Stránka skončeného podujatia ostáva v indexe (viď archív nižšie),
            // ale musí to o sebe povedať — inak návštevník z vyhľadávača číta
            // pozvánku na akciu, ktorá už bola.
            'hasEnded' => EventTimeframe::hasEnded($event),
            'upcomingElsewhere' => EventTimeframe::hasEnded($event)
                ? $this->upcomingEvents(fn (Builder $query) => $event->canal_id
                    ? $query->where('canal_id', $event->canal_id)
                    : $query->where('venue_id', $event->venue_id))->take(5)
                : new Collection(),
            'structuredData' => array_values(array_filter([
                $jsonLd->event($event),
                $jsonLd->faqPage($faq, PublicUrl::event($event)),
                $jsonLd->breadcrumbs([
                    ['name' => __('seo.list.heading'), 'url' => PublicUrl::events()],
                    ['name' => $event->name, 'url' => PublicUrl::event($event)],
                ]),
            ])),
        ]);
    }

    /**
     * Zodpovedané otázky k podujatiu, zoradené ako na verejnom detaile.
     *
     * Nezodpovedané sa vynechávajú zámerne: pre návštevníka z vyhľadávača sú
     * bez hodnoty a do `FAQPage` sa nedajú zapísať vôbec (schéma vyžaduje
     * `acceptedAnswer`). Nástenka sa zakladá lenivo, takže väčšina podujatí
     * vráti prázdnu kolekciu a šablóna sekciu vynechá.
     *
     * @return Collection<int, Question>
     */
    private function answeredQuestions(Event $event): Collection
    {
        $board = $event->questionBoard()->first();

        if ($board === null || ! $board->show_questions) {
            return new Collection();
        }

        return $board->questions()
            ->publiclyVisible()
            ->answered()
            ->inFaqOrder()
            ->limit(self::MAX_FAQ_ENTRIES)
            ->get();
    }

    private function venue(string $segment, JsonLd $jsonLd): ?View
    {
        $id = PublicUrl::idFromSegment($segment);

        if ($id === null) {
            return null;
        }

        /** @var Venue|null $venue */
        $venue = Venue::query()
            ->with(['municipality', 'files'])
            ->where('status', ModelStatus::Published->value)
            ->find($id);

        if (! $venue) {
            return null;
        }

        $events = $this->upcomingEvents(fn (Builder $query) => $query->where('venue_id', $venue->id));
        $past = $this->pastEvents(fn (Builder $query) => $query->where('venue_id', $venue->id), self::PAST_ON_PROFILE);

        return view('prerender.venue', [
            'meta' => $this->meta(
                title: $venue->name,
                description: $this->description(
                    $venue->body,
                    trim(implode(', ', array_filter([$venue->street, $venue->municipality?->shortname])))
                        ?: __('seo.venue_description', ['name' => $venue->name]),
                ),
                canonical: PublicUrl::venue($venue),
                image: $venue->has_primary_image ? $venue->primary_image['large'] : null,
            ),
            'venue' => $venue,
            'bodyHtml' => $this->safeBody($venue->body),
            'events' => $events,
            'pastEvents' => $past,
            'structuredData' => [
                $jsonLd->venue($venue),
                $jsonLd->eventList($events, PublicUrl::venue($venue), __('seo.list.of_name', ['name' => $venue->name])),
            ],
        ]);
    }

    private function canal(string $segment, JsonLd $jsonLd): ?View
    {
        $id = PublicUrl::idFromSegment($segment);

        if ($id === null) {
            return null;
        }

        /** @var Canal|null $canal */
        $canal = Canal::query()
            ->with('files')
            ->where('status', ModelStatus::Published->value)
            ->find($id);

        if (! $canal) {
            return null;
        }

        $events = $this->upcomingEvents(fn (Builder $query) => $query->where('canal_id', $canal->id));
        $past = $this->pastEvents(fn (Builder $query) => $query->where('canal_id', $canal->id), self::PAST_ON_PROFILE);

        return view('prerender.canal', [
            'meta' => $this->meta(
                title: $canal->name,
                description: $this->description($canal->body, __('seo.canal_description', ['name' => $canal->name])),
                canonical: PublicUrl::canal($canal),
                image: $canal->has_primary_image ? $canal->primary_image['large'] : null,
            ),
            'canal' => $canal,
            'bodyHtml' => $this->safeBody($canal->body),
            'events' => $events,
            'pastEvents' => $past,
            'structuredData' => [
                $jsonLd->canal($canal),
                $jsonLd->eventList($events, PublicUrl::canal($canal), __('seo.list.of_name', ['name' => $canal->name])),
            ],
        ]);
    }

    private function eventList(JsonLd $jsonLd): View
    {
        return $this->list(
            heading: __('seo.list.heading'),
            title: __('seo.list.title'),
            description: __('seo.list.description'),
            canonical: PublicUrl::events(),
            events: $this->upcomingEvents(),
            jsonLd: $jsonLd,
        );
    }

    private function weekendList(JsonLd $jsonLd): View
    {
        [$from, $to] = EventTimeframe::thisWeekend();

        $events = $this->upcomingEvents(
            fn (Builder $query) => $query->whereBetween('start_at', [max(now(), $from), $to]),
        );

        return $this->list(
            heading: __('seo.list.weekend_heading'),
            title: __('seo.list.weekend_heading'),
            description: __('seo.list.weekend_description', [
                'from' => $from->format('j. n.'),
                'to' => $to->format('j. n. Y'),
            ]),
            canonical: PublicUrl::thisWeekend(),
            events: $events,
            jsonLd: $jsonLd,
        );
    }

    /**
     * Archív. Jediná stránka, z ktorej vedie odkaz na skončené podujatia —
     * bez nej sú ich detaily osirené: nevedie na ne nič z portálu a Google
     * ich časom vyhodí z indexu ako stránky, ku ktorým sa nedá dostať.
     */
    private function archiveList(JsonLd $jsonLd): View
    {
        return $this->list(
            heading: __('seo.list.archive_heading'),
            title: __('seo.list.archive_title'),
            description: __('seo.list.archive_description'),
            canonical: PublicUrl::archive(),
            events: $this->pastEvents(),
            jsonLd: $jsonLd,
        );
    }

    private function municipalityList(string $slug, JsonLd $jsonLd): ?View
    {
        $municipality = Municipality::query()->where('slug', $slug)->first();

        if (! $municipality) {
            return null;
        }

        $events = $this->upcomingEvents(
            fn (Builder $query) => $query->whereHas('venue', fn (Builder $venue) => $venue->where('village_id', $municipality->id)),
        );

        return $this->list(
            heading: __('seo.list.municipality_heading', ['name' => $municipality->shortname]),
            title: __('seo.list.municipality_title', ['name' => $municipality->shortname]),
            description: __('seo.list.municipality_description', ['name' => $municipality->shortname]),
            canonical: PublicUrl::municipality($municipality),
            events: $events,
            jsonLd: $jsonLd,
        );
    }

    private function tagList(string $slug, JsonLd $jsonLd): ?View
    {
        $tag = Tag::query()->where('slug', $slug)->first();

        if (! $tag) {
            return null;
        }

        $events = $this->upcomingEvents(
            fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->where('tags.id', $tag->id)),
        );

        return $this->list(
            heading: __('seo.list.tag_heading', ['name' => $tag->name]),
            title: __('seo.list.tag_title', ['name' => $tag->name]),
            description: __('seo.list.tag_description', ['name' => $tag->name]),
            canonical: PublicUrl::tag($tag),
            events: $events,
            jsonLd: $jsonLd,
        );
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function list(
        string $heading,
        string $title,
        string $description,
        string $canonical,
        Collection $events,
        JsonLd $jsonLd,
    ): View {
        return view('prerender.list', [
            'meta' => $this->meta($title, $description, $canonical, $this->listImage($events), type: 'website'),
            'heading' => $heading,
            'events' => $events,
            'structuredData' => [$jsonLd->eventList($events, $canonical, $heading)],
        ]);
    }

    /**
     * @param  (callable(Builder): Builder)|null  $filter
     * @return Collection<int, Event>
     */
    private function upcomingEvents(?callable $filter = null): Collection
    {
        $query = EventTimeframe::upcoming($this->publicEvents())
            ->where('status', ModelStatus::Published->value)
            ->orderBy('start_at');

        if ($filter) {
            $filter($query);
        }

        return $query->limit(self::LIST_LIMIT)->get();
    }

    /**
     * Skončené podujatia, od najnovšieho. Stav je širší než pri nadchádzajúcich:
     * po skončení ich `app:events-archive-finished` preklopí na `archived`,
     * takže filter len na `published` by vrátil prázdno.
     *
     * @param  (callable(Builder): Builder)|null  $filter
     * @return Collection<int, Event>
     */
    private function pastEvents(?callable $filter = null, int $limit = self::LIST_LIMIT): Collection
    {
        $query = EventTimeframe::past($this->publicEvents())
            ->whereIn('status', ModelStatus::publiclyReadableValues())
            ->orderByDesc('start_at');

        if ($filter) {
            $filter($query);
        }

        return $query->limit($limit)->get();
    }

    /**
     * @return Builder<Event>
     */
    private function publicEvents(): Builder
    {
        return Event::query()->with([
            'canal:id,name,slug',
            'venue' => fn ($relation) => $relation->with('municipality'),
            'files',
        ]);
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function listImage(Collection $events): ?string
    {
        $withImage = $events->first(fn (Event $event) => $event->has_primary_image);

        return $withImage ? $withImage->primary_image['large'] : null;
    }

    /**
     * @return array<string, string|null>
     */
    private function meta(
        string $title,
        ?string $description,
        string $canonical,
        ?string $image = null,
        string $type = 'website',
    ): array {
        return [
            'title' => $title,
            'site_title' => $title.' | '.config('app.name'),
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'type' => $type,
        ];
    }

    private function notFound(): View
    {
        return view('prerender.list', [
            'meta' => $this->meta(
                title: __('seo.not_found_title'),
                description: __('seo.not_found_description'),
                canonical: PublicUrl::events(),
            ),
            'heading' => __('seo.not_found_title'),
            'events' => collect(),
            'structuredData' => [],
        ]);
    }

    /**
     * `body` sa dnes ukladá bez sanitizácie (ROADMAP 0.1), takže surové HTML
     * z DB sa sem nesmie dostať — prerender je verejná HTML odpoveď, nie JSON.
     * `HtmlBodyCleaner` už v projekte je, len bežal iba na importe a AI výstupe.
     */
    private function safeBody(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        return app(HtmlBodyCleaner::class)->cleanHtmlString($html) ?: null;
    }

    private function description(?string $html, string $fallback): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)) ?? '');

        return $text !== '' ? mb_strimwidth($text, 0, 200, '…') : $fallback;
    }

    /**
     * Cesta zo SPA hostu: bez domény, bez query, bez lomiek navyše. Prerender
     * z nej robí kľúč do cache aj vstup do routovania, takže musí byť
     * jednoznačná — inak by `/podujatia/` a `/podujatia` boli dva záznamy.
     */
    private function normalizePath(string $path): string
    {
        $path = (string) parse_url($path, PHP_URL_PATH);

        return trim(rawurldecode($path), '/');
    }
}
