<?php

namespace App\Notifications;

use App\Models\Event;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Účastníkovi, keď sa vstupenka zdarma vydať nedala (platená akcia, plná
 * kapacita) — záujem sme odovzdali organizátorovi (EventSignup).
 */
class EventInterestRecorded extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject(__('mail.event_interest.subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_interest.intro', ['event' => $event]))
            ->line(__('mail.event_interest.next'))
            ->action(__('mail.event_interest.action'), PublicUrl::event($this->event));
    }
}
