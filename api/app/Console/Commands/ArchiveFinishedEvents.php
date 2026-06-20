<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Event;
use Illuminate\Console\Command;

class ArchiveFinishedEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:events-archive-finished';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive published events that already ended';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $archivedCount = Event::query()
            ->where('status', ModelStatus::Published->value)
            ->whereNotNull('end_at')
            ->where('end_at', '<=', now())
            ->update([
                'status' => ModelStatus::Archived->value,
            ]);

        $this->info("Archived events: {$archivedCount}");

        return self::SUCCESS;
    }
}
