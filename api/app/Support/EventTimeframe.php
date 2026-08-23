<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Časové okná verejných výpisov.
 *
 * Landing „tento víkend" existuje dvakrát — raz v SPA (cez `/api/events`)
 * a raz v bot-render vrstve. Keby si každá počítala víkend sama, crawler by
 * indexoval iný zoznam, než akým sa mu stránka odmení po kliknutí.
 *
 * To isté platí o hranici „už bolo / ešte bude": rozhoduje o výpise, o archíve,
 * o tom, čo ide do `sitemap.xml`, aj o hláške na detaile. Rozpísaná bola na
 * štyroch miestach s drobnými odchýlkami — tu je raz.
 */
final class EventTimeframe
{
    /**
     * Podujatia, ktoré ešte len budú alebo práve prebiehajú.
     *
     * Bez `end_at` sa berie celý deň začiatku — jednodňová akcia zadaná len
     * dátumom nesmie zmiznúť z výpisu hneď ráno v deň konania.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public static function upcoming(Builder $query, string $table = ''): Builder
    {
        $column = fn (string $name) => $table === '' ? $name : "{$table}.{$name}";

        return $query->where(function (Builder $timeframe) use ($column) {
            $timeframe->where($column('end_at'), '>=', now())
                ->orWhere(function (Builder $inner) use ($column) {
                    $inner->whereNull($column('end_at'))
                        ->where($column('start_at'), '>=', now()->startOfDay());
                });
        });
    }

    /**
     * Presný doplnok k upcoming() nad podujatiami s termínom. Podujatie bez
     * dátumu nie je ani v jednom okne — nemá sa podľa čoho zaradiť.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public static function past(Builder $query, string $table = ''): Builder
    {
        $column = fn (string $name) => $table === '' ? $name : "{$table}.{$name}";

        return $query->where(function (Builder $timeframe) use ($column) {
            $timeframe->where($column('end_at'), '<', now())
                ->orWhere(function (Builder $inner) use ($column) {
                    $inner->whereNull($column('end_at'))
                        ->where($column('start_at'), '<', now()->startOfDay());
                });
        });
    }

    /** Tá istá hranica ako past(), len nad načítaným modelom. */
    public static function hasEnded(Event $event): bool
    {
        if ($event->end_at) {
            return $event->end_at->isPast();
        }

        return $event->start_at !== null && $event->start_at->lessThan(now()->startOfDay());
    }

    /**
     * Víkend počítame od piatka rána do nedele polnoci. V piatok a cez víkend
     * teda ide o prebiehajúci víkend, nie o nasledujúci — človek, ktorý si
     * v sobotu otvorí „čo je tento víkend", chce dnešok, nie o týždeň.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function thisWeekend(): array
    {
        $from = now()->startOfWeek()->addDays(4)->startOfDay();
        $to = now()->startOfWeek()->addDays(6)->endOfDay();

        if (now()->greaterThan($to)) {
            $from = $from->addWeek();
            $to = $to->addWeek();
        }

        return [$from, $to];
    }
}
