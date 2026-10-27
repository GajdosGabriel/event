<?php

namespace App\Services\Account;

use App\Enums\MailingTrigger;
use App\Jobs\CancelMailing;
use App\Jobs\ReportMailingEvent;
use App\Models\Canal;
use App\Models\Ticket;
use App\Support\DashboardUrl;
use App\Support\EventDateRange;
use App\Support\PublicUrl;
use Carbon\CarbonInterface;

/**
 * Emailing (kampane a automatizácie) beží v Accounte – Event len hlási
 * udalosti registrácií s premennými do textu kampane.
 *
 * Vecou je registrácia (`ticket`, uuid), takže zrušenie registrácie vie
 * v Accounte zrušiť jej čakajúce maily. Kľúč udalosti je `ticket:{uuid}` –
 * opakované hlásenie nič nezdvojí.
 *
 * Bez ACCOUNT_MAILING (alebo tokenu) sa nič nehlási.
 */
class AccountMailing
{
    public function __construct(private readonly AccountClient $account) {}

    public function enabled(): bool
    {
        return $this->account->mailingEnabled();
    }

    public function event(MailingTrigger $trigger, Ticket $ticket, ?CarbonInterface $at = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $email = mb_strtolower(trim((string) $ticket->holder_email));

        if ($email === '') {
            return;
        }

        $event = $ticket->loadMissing('event.venue')->event;
        $name = trim((string) $ticket->holder_name) ?: null;

        ReportMailingEvent::dispatch(
            $trigger->value,
            $email,
            [
                'type' => 'ticket',
                'id' => $ticket->uuid,
                'label' => 'Registrácia – '.($event?->name ?? '#'.$ticket->id),
                'url' => $event ? DashboardUrl::base().'/events/'.$event->id.'/attendees' : null,
            ],
            [
                'meno' => $name,
                'podujatie' => $event?->name,
                'termin' => $event ? EventDateRange::label($event->start_at, $event->end_at) : null,
                'miesto' => $event?->venue?->name,
                'pocet_miest' => (int) $ticket->quantity,
                'odkaz_podujatia' => $event ? PublicUrl::event($event) : null,
                'odkaz_vstupenky' => rtrim((string) config('app.frontend_url'), '/').'/tickets/'.$ticket->uuid,
            ],
            'ticket:'.$ticket->uuid,
            $at ?? now(),
            $name,
        )->afterCommit();
    }

    /**
     * Registrácia sa zrušila alebo zmazala: čakajúce maily k nej sa neodošlú
     * (okrem kampaní práve na zrušenie).
     */
    public function cancel(Ticket $ticket): void
    {
        if (! $this->enabled()) {
            return;
        }

        CancelMailing::dispatch('ticket', $ticket->uuid, [MailingTrigger::TicketCancelled->value])->afterCommit();
    }

    /**
     * Kanál prevzal organizátor: čakajúce ponuky prevzatia (MailingTrigger::Canal*)
     * sa neodošlú.
     */
    public function cancelCanal(Canal $canal): void
    {
        if (! $this->enabled()) {
            return;
        }

        CancelMailing::dispatch('canal', (string) $canal->id)->afterCommit();
    }

    /** Udalosti pre výber spúšťača v Accounte. */
    public function triggers(): array
    {
        return array_map(fn (MailingTrigger $trigger) => [
            'key' => $trigger->value,
            'label' => $trigger->label(),
            'short_label' => $trigger->shortLabel(),
            'variables' => $trigger->variables(),
        ], MailingTrigger::cases());
    }
}
