<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Oznámenie objednávateľovi, že účastník potvrdil svoju účasť.
 */
class AttendeeConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected ?string $attendeeName,
        protected string $attendeeEmail,
        protected int $seats = 1,
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
        $ticketUrl = rtrim((string) config('app.frontend_url'), '/') . '/tickets/' . $this->ticket->uuid;

        return (new MailMessage())
            ->subject($who . ' potvrdil(a) účasť na ' . $eventName)
            ->markdown('mail.attendee-confirmed', [
                'holderName'    => $this->ticket->holder_name,
                'attendeeName'  => $who,
                'attendeeEmail' => $this->attendeeEmail,
                'eventName'     => $eventName,
                'seats'         => $this->seats,
                'ticketUrl'     => $ticketUrl,
            ]);
    }
}
