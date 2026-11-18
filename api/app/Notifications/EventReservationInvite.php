<?php

namespace App\Notifications;

use App\Models\Event;
use App\Notifications\Channels\BellChannel;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Záložný e-mail po overení: bežne overenie rovno rezervuje (EventSignup),
 * tento e-mail ide len vtedy, keď rezervácia zlyhala. Odkaz vedie na
 * /prihlasenie/{id} — po prihlásení rezervuje jedným klikom.
 */
class EventReservationInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
    ) {}

    public function via(object $notifiable): array
    {
        return [BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => __('mail.event_reservation_invite.subject', ['event' => MailText::subjectName($this->event->name)]), 'link' => '/prihlasenie/'.$this->event->id];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');
        $url = rtrim((string) config('app.frontend_url'), '/').'/prihlasenie/'.$this->event->id;

        return (new MailMessage)
            ->subject(__('mail.event_reservation_invite.subject', ['event' => MailText::subjectName($event)]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_reservation_invite.intro'))
            ->line(__('mail.event_reservation_invite.next', ['event' => $event]))
            ->action(__('mail.event_reservation_invite.action'), $url)
            ->line(__('mail.event_reservation_invite.outro'));
    }
}
