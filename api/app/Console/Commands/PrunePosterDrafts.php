<?php

namespace App\Console\Commands;

use App\Models\PosterDraft;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Upratovanie po nahratiach, ktoré nikto nedotiahol do konca.
 *
 * Analýza beží bez účtu, takže veľká časť plagátov skončí bez majiteľa. Bez
 * tohto príkazu by nám na disku donekonečna ležali cudzie súbory a v DB
 * e-mailové adresy, ku ktorým sa nikto nikdy neprihlásil.
 */
class PrunePosterDrafts extends Command
{
    protected $signature = 'app:poster-drafts-prune';

    protected $description = 'Delete expired, unclaimed poster drafts and their uploaded files';

    public function handle(): int
    {
        $drafts = PosterDraft::query()
            ->whereNull('claimed_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $files = 0;

        foreach ($drafts as $draft) {
            if ($draft->file_disk !== null && $draft->file_path !== null) {
                // Súbor mažeme pred záznamom: keby zlyhal, cesta v DB ostane
                // a ďalší beh to skúsi znova. Opačné poradie by osirelý súbor
                // stratilo navždy.
                if (Storage::disk($draft->file_disk)->delete($draft->file_path)) {
                    $files++;
                }
            }

            $draft->delete();
        }

        $this->info("Deleted expired poster drafts: {$drafts->count()} (files: {$files})");

        return self::SUCCESS;
    }
}
