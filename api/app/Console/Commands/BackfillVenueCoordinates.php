<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Repositories\Contracts\VenueRepository;
use Illuminate\Console\Command;

class BackfillVenueCoordinates extends Command
{
    protected $signature = 'app:backfill-venue-coordinates
        {--sleep=1000 : Delay in milliseconds between lookups (Nominatim politeness)}';

    protected $description = 'Resolve and store GPS coordinates (AI/Nominatim) for venues that are still missing them';

    public function handle(VenueRepository $venues): int
    {
        $sleepMs = max(0, (int) $this->option('sleep'));

        $summary = $venues->backfillMissingCoordinates(function (Venue $venue, bool $updated) use ($sleepMs) {
            $this->line(sprintf(
                ' - #%d %s -> %s',
                $venue->id,
                $venue->name,
                $updated ? sprintf('%s, %s', $venue->latitude, $venue->longitude) : 'nenájdené',
            ));

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        });

        $this->info(sprintf(
            'Venue coordinate backfill -> processed: %d, updated: %d, skipped: %d',
            $summary['processed'],
            $summary['updated'],
            $summary['skipped'],
        ));

        return self::SUCCESS;
    }
}
