<?php

namespace App\Console\Commands;

use App\Services\Imports\EventImportService;
use Illuminate\Console\Command;

class ImportEventSources extends Command
{
    protected $signature = 'app:import-event-sources
        {--url=* : Listing URL(s) to import}
        {--pages=1 : Maximum number of pages per listing}
        {--limit=0 : Maximum number of detail pages to import per listing}
        {--force : Re-import and overwrite events even if already complete}';

    protected $description = 'Import events from configured external source listings';

    public function handle(EventImportService $importService): int
    {
        $urls = (array) $this->option('url');
        if ($urls === []) {
            $urls = (array) config('services.imports.sources.urls', []);
        }

        if ($urls === []) {
            $this->error('No event import URLs configured.');

            return self::FAILURE;
        }

        $pages = max(1, (int) $this->option('pages'));
        $limit = max(0, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $total = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed' => 0,
        ];

        foreach ($urls as $url) {
            $summary = $importService->importFromListing(
                (string) $url,
                $pages,
                $limit > 0 ? $limit : null,
                $force,
            );

            $this->info(sprintf(
                'Source %s -> imported: %d, updated: %d, skipped: %d, errors: %d',
                $url,
                $summary['imported'],
                $summary['updated'],
                $summary['skipped'],
                $summary['errors'],
            ));

            foreach ($total as $key => $value) {
                $total[$key] += $summary[$key];
            }
        }

        $this->info(sprintf(
            'Event import summary -> processed: %d, imported: %d, updated: %d, skipped: %d, errors: %d',
            $total['processed'],
            $total['imported'],
            $total['updated'],
            $total['skipped'],
            $total['errors'],
        ));

        return $total['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
