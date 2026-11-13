<?php

namespace App\Services\Resources;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\SystemLog;
use App\Models\Venue;
use App\Services\Canals\CanalEmails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Deliberately conservative: an unknown reference blocks the whole transaction. */
class ResourceMerger
{
    /**
     * History that describes the source itself and stays attached to the soft-deleted
     * source: logs, checks and reviews of its content, AI cost records and profile
     * enrichment audits, raw page views. They must not describe the target, whose
     * content is preserved.
     */
    private const HISTORY = ['system_logs', 'attribute_checks', 'ai_usages', 'content_reviews', 'views', 'profile_enrichments'];

    public function merge(string $kind, int $sourceId, int $targetId, ?int $actorId): void
    {
        if ($sourceId === $targetId) {
            $this->conflict('same');
        }
        $class = $kind === 'canals' ? Canal::class : Venue::class;
        $key = $kind === 'canals' ? 'canal_id' : 'venue_id';
        DB::transaction(function () use ($class, $key, $sourceId, $targetId, $actorId) {
            $rows = $class::query()->whereIn('id', [$sourceId, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($rows->count() === 2, 404);
            $source = $rows[$sourceId];
            $target = $rows[$targetId];
            if ($source->status !== $target->status) {
                $this->conflict('status');
            }
            if ($class === Canal::class) {
                if ($source->organization_id !== $target->organization_id || $source->identity_mode !== $target->identity_mode) {
                    $this->conflict('identity');
                }
                $this->sameLinks('canal_user', 'canal_id', $sourceId, $targetId, ['user_id', 'is_owner', 'status', 'role']);
            }
            // A venue is a shared record of a real place: duplicates typically come from
            // two different channels, so their links are united instead of compared (below).
            $supported = ['events', 'canal_venue'];
            if ($class === Canal::class) {
                $supported = [...$supported, 'canal_user', 'users', 'canal_emails', 'canal_email_searches'];
            }
            // Include legacy non-FK references and morph aliases as well as class names.
            foreach (Schema::getTables() as $table) {
                $name = $table['name'];
                $columns = Schema::getColumnListing($name);
                if (in_array($key, $columns, true) && ! in_array($name, [...$supported, ...self::HISTORY], true)
                    && DB::table($name)->where($key, $sourceId)->lockForUpdate()->exists()) {
                    $this->conflict('dependency', $name);
                }
                foreach ($columns as $column) {
                    if (! str_ends_with($column, '_type')) {
                        continue;
                    }
                    $idColumn = substr($column, 0, -5).'_id';
                    // Files are moved to the target below (moveFiles).
                    if ($name === 'files' || in_array($name, self::HISTORY, true) || ! in_array($idColumn, $columns, true)) {
                        continue;
                    }
                    if (DB::table($name)->whereIn($column, [$class, $source->getMorphClass()])
                        ->where($idColumn, $sourceId)->lockForUpdate()->exists()) {
                        $this->conflict('dependency', $name);
                    }
                }
            }
            $moved = [];
            $other = $key === 'canal_id' ? 'venue_id' : 'canal_id';
            $links = DB::table('canal_venue')->where($key, $sourceId)->lockForUpdate()->get();
            if ($class === Venue::class) {
                $this->uniteVenueLinks($links, $targetId);
            } else {
                $this->copyCanalLinks($links, $key, $other, $targetId);
            }
            DB::table('canal_venue')->where($key, $sourceId)->delete();
            $moved['events'] = DB::table('events')->where($key, $sourceId)->lockForUpdate()->pluck('id')->all();
            DB::table('events')->where($key, $sourceId)->update([$key => $targetId, 'updated_at' => now()]);
            $moved['files'] = $this->moveFiles([$class, $source->getMorphClass()], $sourceId, $targetId);
            if ($class === Canal::class) {
                $moved['users'] = DB::table('users')->where('canal_id', $sourceId)->lockForUpdate()->pluck('id')->all();
                DB::table('users')->where('canal_id', $sourceId)->update(['canal_id' => $targetId]);
                // Addresses of the source become additional ones; the target keeps its primary.
                $known = DB::table('canal_emails')->where('canal_id', $targetId)->lockForUpdate()->pluck('email')->all();
                $moved['canal_emails'] = DB::table('canal_emails')->where('canal_id', $sourceId)->whereNotIn('email', $known)->lockForUpdate()->pluck('id')->all();
                DB::table('canal_emails')->whereIn('id', $moved['canal_emails'])->update(['canal_id' => $targetId, 'is_primary' => false]);
                DB::table('canal_emails')->where('canal_id', $sourceId)->delete();
                // Derived state of the address search (CanalEmailFinder) — the target keeps its own.
                DB::table('canal_email_searches')->where('canal_id', $sourceId)->delete();
                app(CanalEmails::class)->refresh($target);
                $moved['canal_user'] = DB::table('canal_user')->where('canal_id', $sourceId)->get()->map(fn ($row) => (array) $row)->all();
                DB::table('canal_user')->where('canal_id', $sourceId)->delete();
                // The source name stays an alias of the target for future imports.
                DB::table('canals')->where('id', $sourceId)->update(['merged_into_id' => $targetId]);
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

    /** A merged canal keeps exactly the venue links it had; a differing duplicate link is a conflict. */
    private function copyCanalLinks($links, string $key, string $other, int $targetId): void
    {
        foreach ($links as $link) {
            $existing = DB::table('canal_venue')->where($key, $targetId)->where($other, $link->$other)->lockForUpdate()->first();
            if ($existing && ($existing->is_owner != $link->is_owner || $existing->status !== $link->status)) {
                $this->conflict('links');
            }
            if (! $existing) {
                $copy = (array) $link;
                $copy[$key] = $targetId;
                DB::table('canal_venue')->insert($copy);
            }
        }
    }

    /**
     * Every channel that used the source keeps using the same place through the target.
     * The target's steward stays the steward; the source's steward takes over only when
     * the target has none, otherwise it becomes a user (it can still pick the venue,
     * no longer edit it). A published link wins over a draft one — that channel
     * already saw the place.
     */
    private function uniteVenueLinks($links, int $targetId): void
    {
        $targetLinks = DB::table('canal_venue')->where('venue_id', $targetId)->lockForUpdate()->get()->keyBy('canal_id');
        $hasOwner = $targetLinks->contains(fn ($link) => (bool) $link->is_owner);
        foreach ($links as $link) {
            $owner = (bool) $link->is_owner && ! $hasOwner;
            $hasOwner = $hasOwner || $owner;
            $existing = $targetLinks->get($link->canal_id);
            if ($existing) {
                $published = ModelStatus::Published->value;
                DB::table('canal_venue')->where('venue_id', $targetId)->where('canal_id', $link->canal_id)->update([
                    'is_owner' => (bool) $existing->is_owner || $owner,
                    'status' => $existing->status === $published || $link->status === $published ? $published : $existing->status,
                    'updated_at' => now(),
                ]);

                continue;
            }
            DB::table('canal_venue')->insert([...(array) $link, 'venue_id' => $targetId, 'is_owner' => $owner]);
        }
    }

    /**
     * Pictures and attachments of the source continue on the target; only the database
     * reference changes, the stored objects stay where they are. The target keeps its
     * own primary picture and order — moved files are appended after them. A file the
     * target already has (same checksum) stays with the soft-deleted source.
     *
     * @param  array<int, string>  $types
     * @return array<int, int>
     */
    private function moveFiles(array $types, int $sourceId, int $targetId): array
    {
        $own = DB::table('files')->whereIn('fileable_type', $types)->where('fileable_id', $targetId)->lockForUpdate()->get();
        $checksums = $own->pluck('checksum')->filter()->all();
        $order = (int) $own->max('sort_order');
        $moved = [];
        $files = DB::table('files')->whereIn('fileable_type', $types)->where('fileable_id', $sourceId)
            ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
        foreach ($files as $file) {
            if ($file->checksum !== null && in_array($file->checksum, $checksums, true)) {
                continue;
            }
            // One primary per type among the target's live files.
            $hasPrimary = $own->contains(fn ($f) => $f->type === $file->type && $f->is_primary && $f->deleted_at === null);
            $primary = (bool) $file->is_primary && $file->deleted_at === null && ! $hasPrimary;
            DB::table('files')->where('id', $file->id)->update([
                'fileable_id' => $targetId, 'is_primary' => $primary, 'sort_order' => ++$order, 'updated_at' => now(),
            ]);
            $file->is_primary = $primary;
            $own->push($file);
            $checksums[] = $file->checksum;
            $moved[] = $file->id;
        }

        return $moved;
    }

    private function sameLinks(string $table, string $key, int $source, int $target, array $fields): void
    {
        $read = fn ($id) => DB::table($table)->where($key, $id)->orderBy($fields[0])->lockForUpdate()->get($fields)->toJson();
        if ($read($source) !== $read($target)) {
            $this->conflict('permissions');
        }
    }

    private function conflict(string $reason, string $table = ''): never
    {
        throw ValidationException::withMessages(['target_id' => __('merge.'.$reason, ['table' => $table])]);
    }
}
