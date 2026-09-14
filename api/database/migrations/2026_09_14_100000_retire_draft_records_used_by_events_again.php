<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Druhé kolo retire_draft_records_used_by_events.
 *
 * Prvé prebehlo 26. 8., lenže prenos archívu hlascirkvi (6. 9.) a AiDetector pri
 * jeho preraďovaní založili ďalších ~550 miest ako `draft` a nič ich nedorovnalo
 * — závislosti sa dopublikovávali len pri publikovanom podujatí. Odteraz to
 * rieši EventDependencyPublisher::settle(); táto migrácia dorovná, čo vzniklo
 * medzitým. Pravidlo je rovnaké:
 *
 *  - má pred sebou ešte nejaké podujatie -> `published`,
 *  - má za sebou už len minulé -> `archived`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->retire('venues', 'venue_id');
        $this->retire('canals', 'canal_id');
    }

    public function down(): void {}

    private function retire(string $table, string $foreignKey): void
    {
        $this->apply($table, $this->draftIdsUsedByEvents($table, $foreignKey, upcomingOnly: true), ModelStatus::Published);
        $this->apply($table, $this->draftIdsUsedByEvents($table, $foreignKey, upcomingOnly: false), ModelStatus::Archived);
    }

    /**
     * @return array<int, int>
     */
    private function draftIdsUsedByEvents(string $table, string $foreignKey, bool $upcomingOnly): array
    {
        return DB::table($table)
            ->where('status', ModelStatus::Draft->value)
            ->whereExists(function ($q) use ($table, $foreignKey, $upcomingOnly) {
                $q->select(DB::raw(1))
                    ->from('events')
                    ->whereColumn('events.' . $foreignKey, $table . '.id');

                if ($upcomingOnly) {
                    $q->whereNull('events.deleted_at')->where('events.start_at', '>=', now());
                }
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function apply(string $table, array $ids, ModelStatus $status): void
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table($table)
                ->whereIn('id', $chunk)
                ->update(['status' => $status->value, 'updated_at' => now()]);

            if ($table === 'canals' && $status === ModelStatus::Published) {
                DB::table($table)
                    ->whereIn('id', $chunk)
                    ->whereNull('published_at')
                    ->update(['published_at' => now()]);
            }
        }
    }
};
