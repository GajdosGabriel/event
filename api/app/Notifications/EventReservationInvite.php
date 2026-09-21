<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Po overení e-mailu: účet je hotový, na podujatie, kvôli ktorému sa človek
 * registroval, si teraz môže rezervovať miesto (EventSignup). Odkaz vedie na
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');
        $url = rtrim((string) config('app.frontend_url'), '/').'/prihlasenie/'.$this->event->id;

        return (new MailMessage)
            ->subject(__('mail.event_reservation_invite.subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_reservation_invite.intro'))
            ->line(__('mail.event_reservation_invite.next', ['event' => $event]))
            ->action(__('mail.event_reservation_invite.action'), $url)
            ->line(__('mail.event_reservation_invite.outro'));
    }
}
