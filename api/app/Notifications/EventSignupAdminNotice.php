<?php

namespace App\Notifications;

use App\Models\Event;
use App\Support\DashboardUrl;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Super-adminom: prehľad o každom prihlásení na akciu cez EventSignup — kto,
 * na čo, či dostal vstupenku a či sa podarilo osloviť organizátora. Pri
 * `none` treba organizátora kontaktovať ručne.
 */
class EventSignupAdminNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected string $attendeeName,
        protected ?string $attendeeEmail,
        protected string $status,
        protected string $organizerChannel,
        protected ?string $organizerEmail,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');

        return (new MailMessage)
            ->subject(__('mail.event_signup_admin.subject', ['event' => $event]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.event_signup_admin.intro', [
                'name' => $this->attendeeName,
                'email' => (string) $this->attendeeEmail,
                'event' => $event,
            ]))
            ->line(__('mail.event_signup_admin.status.'.$this->status))
            ->line(__('mail.event_signup_admin.organizer.'.$this->organizerChannel, [
                'email' => (string) $this->organizerEmail,
            ]))
            ->line(__('mail.event_signup_admin.event_link', ['url' => PublicUrl::event($this->event)]))
            ->action(
                __('mail.event_signup_admin.action'),
                DashboardUrl::base().'/events/'.$this->event->id.'/attendees',
            );
    }
}
