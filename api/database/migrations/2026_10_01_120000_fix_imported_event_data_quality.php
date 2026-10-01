<?php

use App\Support\ImportedText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opravy dát z importu nahlásené po kontrole obsahu (1. 10. 2026).
 *
 *  1. Podujatia Domu Quo Vadis (vyveska.sk) viseli na kanáli „Múzeum obetí
 *     komunizmu v Košiciach" — voľné LIKE v ImportedCanalManager (opravené
 *     v kóde). Presunú sa na kanál „Dom Quo Vadis".
 *  2. Miesta s názvom v 6. páde („Kostole …") a s „(okres)" dostanú správny
 *     názov. „Vincent de Paul" (16 podujatí farnosti Prievoz) je kostol.
 *  3. Interné kódy TK KBS „(TK KBS, is, eg, ml; pz) 20260616014" sa z popisov
 *     odstránia; „Sv.omša" → „Sv. omša" v názvoch aj popisoch.
 *  4. Zlý rok v názve „TURZOVKA 2025 – 3.-4. Október 2026".
 *  5. Duplicity (rovnaká akcia dvakrát) sa archivujú: Mužská ekumenická
 *     konferencia (Vrbové) a ekumenická obnova v Badíne; z obnovy v Badíne
 *     zmiznú nesprávne štítky „Online" a „Púť".
 *
 * Každá zmena sa robí len keď záznam stále sedí na id aj na pôvodný údaj
 * (názov, zdrojová URL), takže ručné opravy ostanú nedotknuté. Čistenie textov
 * (bod 3), archivácia a štítky sa nevracajú — down() vráti presun podujatí,
 * názvy miest a názov Turzovky.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> id miesta => [starý názov, nový názov] */
    private const VENUES = [
        14 => ['Levoča (okres)', 'Levoča'],
        93 => ['Žiar nad Hronom (okres)', 'Žiar nad Hronom'],
        106 => ['Dolný Kubín (okres)', 'Dolný Kubín'],
        734 => ['Medzilaborce (okres)', 'Medzilaborce'],
        107 => ['Seminárnom kostole na Hlavnej 89', 'Seminárny kostol na Hlavnej 89'],
        111 => ['Kostole sv. Arnolda Janssena', 'Kostol sv. Arnolda Janssena'],
        125 => ['Kláštore minoritov pri svätom Jakubovi', 'Kláštor minoritov pri svätom Jakubovi'],
        713 => ['Kostole sv. Štefana Uhorského', 'Kostol sv. Štefana Uhorského'],
        716 => ['Kostole Notre Dame', 'Kostol Notre Dame'],
        952 => ['Kostole povýšenia Svätého kríža', 'Kostol povýšenia Svätého kríža'],
        733 => ['Vincent de Paul', 'Kostol sv. Vincenta de Paul'],
    ];

    /** @var array<int, string> id podujatia => orginal_source */
    private const QUO_VADIS_EVENTS = [
        11017 => 'https://www.vyveska.sk/put-v-ramci-roku-sv-frantiska-piestany-hlohovec/',
        11018 => 'https://www.vyveska.sk/co-zostane-z-europy-bez-krestanstva-cirkev-hori/',
        11019 => 'https://www.vyveska.sk/knizna-burza-marys-meals/',
        11020 => 'https://www.vyveska.sk/alfa-kurz-zaciatok-kurzu/',
        11021 => 'https://www.vyveska.sk/malovanie-zalmov/',
    ];

    private const WRONG_CANAL_ID = 1532;

    private const WRONG_CANAL_NAME = 'Múzeum obetí komunizmu v Košiciach';

    private const QUO_VADIS_CANAL_ID = 278;

    private const QUO_VADIS_CANAL_NAME = 'Dom Quo Vadis';

    private const TURZOVKA_ID = 317;

    private const TURZOVKA_OLD = 'TURZOVKA 2025 – 3.-4. Október 2026 dvojdňová Jesenná púť do Turzovky';

    private const TURZOVKA_NEW = 'TURZOVKA 2026 – 3.-4. Október 2026 dvojdňová Jesenná púť do Turzovky';

    /** @var array<int, string> id duplicity (archivuje sa) => orginal_source */
    private const DUPLICATES = [
        63 => 'https://www.vyveska.sk/muzska-ekumenicka-konferencia/',
        93 => 'https://www.tkkbs.sk/view.php?cisloclanku=20260804009',
    ];

    /** Badínska obnova: ostáva #294, nesprávne štítky sa dajú preč. */
    private const BADIN_EVENT_ID = 294;

    public function up(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $this->moveQuoVadisEvents(self::WRONG_CANAL_ID, self::WRONG_CANAL_NAME, self::QUO_VADIS_CANAL_ID, self::QUO_VADIS_CANAL_NAME);

        foreach (self::VENUES as $id => [$old, $new]) {
            $this->renameVenue($id, $old, $new);
        }

        DB::table('events')
            ->where('id', self::TURZOVKA_ID)
            ->where('name', self::TURZOVKA_OLD)
            ->update(['name' => self::TURZOVKA_NEW, 'updated_at' => now()]);

        $this->cleanTexts();

        foreach (self::DUPLICATES as $id => $source) {
            DB::table('events')
                ->where('id', $id)
                ->where('orginal_source', $source)
                ->where('status', 'published')
                ->update(['status' => 'archived', 'updated_at' => now()]);
        }

        $this->dropWrongBadinTags();
    }

    public function down(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $this->moveQuoVadisEvents(self::QUO_VADIS_CANAL_ID, self::QUO_VADIS_CANAL_NAME, self::WRONG_CANAL_ID, self::WRONG_CANAL_NAME);

        foreach (self::VENUES as $id => [$old, $new]) {
            $this->renameVenue($id, $new, $old);
        }

        DB::table('events')
            ->where('id', self::TURZOVKA_ID)
            ->where('name', self::TURZOVKA_NEW)
            ->update(['name' => self::TURZOVKA_OLD, 'updated_at' => now()]);
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('events') && Schema::hasTable('canals') && Schema::hasTable('venues');
    }

    private function moveQuoVadisEvents(int $fromId, string $fromName, int $toId, string $toName): void
    {
        if (! DB::table('canals')->where('id', $fromId)->where('name', $fromName)->exists()
            || ! DB::table('canals')->where('id', $toId)->where('name', $toName)->exists()) {
            return;
        }

        foreach (self::QUO_VADIS_EVENTS as $eventId => $source) {
            DB::table('events')
                ->where('id', $eventId)
                ->where('canal_id', $fromId)
                ->where('orginal_source', $source)
                ->update(['canal_id' => $toId, 'updated_at' => now()]);
        }
    }

    private function renameVenue(int $id, string $from, string $to): void
    {
        DB::table('venues')
            ->where('id', $id)
            ->where('name', $from)
            ->update(['name' => $to, 'updated_at' => now()]);
    }

    /** Kódy agentúry z popisov a medzera v „Sv.omša" v názvoch aj popisoch. */
    private function cleanTexts(): void
    {
        DB::table('events')
            ->where(fn ($q) => $q->where('body', 'like', '%(TK KBS,%')
                ->orWhere('body', 'like', '%sv.%')
                ->orWhere('name', 'like', '%sv.%'))
            ->select(['id', 'name', 'body'])
            ->orderBy('id')
            ->chunkById(200, function ($events): void {
                foreach ($events as $event) {
                    $name = ImportedText::fixAbbreviationSpacing((string) $event->name);
                    $body = $event->body === null
                        ? null
                        : ImportedText::fixAbbreviationSpacing(ImportedText::stripNewsroomCodes((string) $event->body));

                    if ($name === $event->name && $body === $event->body) {
                        continue;
                    }

                    // Bez updated_at: oprava textu nemá posúvať „naposledy upravené".
                    DB::table('events')->where('id', $event->id)->update(['name' => $name, 'body' => $body]);
                }
            });
    }

    private function dropWrongBadinTags(): void
    {
        if (! Schema::hasTable('event_tag') || ! Schema::hasTable('tags')) {
            return;
        }

        $tagIds = DB::table('tags')->whereIn('name', ['Online', 'Púť'])->pluck('id');

        DB::table('event_tag')
            ->where('event_id', self::BADIN_EVENT_ID)
            ->whereIn('tag_id', $tagIds)
            ->whereIn('source', ['ai', 'derived'])
            ->delete();
    }
};
