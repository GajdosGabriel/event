<?php

namespace App\Console\Commands;

use App\Enums\AdmissionStatus;
use App\Enums\MailingTrigger;
use App\Enums\ModelStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\Account\AccountMailing;
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
    public function handle(AccountMailing $mailing): int
    {
        $finished = Event::query()
            ->where('status', ModelStatus::Published->value)
            ->whereNotNull('end_at')
            ->where('end_at', '<=', now());

        // Koniec podujatia je udalosť pre kampane v Accounte („ďakujeme za účasť“).
        // Hlási sa tu, lebo archivácia je jediné miesto, kde Event koniec
        // podujatia zaznamená; ide každému s platným miestom.
        if ($mailing->enabled()) {
            $this->reportFinished($mailing, (clone $finished)->pluck('id')->all());
        }

        $archivedCount = $finished->update([
            'status' => ModelStatus::Archived->value,
        ]);

        $this->info("Archived events: {$archivedCount}");

        return self::SUCCESS;
    }

    /** @param  array<int, int>  $eventIds */
    private function reportFinished(AccountMailing $mailing, array $eventIds): void
    {
        if ($eventIds === []) {
            return;
        }

        Ticket::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', '!=', TicketStatus::Cancelled->value)
            // Len náhradník (bez platného miesta) sa podujatia nezúčastnil.
            ->whereHas('admissions', fn ($q) => $q->where('status', AdmissionStatus::Valid->value))
            ->with('event.venue')
            ->chunkById(200, function ($tickets) use ($mailing) {
                foreach ($tickets as $ticket) {
                    $mailing->event(MailingTrigger::EventFinished, $ticket, $ticket->event->end_at);
                }
            });
    }
}
