<?php

namespace App\Observers;

use App\Enums\MailingTrigger;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Account\AccountMailing;

/**
 * Zrušená registrácia už nemá dostať maily naplánované v Accounte
 * (napr. „ako sa vám páčilo“) – zruší ich a nahlási udalosť zrušenia.
 * Registráciu ruší účastník aj organizátor viacerými cestami, preto observer.
 */
class TicketObserver
{
    public function __construct(private readonly AccountMailing $mailing) {}

    public function updated(Ticket $ticket): void
    {
        if (! $ticket->wasChanged('status') || $ticket->status !== TicketStatus::Cancelled) {
            return;
        }

        $this->mailing->cancel($ticket);
        $this->mailing->event(MailingTrigger::TicketCancelled, $ticket);
    }

    public function deleted(Ticket $ticket): void
    {
        $this->mailing->cancel($ticket);
        // Bez platnej registrácie prichádza adresa o štítok `attendee`.
        $this->mailing->syncContact($ticket->holder_email);
    }
}
