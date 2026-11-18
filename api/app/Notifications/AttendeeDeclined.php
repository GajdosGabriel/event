<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Channels\BellChannel;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Oznámenie objednávateľovi, že účastník odmietol lístok, alebo že
 * uplynula lehota na potvrdenie a jeho miesto sa uvoľnilo. Predmet oba prípady
 * rozlišuje, aby sa dali v schránke od seba odlíšiť. `$promoted` = uvoľnené
 * miesto na workshope dostal prvý náhradník.
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
        protected bool $promoted = false,
    ) {}

    public function via(object $notifiable): array
    {
        return [BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => $this->subject(MailText::subjectName($this->ticket->loadMissing('event')->event?->name)), 'link' => '/tickets/'.$this->ticket->uuid];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eventName = MailText::name($this->ticket->event?->name) ?: __('mail.common.event_fallback');
        $who = $this->attendeeName ?: $this->attendeeEmail;

        return (new MailMessage)
            ->subject($this->subject(MailText::subjectName($eventName)))
            ->markdown('mail.attendee-declined', [
                'holderName' => $this->ticket->holder_name,
                'attendeeName' => $who,
                'attendeeEmail' => $this->attendeeEmail,
                'eventName' => $eventName,
                'seats' => $this->seats,
                'expired' => $this->expired,
                'promoted' => $this->promoted,
            ]);
    }

    private function subject(string $event): string
    {
        return __($this->expired ? 'mail.attendee_declined.subject_expired' : 'mail.attendee_declined.subject', ['event' => $event]);
    }
}
