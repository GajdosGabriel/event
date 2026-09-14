<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Podujatia, pri ktorých zdroj uviedol len dátum bez času, dostali koniec
 * „začiatok + 2 h". Na webe potom svietil vymyslený čas — napr. podujatie 10501
 * („odovzdávanie sa uskutoční 16. septembra 2026") ako 16. 9. 02:00–04:00.
 * Odteraz import dáva celý deň (EventImportService::fallbackEndAt()); táto
 * migrácia prerobí to, čo vzniklo predtým:
 *
 *  - prenos archívu hlascirkvi uložil dátum ako polnoc **UTC** (~1460 podujatí),
 *  - import zo zdrojov ako polnoc **miestneho** času.
 *
 * Oboje sa mení na 00:00–23:59:59 miestneho času v ten istý kalendárny deň.
 */
return new class extends Migration
{
    private const TIMEZONE = 'Europe/Bratislava';

    public function up(): void
    {
        DB::table('events')
            ->whereNotNull('start_at')
            ->whereRaw('TIMESTAMPDIFF(MINUTE, start_at, end_at) = 120')
            ->whereRaw("TIME(start_at) IN ('00:00:00', '22:00:00', '23:00:00')")
            ->orderBy('id')
            ->select(['id', 'start_at', 'meta'])
            ->chunkById(500, function ($events) {
                foreach ($events as $event) {
                    $day = $this->localDay($event);
                    if ($day === null) {
                        continue;
                    }

                    DB::table('events')->where('id', $event->id)->update([
                        'start_at' => $day->copy()->startOfDay()->utc()->format('Y-m-d H:i:s'),
                        'end_at' => $day->copy()->endOfDay()->utc()->format('Y-m-d H:i:s'),
                    ]);
                }
            });
    }

    public function down(): void {}

    /** Kalendárny deň podujatia, alebo null, keď začiatok nie je „len dátum". */
    private function localDay(object $event): ?Carbon
    {
        $utc = Carbon::parse($event->start_at, 'UTC');

        $isLegacy = (json_decode((string) $event->meta, true)['import']['source'] ?? null) === 'hlascirkvi_legacy';
        if ($isLegacy && $utc->format('H:i:s') === '00:00:00') {
            return Carbon::create($utc->year, $utc->month, $utc->day, 0, 0, 0, self::TIMEZONE);
        }

        $local = $utc->copy()->setTimezone(self::TIMEZONE);

        return $local->format('H:i:s') === '00:00:00' ? $local : null;
    }
};
