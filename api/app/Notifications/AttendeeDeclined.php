<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Oznámenie objednávateľovi, že účastník odmietol lístok, alebo že
 * uplynula lehota na potvrdenie a jeho miesto sa uvoľnilo.
 */
class AttendeeDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected ?string $attendeeName,
        protected string $attendeeEmail,
        protected int $seats = 1,
        protected bool $expired = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eventName = $this->ticket->event?->name ?? 'podujatie';
        $who = $this->attendeeName ?: $this->attendeeEmail;

        return (new MailMessage())
            ->subject('Uvoľnené miesto na ' . $eventName)
            ->markdown('mail.attendee-declined', [
                'holderName'    => $this->ticket->holder_name,
                'attendeeName'  => $who,
                'attendeeEmail' => $this->attendeeEmail,
                'eventName'     => $eventName,
                'seats'         => $this->seats,
                'expired'       => $this->expired,
            ]);
    }
}
