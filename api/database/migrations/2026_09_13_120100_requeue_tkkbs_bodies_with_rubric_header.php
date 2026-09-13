<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vráti do `app:ai-detector` tkkbs.sk podujatia, ktorým copywriter vymyslel
 * miesto alebo termín z hlavičky článku.
 *
 * ContentExtractor posielal AI aj hlavičku „P:3, 06. 07. 2026 08:53, DOM"
 * (čas zverejnenia a rubrika Domáce). Z nej vznikali popisy
 * „uskutoční sa 6. júla 2026 o 08:53 v DOM Bratislava" (10807),
 * „v 17. ročníku BAŠky P:3, ktorý sa uskutoční 01. 07. 2026 o 11:05" (10790)
 * aj „Dňa 18. júna 2026 o 10:21 hod. sa v Bratislave uskutoční" (10732).
 * Hlavička sa už vyhadzuje, takže stačí popis prepísať znova — zo surového
 * textu importu.
 */
return new class extends Migration
{
    private const MONTHS = [
        1 => 'januára', 'februára', 'marca', 'apríla', 'mája', 'júna',
        'júla', 'augusta', 'septembra', 'októbra', 'novembra', 'decembra',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        DB::table('events')
            ->where('orginal_source', 'like', '%tkkbs.sk%')
            ->whereNotNull('body_rewritten_at')
            ->select(['id', 'body', 'meta', 'orginal_source'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if (! $this->mentionsHeader(strip_tags((string) $row->body), (string) $row->orginal_source)) {
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
     * Rubrika („DOM") a poradie („P:3") sú vždy verzálkami ako samostatné slovo.
     * Čas zverejnenia sa pozná podľa dňa článku z `cisloclanku=YYYYMMDDnnn`
     * s hodinou hneď za ním.
     */
    private function mentionsHeader(string $text, string $source): bool
    {
        if (preg_match('/\bDOM\b|\bP:\d+\b/u', $text)) {
            return true;
        }

        if (! preg_match('/cisloclanku=(\d{4})(\d{2})(\d{2})/', $source, $m)) {
            return false;
        }

        $day = (int) $m[3];
        $month = (int) $m[2];
        $date = '0?'.$day.'\.\s*(?:0?'.$month.'\.|'.self::MONTHS[$month].')\s*'.$m[1];

        return (bool) preg_match('/'.$date.'\s+(?:o\s+)?\d{1,2}[:.]\d{2}/iu', $text);
    }

    /**
     * Zámerne prázdne — detektor popis prepíše znova sám.
     */
    public function down(): void {}
};
