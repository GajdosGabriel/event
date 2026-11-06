<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zlúči kanály, ktoré import založil znova len preto, že AI napísala skratku
 * rozpísane („KBS" / „Konferencie biskupov Slovenska", „SR" / „Slovenskej
 * republiky", „MV SR", „ECAV") alebo slová v inom poradí („Rada KBS pre
 * rodinu" / „Rada pre rodinu KBS", „Sigord - Centrum pre rodinu").
 *
 * Príčinu odstraňuje ImportedNameMatcher (ABBREVIATIONS a zhoda slov v inom
 * poradí); tieto dvojice našiel nový kľúč nad produkčnými dátami 6. 11. 2026
 * a každá bola posúdená ručne. Zlučuje sa rovnako ako
 * 2026_11_06_100000_merge_duplicate_canals_from_november_audit.php.
 *
 * Kanonický kanál = ten s najviac podujatiami a vyplneným kontaktom.
 * down() duplicity obnoví; presuny sa nevracajú.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0:int, 1:list<int>}> [kanonický_id, [duplicitné_id, ...]]
     */
    private const MERGES = [
        [9, [81, 1593]], // Rada pre mládež a univerzity KBS
        [19, [1089]], // Ekumenický výbor ECAV na Slovensku
        [74, [614]], // Sociálna subkomisia Teologickej komisie KBS
        [196, [127]], // Sigord - Centrum pre rodinu
        [200, [131]], // Centrum pre rodinu Trenčín
        [322, [320]], // Environmentálna subkomisia KBS
        [441, [552]], // Ordinariát Ozbrojených síl a ozbrojených zborov SR
        [485, [518, 1439, 1489]], // Rada KBS pre rodinu
        [583, [890]], // Liturgická komisia KBS
        [1208, [751]], // Nádej - spoločenstvo slobodných
        [1395, [787]], // Centrum pre rodinu Pezinok
        [806, [1117]], // Generálny sekretariát KBS
        [995, [878]], // Úrad ekumenickej pastoračnej služby MV SR
        [952, [1001]], // Subkomisia pre bioetiku Teologickej komisie KBS
        [1107, [1573]], // Ministerstvo kultúry SR
    ];

    /** Duplicity s kontaktom iného subjektu — z nich sa nič nedopĺňa. */
    private const FOREIGN_CONTACT = [];

    private const FILLABLE = ['municipality_id', 'street', 'postcode', 'country', 'phone', 'website', 'body', 'title_prefix', 'title_suffix'];

    /** Tabuľky, ktorých canal_id sa jednoducho prepíše na kanonický kanál. */
    private const MOVABLE = ['events', 'event_series', 'canal_invitations', 'canal_outreach', 'canal_relay_threads', 'support_tickets', 'users'];

    public function up(): void
    {
        if (! Schema::hasTable('canals') || ! Schema::hasTable('canal_venue')) {
            return;
        }

        foreach (self::MERGES as [$canonicalId, $duplicateIds]) {
            DB::transaction(function () use ($canonicalId, $duplicateIds) {
                $merged = [];

                foreach ($duplicateIds as $duplicateId) {
                    if ($this->merge($canonicalId, $duplicateId)) {
                        $merged[] = $duplicateId;
                    }
                }

                if ($merged === []) {
                    return;
                }

                $this->settleDecisions([$canonicalId, ...$merged]);

                if (Schema::hasTable('canal_emails') && ($canonical = Canal::query()->find($canonicalId)) !== null) {
                    app(CanalEmails::class)->refresh($canonical);
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        $duplicateIds = collect(self::MERGES)->flatMap(fn ($merge) => $merge[1])->all();

        $restore = ['deleted_at' => null, 'updated_at' => now()];

        if (Schema::hasColumn('canals', 'merged_into_id')) {
            $restore['merged_into_id'] = null;
        }

        DB::table('canals')->whereIn('id', $duplicateIds)->update($restore);
    }

    private function merge(int $canonicalId, int $duplicateId): bool
    {
        $canonical = DB::table('canals')->where('id', $canonicalId)->whereNull('deleted_at')->first();
        $duplicate = DB::table('canals')->where('id', $duplicateId)->whereNull('deleted_at')->first();

        if ($canonical === null || $duplicate === null || ! $this->safeToMerge($canonical, $duplicate)) {
            return false;
        }

        $update = ['updated_at' => now()];
        $fill = ! in_array($duplicateId, self::FOREIGN_CONTACT, true);

        foreach (self::FILLABLE as $column) {
            if ($fill && $this->blank($canonical->{$column}) && ! $this->blank($duplicate->{$column})) {
                $update[$column] = $duplicate->{$column};
            }
        }

        if ($fill && $canonical->latitude === null && $duplicate->latitude !== null) {
            $update['latitude'] = $duplicate->latitude;
            $update['longitude'] = $duplicate->longitude;
            $update['coordinates_source'] = $duplicate->coordinates_source;
        }

        $update['views_count'] = (int) $canonical->views_count + (int) $duplicate->views_count;

        DB::table('canals')->where('id', $canonicalId)->update($update);

        foreach (self::MOVABLE as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('canal_id', $duplicateId)->update(['canal_id' => $canonicalId]);
            }
        }

        $this->moveVenueLinks($canonicalId, $duplicateId);
        $this->moveEmails($canonicalId, $duplicateId, $fill);

        $this->moveViews($duplicateId, $canonicalId);

        if (Schema::hasTable('content_reviews')) {
            DB::table('content_reviews')->where('reviewable_type', Canal::class)
                ->whereIn('reviewable_id', [$canonicalId, $duplicateId])->delete();
        }

        if (Schema::hasTable('attribute_checks')) {
            DB::table('attribute_checks')->where('checkable_type', Canal::class)
                ->where('checkable_id', $duplicateId)->delete();
        }

        DB::table('canal_user')->where('canal_id', $duplicateId)->delete();
        $trash = ['deleted_at' => now(), 'updated_at' => now()];

        // Názov duplicity ostane aliasom kanonického kanála (DuplicateResolver::canalByKey).
        if (Schema::hasColumn('canals', 'merged_into_id')) {
            $trash['merged_into_id'] = $canonicalId;
        }

        DB::table('canals')->where('id', $duplicateId)->update($trash);

        return true;
    }

    /**
     * Zlučujú sa len importované kanály, ktoré nikto neprevzal a na ktoré nemá
     * prístup nikto, kto by ho nemal aj na kanonický kanál.
     */
    private function safeToMerge(object $canonical, object $duplicate): bool
    {
        if ($duplicate->registration_source !== 'import' || $duplicate->claimed_at !== null
            || $duplicate->organization_id !== $canonical->organization_id) {
            return false;
        }

        $canonicalUsers = DB::table('canal_user')->where('canal_id', $canonical->id)->pluck('user_id')->all();

        if (DB::table('canal_user')->where('canal_id', $duplicate->id)->whereNotIn('user_id', $canonicalUsers)->exists()) {
            return false;
        }

        foreach (['canal_claims', 'canal_notification_settings'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('canal_id', $duplicate->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    private function moveVenueLinks(int $canonicalId, int $duplicateId): void
    {
        foreach (DB::table('canal_venue')->where('canal_id', $duplicateId)->get() as $link) {
            $existing = DB::table('canal_venue')
                ->where('canal_id', $canonicalId)->where('venue_id', $link->venue_id)->first();

            if ($existing === null) {
                DB::table('canal_venue')->insert([
                    'canal_id' => $canonicalId,
                    'venue_id' => $link->venue_id,
                    'is_owner' => $link->is_owner,
                    'status' => $link->status,
                    'created_at' => $link->created_at,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('canal_venue')
                    ->where('canal_id', $canonicalId)->where('venue_id', $link->venue_id)
                    ->update([
                        'is_owner' => max($existing->is_owner, $link->is_owner),
                        'status' => $existing->status === 'published' || $link->status === 'published' ? 'published' : $existing->status,
                        'updated_at' => now(),
                    ]);
            }
        }

        DB::table('canal_venue')->where('canal_id', $duplicateId)->delete();
    }

    /** Adresy duplicity pribudnú ako ďalšie; primárnu potom vyberie CanalEmails. */
    private function moveEmails(int $canonicalId, int $duplicateId, bool $move): void
    {
        if (! Schema::hasTable('canal_emails')) {
            return;
        }

        $known = DB::table('canal_emails')->where('canal_id', $canonicalId)->pluck('email')->all();
        $room = $move ? max(0, CanalEmails::MAX - count($known)) : 0;

        $moved = $room === 0 ? [] : DB::table('canal_emails')->where('canal_id', $duplicateId)->whereNotIn('email', $known)
            ->orderBy('id')->limit($room)->pluck('id')->all();

        DB::table('canal_emails')->whereIn('id', $moved)->update(['canal_id' => $canonicalId, 'is_primary' => false, 'updated_at' => now()]);
        DB::table('canal_emails')->where('canal_id', $duplicateId)->delete();
    }

    /**
     * Návštevník, ktorý v ten deň videl oba kanály, je jedno zobrazenie
     * (unikátny index views_unique_per_day) — jeho riadok duplicity sa zahodí.
     */
    private function moveViews(int $fromId, int $toId): void
    {
        if (! Schema::hasTable('views')) {
            return;
        }

        $conflicting = DB::table('views as d')
            ->join('views as k', fn ($join) => $join->on('k.visitor_hash', '=', 'd.visitor_hash')->on('k.viewed_on', '=', 'd.viewed_on'))
            ->where('d.viewable_type', Canal::class)->where('d.viewable_id', $fromId)
            ->where('k.viewable_type', Canal::class)->where('k.viewable_id', $toId)
            ->pluck('d.id')->all();

        DB::table('views')->whereIn('id', $conflicting)->delete();
        DB::table('views')->where('viewable_type', Canal::class)->where('viewable_id', $fromId)
            ->update(['viewable_id' => $toId]);
    }

    /** Nerozhodnuté import-duplicity medzi kanálmi skupiny sú týmto vybavené. */
    private function settleDecisions(array $groupIds): void
    {
        if (! Schema::hasTable('duplicate_decisions')) {
            return;
        }

        DB::table('duplicate_decisions')
            ->where('entity', 'canal')
            ->whereNull('reviewed_at')
            ->whereIn('candidate_id', $groupIds)
            ->whereIn('created_id', $groupIds)
            ->update(['reviewed_at' => now(), 'updated_at' => now()]);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '' || in_array(strtolower(trim((string) $value)), ['null', '[]'], true);
    }
};
