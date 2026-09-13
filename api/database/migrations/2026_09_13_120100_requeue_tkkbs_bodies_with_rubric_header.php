<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vráti do `app:ai-detector` tkkbs.sk podujatia, ktorým copywriter vymyslel
 * miesto z rubriky článku.
 *
 * ContentExtractor posielal AI aj hlavičku „P:3, 06. 07. 2026 08:53, DOM"
 * (čas zverejnenia a rubrika Domáce). Z nej vznikol popis „uskutoční sa
 * 6. júla 2026 o 08:53 v DOM Bratislava" (podujatie 10807). Hlavička sa už
 * vyhadzuje, takže stačí popis prepísať znova — zo surového textu importu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        DB::table('events')
            ->where('orginal_source', 'like', '%tkkbs.sk%')
            ->whereNotNull('body_rewritten_at')
            ->where('body', 'like', '%DOM%')
            ->select(['id', 'body', 'meta'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    // LIKE je v MySQL bez ohľadu na veľkosť písmen, rubrika je
                    // však vždy verzálkami ako samostatné slovo.
                    if (! preg_match('/\bDOM\b/u', strip_tags((string) $row->body))) {
                        continue;
                    }

                    $meta = json_decode((string) $row->meta, true);
                    $meta = is_array($meta) ? $meta : [];
                    $raw = $meta['imported_raw_body'] ?? null;

                    unset($meta['ai_detector']);

                    DB::table('events')->where('id', $row->id)->update(array_filter([
                        'body' => is_string($raw) && trim($raw) !== '' ? $raw : null,
                        'body_rewritten_at' => null,
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ], fn ($value, $key) => $key === 'body_rewritten_at' || $value !== null, ARRAY_FILTER_USE_BOTH));
                }
            });
    }

    /**
     * Zámerne prázdne — detektor popis prepíše znova sám.
     */
    public function down(): void {}
};
