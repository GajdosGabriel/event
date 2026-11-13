<?php

use App\Models\Venue;
use App\Services\Resources\ResourceMerger;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\Migrations\Migration;

/**
 * Dokončenie zlúčení miest, ktoré v dávkach 2 – 4 zablokovali priložené obrázky
 * (ResourceMerger ich vtedy nevedel presunúť, preto sa duplikáty len premenovali).
 * Teraz sa obrázky zdroja presunú k cieľu a cieľ si ponechá svoj hlavný obrázok.
 *
 *  - #985 Dom Quo Vadis (Hurbanovo námestie 1) → #133 Dom Quo Vadis,
 *  - #832 Bazilika Sedembolestnej Panny Márie (Šaštín) → #104 Bazilika Sedembolestnej Panny Márie,
 *  - #130 Koncertná sieň Klarisky (Klariská ulica) → #996 Koncertná sieň Klarisky.
 *
 * Zdroj aj cieľ musia sedieť ID aj názvom; zlyhanie sa zapíše a migrácia pokračuje.
 */
return new class extends Migration
{
    private const MERGES = [
        985 => [133, 'Dom Quo Vadis (Hurbanovo námestie 1)', 'Dom Quo Vadis'],
        832 => [104, 'Bazilika Sedembolestnej Panny Márie (Šaštín)', 'Bazilika Sedembolestnej Panny Márie'],
        130 => [996, 'Koncertná sieň Klarisky (Klariská ulica)', 'Koncertná sieň Klarisky'],
    ];

    public function up(): void
    {
        foreach (self::MERGES as $sourceId => [$targetId, $sourceName, $targetName]) {
            $source = Venue::query()->find($sourceId);
            $target = Venue::query()->find($targetId);
            if (! $source || ! $target || $source->name !== $sourceName || $target->name !== $targetName) {
                continue;
            }
            try {
                app(ResourceMerger::class)->merge('venues', $sourceId, $targetId, null);
            } catch (Throwable $e) {
                Recorder::warning('admin', 'resource.merge_skipped', "Zlúčenie miesta #{$sourceId} → #{$targetId} preskočené",
                    subject: $target, context: ['error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }
    }

    public function down(): void
    {
        // Zlúčenie sa nevracia.
    }
};
