<?php

namespace App\Notifications;

use App\Models\Event;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Účastníkovi: potvrdenie, že si rezerváciu na podujatí sám zrušil
 * (EventSignup::notifyAboutCancellation). Odkaz vedie späť na akciu,
 * aby si miesto mohol znova rezervovať.
 */
class RegistrationCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
    ) {}

    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => __('mail.registration_cancelled.subject', ['event' => $this->event->name]), 'link' => PublicUrl::event($this->event)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');

        return (new MailMessage)
            ->subject(__('mail.registration_cancelled.subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.registration_cancelled.intro', ['event' => $event]))
            ->line(__('mail.registration_cancelled.next'))
            ->action(__('mail.registration_cancelled.action'), PublicUrl::event($this->event));
    }
}
