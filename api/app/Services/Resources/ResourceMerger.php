<?php

namespace App\Services\Resources;

use App\Models\{Canal, Venue, SystemLog};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/** Deliberately conservative: an unknown reference blocks the whole transaction. */
class ResourceMerger
{
    public function merge(string $kind, int $sourceId, int $targetId, int $actorId): void
    {
        if ($sourceId === $targetId) $this->conflict('same');
        $class = $kind === 'canals' ? Canal::class : Venue::class;
        $key = $kind === 'canals' ? 'canal_id' : 'venue_id';
        DB::transaction(function () use ($class, $key, $sourceId, $targetId, $actorId) {
            $rows = $class::query()->whereIn('id', [$sourceId, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($rows->count() === 2, 404);
            $source = $rows[$sourceId];
            $target = $rows[$targetId];
            if ($source->status !== $target->status) $this->conflict('status');
            if ($class === Canal::class) {
                if ($source->organization_id !== $target->organization_id || $source->identity_mode !== $target->identity_mode) $this->conflict('identity');
                $this->sameLinks('canal_user', 'canal_id', $sourceId, $targetId, ['user_id', 'is_owner', 'status', 'role']);
            } else {
                // A merge must not silently grant a different channel access to a venue.
                $this->sameLinks('canal_venue', 'venue_id', $sourceId, $targetId, ['canal_id', 'is_owner', 'status']);
            }
            $supported = ['events', 'canal_venue'];
            if ($class === Canal::class) $supported = [...$supported, 'canal_user', 'users'];
            // Include legacy non-FK references and morph aliases as well as class names.
            foreach (Schema::getTables() as $table) {
                $name = $table['name'];
                $columns = Schema::getColumnListing($name);
                if (in_array($key, $columns, true) && !in_array($name, $supported, true)
                    && DB::table($name)->where($key, $sourceId)->lockForUpdate()->exists()) {
                    $this->conflict('dependency', $name);
                }
                foreach ($columns as $column) {
                    if (!str_ends_with($column, '_type')) continue;
                    $idColumn = substr($column, 0, -5).'_id';
                    // History and source-content checks remain attached to the soft-deleted source.
                    // They must not describe the target, whose content is preserved.
                    if (in_array($name, ['system_logs', 'attribute_checks', 'ai_usages'], true) || !in_array($idColumn, $columns, true)) continue;
                    if (DB::table($name)->whereIn($column, [$class, $source->getMorphClass()])
                        ->where($idColumn, $sourceId)->lockForUpdate()->exists()) $this->conflict('dependency', $name);
                }
            }
            $moved = [];
            $other = $key === 'canal_id' ? 'venue_id' : 'canal_id';
            $links = DB::table('canal_venue')->where($key, $sourceId)->lockForUpdate()->get();
            foreach ($links as $link) {
                $existing = DB::table('canal_venue')->where($key, $targetId)->where($other, $link->$other)->lockForUpdate()->first();
                if ($existing && ($existing->is_owner != $link->is_owner || $existing->status !== $link->status)) $this->conflict('links');
                if (!$existing) {
                    $copy = (array) $link;
                    $copy[$key] = $targetId;
                    DB::table('canal_venue')->insert($copy);
                }
            }
            $moved['canal_venue'] = $links->map(fn ($row) => (array) $row)->all();
            DB::table('canal_venue')->where($key, $sourceId)->delete();
            $moved['events'] = DB::table('events')->where($key, $sourceId)->lockForUpdate()->pluck('id')->all();
            DB::table('events')->where($key, $sourceId)->update([$key => $targetId, 'updated_at' => now()]);
            if ($class === Canal::class) {
                $moved['users'] = DB::table('users')->where('canal_id', $sourceId)->lockForUpdate()->pluck('id')->all();
                DB::table('users')->where('canal_id', $sourceId)->update(['canal_id' => $targetId]);
                $moved['canal_user'] = DB::table('canal_user')->where('canal_id', $sourceId)->get()->map(fn ($row) => (array) $row)->all();
                DB::table('canal_user')->where('canal_id', $sourceId)->delete();
            }
            $source->delete();
            // Audit is part of the transaction: a failure must roll the merge back.
            SystemLog::create([
                'level' => 'info', 'channel' => 'admin', 'event' => 'resource.merged', 'status' => 'ok',
                'message' => 'Resource merged', 'user_id' => $actorId,
                'subject_type' => $target->getMorphClass(), 'subject_id' => $targetId,
                'context' => ['source_id' => $sourceId, 'target_id' => $targetId, 'resource' => $class, 'transferred' => $moved],
            ]);
        });
    }

    private function sameLinks(string $table, string $key, int $source, int $target, array $fields): void
    {
        $read = fn ($id) => DB::table($table)->where($key, $id)->orderBy($fields[0])->lockForUpdate()->get($fields)->toJson();
        if ($read($source) !== $read($target)) $this->conflict('permissions');
    }

    private function conflict(string $reason, string $table = ''): never
    {
        throw ValidationException::withMessages(['target_id' => __('merge.'.$reason, ['table' => $table])]);
    }
}
