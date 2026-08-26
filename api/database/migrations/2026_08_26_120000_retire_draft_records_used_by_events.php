<?php

use App\Enums\ModelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Koncept, na ktorý sa už odvoláva podujatie, konceptom byť nesmie.
 *
 * Koncept znamená „ešte to nikto nevidel" — lenže miesto či kanál, ktorý si
 * vybralo podujatie, videli všetci, čo si to podujatie otvorili. Import zakladal
 * miesta ako `draft` (ImportedVenueManager) a stav tam ostal navždy, takže
 * dashboard ukazoval katalóg samých konceptov a UnpublishGuard — postavený proti
 * ceste *do* konceptu — nemal čo strážiť, lebo tam už boli.
 *
 * Kam ktorý riadok patrí, rozhoduje jeho vlastná história, rovnako ako pri
 * podujatiach (app:events-archive-finished):
 *
 *  - má pred sebou ešte nejaké podujatie -> `published`, lebo sa tam koná,
 *  - má za sebou už len minulé -> `archived`, teda „mimo prevádzky", ale
 *    dohľadateľné, aby odkaz z minuloročnej akcie nikam nespadol.
 *
 * Zmazané podujatia (soft delete) držia záznam pri živote — preto rozhodujú
 * o tom, že sa dorovnáva — ale o tom, že sa tam ešte koná, nevypovedajú, takže
 * do voľby medzi published a archived nevstupujú.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->retire('venues', 'venue_id');
        $this->retire('canals', 'canal_id');
    }

    /**
     * Späť sa to vrátiť nedá — pôvodný `draft` bol práve tá nezrovnalosť, ktorú
     * migrácia opravuje, a ktorý riadok bol ktorý, sa už nezistí.
     */
    public function down(): void {}

    private function retire(string $table, string $foreignKey): void
    {
        // Poradie je podstatné: publikované riadky prvým krokom prestanú byť
        // konceptom, takže do druhého už nespadnú a archív dostane presne
        // zvyšok — tie, čo majú podujatia len za sebou.
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

            // `canals` má stĺpec `published_at`, `venues` nie. Raz zapísaný čas
            // je história prvého zverejnenia, preto sa dopĺňa len tam, kde chýba.
            if ($table === 'canals' && $status === ModelStatus::Published) {
                DB::table($table)
                    ->whereIn('id', $chunk)
                    ->whereNull('published_at')
                    ->update(['published_at' => now()]);
            }
        }
    }
};
