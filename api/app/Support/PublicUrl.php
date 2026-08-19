<?php

namespace App\Support;

use App\Models\Canal;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Tag;
use App\Models\Venue;
use Illuminate\Support\Str;

/**
 * Jediné miesto, ktoré vie, ako vyzerá verejná URL.
 *
 * Používa ju bot-render vrstva, sitemap aj `<link rel="canonical">`. Keby si
 * každá z nich cestu skladala sama, raz by sa rozišli a vyhľadávač by
 * indexoval dve adresy toho istého obsahu.
 *
 * Tvar detailu je `{slug}-{id}`: slug je pre človeka a pre vyhľadávač, id je
 * jediná časť, ktorá sa naozaj routuje. Premenovanie podujatia tak nezhodí
 * staré odkazy — SPA aj prerender čítajú len číslo za poslednou pomlčkou.
 *
 * Základ je `FRONTEND_URL`, nie `APP_URL`: verejné adresy patria SPA hostu,
 * API beží pod `/api` na tom istom origine (pozri [deploy/htaccess.md]).
 */
final class PublicUrl
{
    /** Prvý segment detailu podujatia. */
    public const EVENTS = 'podujatia';

    /** Prvý segment detailu miesta. */
    public const VENUES = 'miesta';

    /** Prvý segment detailu kanála (organizátora). */
    public const CANALS = 'organizatori';

    /** Landing „podujatia v meste". */
    public const BY_MUNICIPALITY = 'mesto';

    /** Landing „podujatia so štítkom". */
    public const BY_TAG = 'tema';

    /** Landing „tento víkend". */
    public const THIS_WEEKEND = 'tento-vikend';

    /**
     * Nástenka otázok z publika. Jediná verejná cesta, ktorá nie je slovenské
     * slovo — a je to zámer: adresa sa premieta na plátno a ľudia v zadnom rade
     * si ju prepisujú rukou do telefónu, takže každý znak navyše je cena.
     */
    public const QUESTIONS = 'q';

    /** Premietacia stena tej istej nástenky (projektor, nie telefón). */
    public const QUESTIONS_WALL = 'stena';

    /**
     * Odhlásenie odberu. Chodí v pätičke každého e-mailu, ktorý z odberu
     * vznikne — token v odkaze je autorizácia, rovnako ako pri RSVP.
     */
    public const UNSUBSCRIBE = 'odhlasenie';

    public static function base(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }

    public static function absolute(string $path): string
    {
        return self::base().'/'.ltrim($path, '/');
    }

    public static function eventPath(Event $event): string
    {
        return self::EVENTS.'/'.self::segment($event->slug, $event->name, $event->id);
    }

    public static function event(Event $event): string
    {
        return self::absolute(self::eventPath($event));
    }

    public static function venuePath(Venue $venue): string
    {
        return self::VENUES.'/'.self::segment($venue->slug, $venue->name, $venue->id);
    }

    public static function venue(Venue $venue): string
    {
        return self::absolute(self::venuePath($venue));
    }

    public static function canalPath(Canal $canal): string
    {
        return self::CANALS.'/'.self::segment($canal->slug, $canal->name, $canal->id);
    }

    public static function canal(Canal $canal): string
    {
        return self::absolute(self::canalPath($canal));
    }

    public static function eventsPath(): string
    {
        return self::EVENTS;
    }

    public static function events(): string
    {
        return self::absolute(self::eventsPath());
    }

    public static function municipalityPath(Municipality $municipality): string
    {
        return self::EVENTS.'/'.self::BY_MUNICIPALITY.'/'.$municipality->slug;
    }

    public static function municipality(Municipality $municipality): string
    {
        return self::absolute(self::municipalityPath($municipality));
    }

    public static function tagPath(Tag $tag): string
    {
        return self::EVENTS.'/'.self::BY_TAG.'/'.$tag->slug;
    }

    public static function tag(Tag $tag): string
    {
        return self::absolute(self::tagPath($tag));
    }

    public static function thisWeekendPath(): string
    {
        return self::EVENTS.'/'.self::THIS_WEEKEND;
    }

    public static function thisWeekend(): string
    {
        return self::absolute(self::thisWeekendPath());
    }

    public static function questionBoardPath(string $token): string
    {
        return self::QUESTIONS.'/'.$token;
    }

    public static function questionBoard(string $token): string
    {
        return self::absolute(self::questionBoardPath($token));
    }

    public static function questionWall(string $token): string
    {
        return self::absolute(self::questionBoardPath($token).'/'.self::QUESTIONS_WALL);
    }

    public static function unsubscribe(string $token): string
    {
        return self::absolute(self::UNSUBSCRIBE.'/'.$token);
    }

    /**
     * Id z `{slug}-{id}`. Vracia null, keď segment na id nekončí — volajúci
     * potom vie odlíšiť detail od landing segmentu (`mesto`, `tento-vikend`).
     */
    public static function idFromSegment(string $segment): ?int
    {
        return preg_match('/(?:^|-)(\d+)$/', $segment, $m) === 1
            ? (int) $m[1]
            : null;
    }

    /**
     * Slug je v DB, ale historické riadky ho mať nemusia (mutator ho dopĺňa až
     * pri zápise mena), preto fallback na meno a nakoniec na typ záznamu — do
     * URL sa nikdy nesmie dostať samotná pomlčka pred id.
     */
    private static function segment(?string $slug, ?string $name, int|string $id): string
    {
        $slug = trim((string) $slug) !== '' ? $slug : Str::slug((string) $name);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? "{$slug}-{$id}" : (string) $id;
    }
}
