<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vráti na prepis podujatia, ktorým ako „AI popis" ostal plochý extrakt.
 *
 * `app:ai-detector` do 31. 8. 2026 pri zlyhaní copywritera uložil do `body_ai`
 * surový extrakt stránky — jeden riadok textu bez odsekov. Konsolidácia
 * (EventBodyConsolidator) ho potom presunula do `body` a nastavila
 * `body_rewritten_at`, takže detektor sa k nim už nevráti. Na dev dátach je to
 * ~200 podujatí; pôvodný zoškrabaný popis majú v `meta.imported_raw_body`
 * a vo väčšine je bohatší (odseky, zoznamy) než to, čo sa zobrazuje.
 *
 * Podujatie dostane späť pôvodný popis a `body_rewritten_at = null`, takže ho
 * detektor prepíše znova — dnes už s povinnou sadzbou do HTML
 * (HtmlBodyFinisher). Keď zdroj medzitým zmizol, ostane aspoň pôvodný popis,
 * ktorý je lepší než plochý extrakt. Tempo je jedno podujatie za minútu.
 *
 * Plochý = žiadny nadpis, zoznam ani <strong> a najviac jeden odsek. Také
 * telo copywriter nevyrobí nikdy (má predpísané tri sekcie s <h3>).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'body_rewritten_at')) {
            return;
        }

        DB::table('events')
            ->whereNotNull('body_rewritten_at')
            ->whereNotNull('meta')
            ->select(['id', 'body', 'meta'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) $row->meta, true);
                    $raw = is_array($meta) ? ($meta['imported_raw_body'] ?? null) : null;

                    if (! is_string($raw) || trim($raw) === '' || ! $this->isFlat((string) $row->body)) {
                        continue;
                    }

                    // Staré zlyhania by nový pokus odložili alebo zablokovali.
                    if (isset($meta['ai_detector']) && is_array($meta['ai_detector'])) {
                        unset($meta['ai_detector']['skipped_at'], $meta['ai_detector']['retry_at'], $meta['ai_detector']['attempts']);
                    }

                    DB::table('events')->where('id', $row->id)->update([
                        'body' => $raw,
                        'body_rewritten_at' => null,
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Plochý extrakt nemá cenu vracať — nič iné než pôvodný popis sa v ňom
     * nenachádzalo. Zámerne prázdne.
     */
    public function down(): void {}

    private function isFlat(string $body): bool
    {
        return preg_match('/<(?:h[2-4]|ul|ol|strong)\b/i', $body) !== 1
            && preg_match_all('/<p\b/i', $body) <= 1;
    }
};
