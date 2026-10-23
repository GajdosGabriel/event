<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zlúči duplicitné podujatia nájdené kontrolou produkčnej databázy (23. 10. 2026).
 *
 * Tá istá akcia z viacerých článkov (TK KBS o nej napísala viackrát, raz
 * aj TV LUX, ktorá ju prenáša; vyveska.sk aj tkkbs.sk), alebo ten istý článok
 * naimportovaný opakovane ako koncept. Kandidáti: rovnaký názov a začiatok,
 * resp. rovnaký deň a miesto/kanál s podobným názvom; každá skupina posúdená
 * ručne. Ostáva záznam podľa pravidiel DuplicateEventMerger (kanál
 * organizátora pred zberným kanálom, dlhší popis), s dvoma výnimkami:
 *  - púť za kňazov v Šaštíne: ostáva #11181, #298 má názov o začiatku reťaze
 *    (15. 9.), hoci termín je jej vyvrcholenie 24. 10.,
 *  - „Pripravte cestu Pánovi…": #10816 má popis inej akcie („K výšinám",
 *    september) a zástupné miesto Bratislava, ostáva #291.
 * Jubileum Cantica Sacra Tyrnaviae ostáva s miestom Jezuitský kostol (uvádzajú
 * ho oba články), nie s adresou Ružová 4.
 *
 * Zlučuje sa ako v App\Services\Events\DuplicateEventMerger::mergeGroup():
 * zahodené podujatie ide do koša s meta.merged_into a jeho zdrojová adresa sa
 * zapíše do meta.merged_sources toho, ktoré ostalo — inak by ho najbližší
 * import vyrobil znova (App\Services\Imports\EventSourceLookup). Navyše sa
 * presunú zobrazenia (a pripočíta sa počet), rozosielky a čakajúce
 * registrácie; obrázky sa presunú len vtedy, keď ostávajúce podujatie
 * žiadny nemá. Podujatie s objednávkou, vstupenkou, odberom či nástenkou
 * otázok sa nezahadzuje.
 *
 * down() vráti zahodené podujatia z koša; presuny sa nevracajú.
 */
return new class extends Migration
{
    /** @var array<int, list<int>> ostáva => [zahodené, ...] */
    private const MERGES = [
        11121 => [11158, 11163], // Novéna k 250. výročiu Rožňavskej diecézy (aj článok TV LUX)
        11181 => [298],          // Púť za kňazov v Šaštíne, 24. 10.
        11159 => [11156],        // Konsekrácia kostola sv. Michala v Spišskom Bystrom
        11197 => [11195],        // 50 rokov Cantica Sacra Tyrnaviae
        291 => [10816],          // Pripravte cestu Pánovi, vyrovnajte mu chodníky (Centrum Salvator)
        11199 => [11200],        // Registrácia na SDM v Soule 2027
        11117 => [11116, 11118, 11119], // koncept: Predseda KBS vyzval k modlitbe za pápeža
        11124 => [11125],        // koncept: súťaž Rady KBS pre spoločenské komunikačné prostriedky
        // Archív (minulé podujatia):
        10938 => [10935],        // KBS pozvala pápeža na návštevu Slovenska
        199 => [280],            // Národná púť k Sedembolestnej v Šaštíne 2026
        10937 => [10936],        // Predseda KBS: Panna Mária je a zostane našou patrónkou
        289 => [288],            // Tréning zručností tvorby komunity
        2293 => [2730],          // Otec a syn 2022
        5521 => [5955],          // Spoločná ekumenická duchovná obnova 2023 v Herľanoch
        8308 => [8307],          // Slovanskí vierozvestcovia v slovanskom svete
    ];

    /** Jubileum Cantica Sacra Tyrnaviae: Ružová 4 → Jezuitský kostol v Trnave. */
    private const CANTICA_EVENT_ID = 11197;

    private const CANTICA_WRONG_VENUE_ID = 2;

    private const JESUIT_CHURCH_VENUE_ID = 1017;

    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        foreach (self::MERGES as $keepId => $dropIds) {
            DB::transaction(fn () => $this->mergeGroup($keepId, $dropIds));
        }

        if (DB::table('venues')->where('id', self::JESUIT_CHURCH_VENUE_ID)->whereNull('deleted_at')->exists()) {
            DB::table('events')
                ->where('id', self::CANTICA_EVENT_ID)
                ->where('venue_id', self::CANTICA_WRONG_VENUE_ID)
                ->update(['venue_id' => self::JESUIT_CHURCH_VENUE_ID, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        foreach (self::MERGES as $keepId => $dropIds) {
            DB::table('events')
                ->whereIn('id', $dropIds)
                ->whereNotNull('deleted_at')
                ->where('meta->merged_into', $keepId)
                ->update(['deleted_at' => null]);
        }
    }

    /** @param  list<int>  $dropIds */
    private function mergeGroup(int $keepId, array $dropIds): void
    {
        $keep = DB::table('events')->where('id', $keepId)->whereNull('deleted_at')->first();

        if ($keep === null) {
            return;
        }

        $meta = $this->decode($keep->meta);
        $sources = collect($meta['merged_sources'] ?? []);
        $views = (int) $keep->views_count;
        $keepHasImage = $this->hasImage($keepId);

        foreach ($dropIds as $dropId) {
            $drop = DB::table('events')->where('id', $dropId)->whereNull('deleted_at')->first();

            if ($drop === null || $this->isReferenced($dropId)) {
                continue;
            }

            $source = trim((string) $drop->orginal_source);

            if ($source !== '') {
                $sources->push($source);
            }

            foreach ($this->decode($drop->meta)['merged_sources'] ?? [] as $merged) {
                $sources->push($merged);
            }

            $views += (int) $drop->views_count;

            $this->moveViews($dropId, $keepId);

            foreach (['canal_outreach', 'pending_registrations'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('event_id', $dropId)->update(['event_id' => $keepId]);
                }
            }

            if (! $keepHasImage && $this->hasImage($dropId)) {
                DB::table('files')->where('fileable_type', Event::class)->where('fileable_id', $dropId)
                    ->update(['fileable_id' => $keepId]);
                $keepHasImage = true;
            }

            $dropMeta = $this->decode($drop->meta);
            $dropMeta['merged_into'] = $keepId;

            DB::table('events')->where('id', $dropId)->update([
                'meta' => json_encode($dropMeta),
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sources = $sources->unique()->values()->all();

        if ($sources === ($meta['merged_sources'] ?? []) && $views === (int) $keep->views_count) {
            return;
        }

        $meta['merged_sources'] = $sources;

        DB::table('events')->where('id', $keepId)->update([
            'meta' => json_encode($meta),
            'views_count' => $views,
            'updated_at' => now(),
        ]);
    }

    /**
     * Návštevník, ktorý v ten deň videl obe duplicity, je jedno zobrazenie
     * (unikátny index views_unique_per_day) — jeho riadok duplicity sa zahodí.
     */
    private function moveViews(int $fromId, int $toId): void
    {
        if (! Schema::hasTable('views')) {
            return;
        }

        $conflicting = DB::table('views as d')
            ->join('views as k', fn ($join) => $join->on('k.visitor_hash', '=', 'd.visitor_hash')->on('k.viewed_on', '=', 'd.viewed_on'))
            ->where('d.viewable_type', Event::class)->where('d.viewable_id', $fromId)
            ->where('k.viewable_type', Event::class)->where('k.viewable_id', $toId)
            ->pluck('d.id')->all();

        DB::table('views')->whereIn('id', $conflicting)->delete();
        DB::table('views')->where('viewable_type', Event::class)->where('viewable_id', $fromId)
            ->update(['viewable_id' => $toId]);
    }

    /** Na podujatí, s ktorým už niekto pracoval, nesmie automatika nič zahodiť. */
    private function isReferenced(int $eventId): bool
    {
        foreach (['tickets', 'ticket_admissions'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('event_id', $eventId)->exists()) {
                return true;
            }
        }

        foreach (['subscriptions' => 'subscribable', 'question_boards' => 'boardable'] as $table => $morph) {
            if (Schema::hasTable($table) && DB::table($table)
                ->where($morph.'_type', Event::class)->where($morph.'_id', $eventId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasImage(int $eventId): bool
    {
        return Schema::hasTable('files')
            && DB::table('files')->where('fileable_type', Event::class)->where('fileable_id', $eventId)->exists();
    }

    /** @return array<string, mixed> */
    private function decode(?string $json): array
    {
        $decoded = $json === null ? null : json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
};
