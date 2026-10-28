<?php

namespace App\Jobs;

use App\Services\Account\AccountClient;
use App\Services\Account\AccountMailing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Nahlási udalosť emailingu do Accountu.
 *
 * Cez frontu, aby výpadok Accountu nezhodil registráciu – udalosť sa
 * zopakuje. Opakovanie je bezpečné: Account deduplikuje podľa `dedupeKey`.
 *
 *   ReportMailingEvent::dispatch('ticket_registered', $ticket->holder_email, ['type' => 'ticket', 'id' => $ticket->uuid],
 *       ['podujatie' => $event->name], "ticket:{$ticket->uuid}", $ticket->created_at)->afterCommit();
 */
class ReportMailingEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600, 10800];

    /**
     * @param  array{type: string, id: string|int, label?: string, url?: string}|null  $ref
     * @param  array<string, scalar|null>  $variables
     */
    public function __construct(
        public string $event,
        public string $email,
        public ?array $ref = null,
        public array $variables = [],
        public ?string $dedupeKey = null,
        public ?\DateTimeInterface $occurredAt = null,
        public ?string $name = null,
        public ?string $threadId = null,
    ) {
        // Čas udalosti zafixuj teraz – job môže bežať až o hodinu.
        $this->occurredAt ??= now();
    }

    public function handle(AccountClient $account, AccountMailing $mailing): void
    {
        // Bez kontaktu by Account kampaň (ponuku ani oznámenie) nezaradil.
        // V tom istom jobe, aby ho udalosť nepredbehla ani pri opakovaní.
        if ($contact = $mailing->contact($this->email)) {
            $account->upsertMailingContacts([$contact]);
        }

        $account->mailingEvent($this->event, $this->email, $this->ref, $this->variables, $this->dedupeKey, $this->occurredAt, $this->name, $this->threadId);
    }
}
