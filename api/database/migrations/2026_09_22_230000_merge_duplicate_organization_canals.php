<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zlúči ~55 duplicitných neosobných kanálov zistených počas prieskumu
 * v predošlých backfill_contact_info_on_*_organization_canals.php
 * migráciách (rovnaká reálna organizácia importovaná viackrát pod mierne
 * odlišným názvom — napr. „OZ Bratislavská Kalvária" 6-krát, klub ISKRA
 * 8-krát) do jedného kanonického kanála.
 *
 * Overené pred spustením: všetkých ~55 duplicitných kanálov má
 * registration_source=import, organization_id=NULL (žiadna fakturačná
 * väzba) a jediný riadok v canal_user patrí administrátorskému účtu
 * (rovnakému na všetkých) — nie cudziemu registrovanému používateľovi.
 * Polymorfné tabuľky files/views/subscriptions/ai_usages/system_logs
 * v súčasných dátach vôbec neobsahujú záznamy typu Canal, preto sa tu
 * neriešia.
 *
 * Pre každú dvojicu (kanonický, [duplicitné...]):
 *  1. events.canal_id sa presunie na kanonický kanál,
 *  2. canal_venue väzby sa presunú (INSERT IGNORE kvôli zloženému PK,
 *     aby sa nekolidovalo, ak už kanonický tú istú venue má),
 *  3. canal_user riadok duplicitného kanála sa zmaže (vlastník má
 *     prístup už cez kanonický kanál),
 *  4. duplicitný kanál sa soft-deletne (deleted_at), nie fyzicky
 *     nevymaže — dá sa v prípade omylu obnoviť.
 *
 * down() vracia späť len soft-delete duplicitných kanálov (deleted_at =
 * null); presun eventov a venue väzieb naspäť na pôvodné duplicitné
 * kanály sa zámerne nevracia — rovnaký princíp jednosmerného prepisu ako
 * pri `body` v predošlých migráciách.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:int, 1:list<int>}> zoznam [kanonický_id, [duplicitné_id, ...]]
     */
    private const MERGES = [
        [789, [265]],
        [331, [679, 207]],
        [467, [450]],
        [36, [246]],
        [406, [854]],
        [568, [253]],
        [719, [577]],
        [63, [837]],
        [202, [938]],
        [926, [584, 595]],
        [566, [213]],
        [686, [355, 912, 233]],
        [903, [104]],
        [206, [223]],
        [602, [526, 132]],
        [675, [184, 440]],
        [775, [581]],
        [887, [317]],
        [680, [28]],
        [717, [389, 332]],
        [20, [14]],
        [902, [88]],
        [360, [976]],
        [1012, [530]],
        [698, [169, 356, 720, 738, 922]],
        [1031, [400]],
        [707, [617, 941, 414, 820, 793, 906, 447]],
        [302, [461, 783, 785]],
        [151, [408]],
        [688, [622]],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        foreach (self::MERGES as [$canonicalId, $duplicateIds]) {
            foreach ($duplicateIds as $duplicateId) {
                $this->mergeDuplicateIntoCanonical($duplicateId, $canonicalId);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        $allDuplicateIds = collect(self::MERGES)->flatMap(fn ($merge) => $merge[1])->all();

        // vracia len soft-delete (dá sa vrátiť); presun eventov/venue väzieb je jednosmerný
        DB::table('canals')->whereIn('id', $allDuplicateIds)->update(['deleted_at' => null, 'updated_at' => now()]);
    }

    private function mergeDuplicateIntoCanonical(int $duplicateId, int $canonicalId): void
    {
        $duplicate = DB::table('canals')->where('id', $duplicateId)->whereNull('deleted_at')->first();
        $canonical = DB::table('canals')->where('id', $canonicalId)->whereNull('deleted_at')->first();

        if ($duplicate === null || $canonical === null) {
            // niektorá strana už bola medzičasom zmenená/zmazaná - nič sa nerobí
            return;
        }

        DB::table('events')->where('canal_id', $duplicateId)->update(['canal_id' => $canonicalId, 'updated_at' => now()]);

        $venueLinks = DB::table('canal_venue')->where('canal_id', $duplicateId)->get();
        foreach ($venueLinks as $link) {
            DB::table('canal_venue')->insertOrIgnore([
                'canal_id' => $canonicalId,
                'venue_id' => $link->venue_id,
                'is_owner' => $link->is_owner,
                'status' => $link->status,
                'created_at' => $link->created_at,
                'updated_at' => now(),
            ]);
        }
        DB::table('canal_venue')->where('canal_id', $duplicateId)->delete();

        DB::table('canal_user')->where('canal_id', $duplicateId)->delete();

        DB::table('canals')->where('id', $duplicateId)->update(['deleted_at' => now(), 'updated_at' => now()]);
    }
};
