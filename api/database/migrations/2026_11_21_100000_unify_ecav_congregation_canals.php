<?php

use App\Models\Canal;
use App\Services\Canals\CanalEmails;
use App\Services\Imports\EcavCongregationName;
use App\Services\Imports\ImportedNameMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kanály cirkevných zborov ECAV na jeden tvar názvu a bez duplicít.
 *
 * Import ten istý zbor zapisoval podľa toho, ako ho napísal zdroj: „CZ ECAV
 * Poltár", „Cirkevný zbor ECAV na Slovensku Modra", „Evanjelický cirkevný
 * zbor …", a pre jeden zbor vznikali dva-tri kanály. Odteraz názov
 * zjednocuje EcavCongregationName (import aj kľúč pre párovanie); táto
 * migrácia rovnako upraví, čo už v databáze je:
 *
 *  1. importované kanály, ktoré nikto neprevzal, dostanú jednotný názov
 *     „CZ ECAV <obec>" — prevzatý kanál si názov určuje organizátor;
 *  2. kanály toho istého zboru (ImportedNameMatcher::canalKeysMatch, teda aj
 *     „v Sobotišti" / „Sobotište") sa zlúčia — rovnako ako v
 *     2026_11_07_100000_merge_canals_matched_by_abbreviation_and_word_order.php.
 *
 * Zostáva prevzatý kanál, inak ten s najviac podujatiami, pri zhode starší.
 * down() vráti pôvodné názvy a obnoví duplicity; presuny sa nevracajú.
 */
return new class extends Migration
{
    private const FILLABLE = ['municipality_id', 'street', 'postcode', 'country', 'phone', 'website', 'body', 'title_prefix', 'title_suffix'];

    /** Tabuľky, ktorých canal_id sa jednoducho prepíše na kanonický kanál. */
    private const MOVABLE = ['events', 'event_series', 'canal_invitations', 'canal_outreach', 'canal_relay_threads', 'support_tickets', 'users'];

    private const LOG_KEY = 'ecav_congregation_canals_2026_11_21';

    public function up(): void
    {
        if (! Schema::hasTable('canals') || ! Schema::hasTable('canal_venue')) {
            return;
        }

        $renamed = $this->renameImported();
        $merged = $this->mergeDuplicates();

        if (Schema::hasTable('cache')) {
            // Pre down(): pôvodné názvy a zlúčené duplicity.
            DB::table('cache')->updateOrInsert(
                ['key' => self::LOG_KEY],
                ['value' => json_encode(['renamed' => $renamed, 'merged' => $merged]), 'expiration' => now()->addYear()->getTimestamp()],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('canals') || ! Schema::hasTable('cache')) {
            return;
        }

        $log = json_decode((string) DB::table('cache')->where('key', self::LOG_KEY)->value('value'), true);
        if (! is_array($log)) {
            return;
        }

        $restore = ['deleted_at' => null, 'updated_at' => now()];
        if (Schema::hasColumn('canals', 'merged_into_id')) {
            $restore['merged_into_id'] = null;
        }
        DB::table('canals')->whereIn('id', (array) ($log['merged'] ?? []))->update($restore);

        foreach ((array) ($log['renamed'] ?? []) as $id => $name) {
            DB::table('canals')->where('id', (int) $id)->update(['name' => $name, 'slug' => Str::slug($name), 'updated_at' => now()]);
        }

        DB::table('cache')->where('key', self::LOG_KEY)->delete();
    }

    /** @return array<int, string> id => pôvodný názov */
    private function renameImported(): array
    {
        $renamed = [];

        $canals = DB::table('canals')
            ->whereNull('deleted_at')
            ->whereNull('claimed_at')
            ->where('registration_source', 'import')
            ->get(['id', 'name']);

        foreach ($canals as $canal) {
            $name = EcavCongregationName::normalize((string) $canal->name);

            if ($name === $canal->name) {
                continue;
            }

            DB::table('canals')->where('id', $canal->id)->update(['name' => $name, 'slug' => Str::slug($name), 'updated_at' => now()]);
            $renamed[$canal->id] = (string) $canal->name;
        }

        return $renamed;
    }

    /** @return list<int> zlúčené (zmazané) kanály */
    private function mergeDuplicates(): array
    {
        $congregations = DB::table('canals')
            ->whereNull('deleted_at')
            ->where('name', 'like', EcavCongregationName::PREFIX.' %')
            ->orderBy('id')
            ->get(['id', 'name', 'claimed_at'])
            ->map(fn ($canal) => [
                'id' => (int) $canal->id,
                'key' => ImportedNameMatcher::canalKey((string) $canal->name),
                'claimed' => $canal->claimed_at !== null,
                'events' => Schema::hasTable('events') ? DB::table('events')->where('canal_id', $canal->id)->count() : 0,
            ])
            ->all();

        $groups = [];
        foreach ($congregations as $canal) {
            foreach ($groups as $index => $group) {
                if (ImportedNameMatcher::canalKeysMatch($group[0]['key'], $canal['key'])) {
                    $groups[$index][] = $canal;

                    continue 2;
                }
            }
            $groups[] = [$canal];
        }

        $mergedIds = [];

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, fn (array $a, array $b) => [$b['claimed'], $b['events'], -$a['id']] <=> [$a['claimed'], $a['events'], -$b['id']]);
            $canonicalId = $group[0]['id'];

            DB::transaction(function () use ($group, $canonicalId, &$mergedIds) {
                $merged = [];

                foreach (array_slice($group, 1) as $duplicate) {
                    if ($this->merge($canonicalId, $duplicate['id'])) {
                        $merged[] = $duplicate['id'];
                    }
                }

                if ($merged === []) {
                    return;
                }

                $mergedIds = [...$mergedIds, ...$merged];
                $this->settleDecisions([$canonicalId, ...$merged]);

                if (Schema::hasTable('canal_emails') && ($canonical = Canal::query()->find($canonicalId)) !== null) {
                    app(CanalEmails::class)->refresh($canonical);
                }
            });
        }

        return $mergedIds;
    }

    private function merge(int $canonicalId, int $duplicateId): bool
    {
        $canonical = DB::table('canals')->where('id', $canonicalId)->whereNull('deleted_at')->first();
        $duplicate = DB::table('canals')->where('id', $duplicateId)->whereNull('deleted_at')->first();

        if ($canonical === null || $duplicate === null || ! $this->safeToMerge($canonical, $duplicate)) {
            return false;
        }

        $update = ['updated_at' => now()];

        foreach (self::FILLABLE as $column) {
            if ($this->blank($canonical->{$column}) && ! $this->blank($duplicate->{$column})) {
                $update[$column] = $duplicate->{$column};
            }
        }

        if ($canonical->latitude === null && $duplicate->latitude !== null) {
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
        $this->moveEmails($canonicalId, $duplicateId);
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
    private function moveEmails(int $canonicalId, int $duplicateId): void
    {
        if (! Schema::hasTable('canal_emails')) {
            return;
        }

        $known = DB::table('canal_emails')->where('canal_id', $canonicalId)->pluck('email')->all();
        $room = max(0, CanalEmails::MAX - count($known));

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
