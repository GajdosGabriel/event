<?php

namespace App\Http\Controllers\Public;

use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Tag;
use App\Models\Venue;
use App\Support\PublicUrl;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * `sitemap.xml` pre verejnú časť portálu.
 *
 * Jeden súbor, nie index s podsitemapami: aj so všetkými obcami a štítkami
 * ide o tisícky adries, teda hlboko pod limitom 50 000 URL, a nasadenie tak
 * potrebuje jediné pravidlo v `.htaccess`.
 *
 * Do mapy patrí len to, čo je naozaj verejné a živé — skončené podujatia by
 * z nej robili zoznam 404-iek v očiach vyhľadávača.
 */
class SitemapController extends Controller
{
    private const CACHE_SECONDS = 3600;

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', self::CACHE_SECONDS, fn () => $this->build());

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function build(): string
    {
        $urls = collect()
            ->push($this->url(PublicUrl::events(), null, 'hourly', '1.0'))
            ->push($this->url(PublicUrl::thisWeekend(), null, 'daily', '0.9'))
            ->merge($this->events())
            ->merge($this->municipalities())
            ->merge($this->tags())
            ->merge($this->venues())
            ->merge($this->canals());

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls->implode("\n")."\n"
            .'</urlset>'."\n";
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function events()
    {
        return $this->publishedUpcomingEvents()
            ->with(['canal:id,slug,name'])
            ->orderBy('start_at')
            ->get()
            ->map(fn (Event $event) => $this->url(
                PublicUrl::event($event),
                $event->updated_at,
                'daily',
                '0.8',
            ));
    }

    /**
     * Len obce, ktoré naozaj majú čo ukázať — landing bez podujatí je pre
     * vyhľadávač prázdna stránka a stiahne dole hodnotenie zvyšku portálu.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function municipalities()
    {
        $ids = $this->publishedUpcomingEvents()
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->distinct()
            ->pluck('venues.village_id')
            ->filter();

        return Municipality::query()
            ->whereIn('id', $ids)
            ->whereNotNull('slug')
            ->orderBy('shortname')
            ->get()
            ->map(fn (Municipality $municipality) => $this->url(
                PublicUrl::municipality($municipality),
                null,
                'daily',
                '0.7',
            ));
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function tags()
    {
        $ids = $this->publishedUpcomingEvents()
            ->join('event_tag', 'event_tag.event_id', '=', 'events.id')
            ->distinct()
            ->pluck('event_tag.tag_id');

        return Tag::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => $this->url(PublicUrl::tag($tag), null, 'daily', '0.6'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function venues()
    {
        return Venue::query()
            ->where('status', ModelStatus::Published->value)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'updated_at'])
            ->map(fn (Venue $venue) => $this->url(PublicUrl::venue($venue), $venue->updated_at, 'weekly', '0.5'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function canals()
    {
        return Canal::query()
            ->where('status', ModelStatus::Published->value)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'updated_at'])
            ->map(fn (Canal $canal) => $this->url(PublicUrl::canal($canal), $canal->updated_at, 'weekly', '0.5'));
    }

    /**
     * Publikované podujatia, ktoré ešte neskončili — rovnaké okno, aké používa
     * verejný výpis, aby sitemap a portál hovorili to isté.
     */
    private function publishedUpcomingEvents(): Builder
    {
        return Event::query()
            ->where('events.status', ModelStatus::Published->value)
            ->where(function (Builder $timeframe) {
                $timeframe->where('events.end_at', '>=', now())
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNull('events.end_at')
                            ->where('events.start_at', '>=', now()->startOfDay());
                    });
            });
    }

    private function url(string $location, ?CarbonInterface $lastModified, string $changeFrequency, string $priority): string
    {
        $lastModifiedTag = $lastModified
            ? '    <lastmod>'.$lastModified->toAtomString().'</lastmod>'."\n"
            : '';

        return '  <url>'."\n"
            .'    <loc>'.htmlspecialchars($location, ENT_XML1).'</loc>'."\n"
            .$lastModifiedTag
            .'    <changefreq>'.$changeFrequency.'</changefreq>'."\n"
            .'    <priority>'.$priority.'</priority>'."\n"
            .'  </url>';
    }
}
