<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Services\Publishing\EventDependencyPublisher;
use Illuminate\Console\Command;

/**
 * Naplánované publikovanie: podujatia v stave `scheduled`, ktorým už nastal
 * `publish_at`, prehodí na `published`.
 *
 * `published_at` sa nastavuje len prvýkrát — je to čas prvého zverejnenia
 * (rovnako ako pri ručnom publikovaní), nie čas posledného behu príkazu.
 * Zapisuje sa doň plánovaný čas, nie `now()`: príkaz beží v dávkach, takže
 * `now()` by podujatie posunulo o pár minút podľa toho, kedy webcron dobehol.
 */
class PublishScheduledEvents extends Command
{
    protected $signature = 'app:events-publish-scheduled';

    protected $description = 'Publish scheduled events whose publish_at is due';

    public function __construct(private readonly EventDependencyPublisher $dependencyPublisher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $due = Event::query()
            ->where('status', ModelStatus::Scheduled->value)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->get();

        foreach ($due as $event) {
            // Miesto ani kanál sa medzi naplánovaním a termínom nemuseli
            // pohnúť. Cron nemá koho spýtať, či ich publikovať, a stiahnuť
            // podujatie z plánu tiež nemôže — otvorí ich s ním.
            $this->dependencyPublisher->publishAll($event);

            $event->update([
                'status' => ModelStatus::Published->value,
                'published_at' => $event->published_at ?? $event->publish_at,
                'publish_at' => null,
            ]);
        }

        $this->info('Published scheduled events: ' . $due->count());

        return self::SUCCESS;
    }
}
