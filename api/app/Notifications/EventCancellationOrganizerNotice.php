<?php

namespace App\Notifications;

use App\Models\Event;
use App\Support\DashboardUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Organizátorovi: účastník si rezerváciu na jeho akciu zrušil a miesto sa
 * uvoľnilo (App\Services\Tickets\EventSignup::notifyAboutCancellation).
 */
class EventCancellationOrganizerNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected string $attendeeName,
        protected int $registeredCount,
    ) {}

    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        return ['message' => __('mail.event_cancellation_organizer.subject', ['event' => $this->event->name]), 'link' => '/dashboard/events/'.$this->event->id.'/attendees'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');

        $mail = (new MailMessage)
            ->subject(__('mail.event_cancellation_organizer.subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_cancellation_organizer.intro', ['event' => $event, 'name' => $this->attendeeName]));

        if ($this->registeredCount > 0) {
            $mail->line(trans_choice('mail.event_signup_organizer.count', $this->registeredCount));
        }

        return $mail
            ->action(
                __('mail.event_signup_organizer.action'),
                DashboardUrl::base().'/events/'.$this->event->id.'/attendees',
            );
    }
}
