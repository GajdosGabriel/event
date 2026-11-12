<?php

namespace App\Jobs;

use App\Models\Canal;
use App\Services\Canals\CanalEmailFinder;
use App\Services\Geocoding\PlaceCoordinateBackfill;
use App\Services\Lookups\ManualLookups;
use App\Services\Profiles\ProfileEnricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hľadanie, ktoré admin spustil ručne z detailu kanála (ManualLookups).
 *
 * Beží vo fronte: dopĺňanie profilu a hľadanie e-mailu trvajú desiatky
 * sekúnd (web, AI) a request na ne čakať nemá. Ručné spustenie obíde
 * odtlačok podkladov a limity pokusov; vypínače a rozpočet AI platia.
 */
class RunManualLookup implements ShouldQueue
{
    use Queueable;

    /** Opakovanie by znova platilo za AI — admin ho spustí sám, ak treba. */
    public int $tries = 1;

    public function __construct(
        public readonly int $canalId,
        public readonly string $kind,
    ) {}

    public function handle(
        ProfileEnricher $profiles,
        PlaceCoordinateBackfill $coordinates,
        CanalEmailFinder $emails,
        ManualLookups $lookups,
    ): void {
        try {
            $canal = Canal::withTrashed()->find($this->canalId);

            if ($canal === null) {
                return;
            }

            match ($this->kind) {
                ManualLookups::PROFILE => $profiles->force($canal),
                ManualLookups::COORDINATES => $coordinates->backfill($canal, force: true),
                ManualLookups::EMAIL => $emails->run($canal, manual: true),
            };
        } finally {
            $lookups->finished($this->canalId, $this->kind);
        }
    }

    public function failed(): void
    {
        app(ManualLookups::class)->finished($this->canalId, $this->kind);
    }
}
