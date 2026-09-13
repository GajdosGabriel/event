<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zmaže web zdroja (vyveska.sk, tkkbs.sk) u importovaných organizátorov.
 *
 * ImportedCanalManager dával každému kanálu, ktorý import založil, `website`
 * = doména zdroja. Organizátor „Misijná škola Karola Wojtylu" tak mal v
 * kontakte „www.vyveska.sk". Tá patrí len zbernému kanálu `vyveska.sk`.
 *
 * Mažú sa len holé domény (bez cesty) a len na kanáloch z importu, ktoré sa
 * nevolajú po doméne. ecav.sk sa zámerne nechytá: pre evanjelické inštitúcie
 * môže byť skutočným webom.
 */
return new class extends Migration
{
    private const SOURCE_HOSTS = ['vyveska.sk', 'tkkbs.sk'];

    public function up(): void
    {
        if (! Schema::hasTable('canals')) {
            return;
        }

        DB::table('canals')
            ->where('registration_source', 'import')
            ->whereNotNull('website')
            ->select(['id', 'name', 'website'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if ($this->looksLikeHost((string) $row->name) || ! $this->isBareSourceOrigin((string) $row->website)) {
                        continue;
                    }

                    DB::table('canals')->where('id', $row->id)->update([
                        'website' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Pôvodná hodnota nebola údajom o organizátorovi. Zámerne prázdne.
     */
    public function down(): void {}

    private function isBareSourceOrigin(string $website): bool
    {
        $website = trim($website);
        $url = preg_match('#^https?://#i', $website) ? $website : 'https://'.$website;
        $host = preg_replace('/^www\./', '', mb_strtolower((string) parse_url($url, PHP_URL_HOST))) ?? '';
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path === '' && parse_url($url, PHP_URL_QUERY) === null && in_array($host, self::SOURCE_HOSTS, true);
    }

    /** Kópia App\Services\Imports\OrganizerName::looksLikeHost — migrácia nesmie závisieť od aplikačného kódu. */
    private function looksLikeHost(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = preg_replace('/^www\./', '', $value) ?? $value;

        return (bool) preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', rtrim($value, '/'));
    }
};
