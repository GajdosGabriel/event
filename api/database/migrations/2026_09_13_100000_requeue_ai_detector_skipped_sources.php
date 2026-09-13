<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vráti do `app:ai-detector` podujatia, ktorých sa vzdal pre nedostupný zdroj.
 *
 * Do 13. 9. 2026 sa detektor pri 404/410 alebo nečitateľnej stránke vzdal
 * natrvalo (`skipped_at`). Týka sa to hlavne archívu hlascirkvi.sk s odkazmi
 * na vyveska.sk z roku 2024. Dnes pri nedostupnom zdroji použije text z DB
 * a dopíše z neho popis, organizátora aj miesto — takže im treba dať druhú šancu.
 *
 * Podujatia, ktoré sa vzdali po piatich prechodných zlyhaniach (503, timeout),
 * ostávajú preskočené: fallback na uložený text sa na ne nevzťahuje.
 * Nečitateľná stránka stav nemá a vzdala sa na prvý pokus — preto `attempts < 5`.
 */
return new class extends Migration
{
    private const MAX_ATTEMPTS = 5;

    public function up(): void
    {
        DB::table('events')
            ->whereNotNull('meta')
            ->whereNotNull('meta->ai_detector->skipped_at')
            ->select(['id', 'meta'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);
                    $detector = is_array($meta) ? ($meta['ai_detector'] ?? null) : null;

                    if (! is_array($detector) || ! $this->sourceWasGone($detector)) {
                        continue;
                    }

                    unset($meta['ai_detector']['skipped_at'], $meta['ai_detector']['retry_at'], $meta['ai_detector']['attempts']);

                    DB::table('events')->where('id', $row->id)->update([
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Zámerne prázdne — preskočenie sa pri neúspechu nastaví znova samo.
     */
    public function down(): void {}

    /**
     * @param  array<string, mixed>  $detector
     */
    private function sourceWasGone(array $detector): bool
    {
        if (in_array((int) ($detector['source_http_status'] ?? 0), [404, 410], true)) {
            return true;
        }

        return ($detector['source_http_status'] ?? null) === null
            && (int) ($detector['attempts'] ?? self::MAX_ATTEMPTS) < self::MAX_ATTEMPTS;
    }
};
