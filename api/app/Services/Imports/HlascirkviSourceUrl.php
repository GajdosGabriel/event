<?php

namespace App\Services\Imports;

use Illuminate\Support\Str;

/**
 * Deduplikačný kľúč archívu z hlascirkvi.
 *
 * Podujatia sa v novom modeli nespájajú cez legacy id (`meta` nie je
 * indexované), ale cez `events.orginal_source`. Staršie záznamy zdrojovú URL
 * nemajú — scraper ju vtedy ešte nezapisoval — takže sa doplní adresa detailu
 * na starom webe. Je to funkčný odkaz aj stabilný kľúč pre opakovaný beh.
 *
 * Kľúč počíta import podujatí aj import obrázkov. Keby sa tie dva výpočty
 * rozišli, obrázky by sa nemali k čomu pripojiť, preto je logika tu a nie
 * skopírovaná v oboch príkazoch.
 */
class HlascirkviSourceUrl
{
    /**
     * @param  array<string, mixed>  $row  riadok z JSONL exportu
     */
    public static function for(array $row, ?string $baseUrl = null): string
    {
        $source = $row['orginal_source'] ?? null;
        if (is_string($source) && trim($source) !== '') {
            return trim($source);
        }

        $baseUrl ??= (string) config('services.imports.legacy.hlascirkvi_base_url');
        $slug = $row['slug'] ?: Str::slug((string) ($row['title'] ?? ''));

        return rtrim($baseUrl, '/')."/akcie/{$row['legacy_id']}/{$slug}";
    }
}
