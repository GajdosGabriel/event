<?php

namespace App\Notifications;

use App\Models\Event;
use App\Support\PublicUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pripomienka účastníkom pred začiatkom podujatia (roadmap 3.5). Posiela ju
 * príkaz `app:events-send-reminders` podľa `events.reminder_hours_before`.
 */
class EventReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected string $attendeeName = '',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Notifikácia je ShouldQueue — na workeri sa model načíta odznova a bez
        // relácie, takže miesto konania si treba vypýtať explicitne
        // (Model::preventLazyLoading je mimo produkcie zapnutý).
        $venue = $this->event->loadMissing('venue')->venue;

        return (new MailMessage())
            ->subject(__('mail.event_reminder.subject', ['event' => $this->event->name]))
            ->markdown('mail.event-reminder', [
                'greeting' => $this->attendeeName !== ''
                    ? __('mail.common.greeting_named', ['name' => $this->attendeeName])
                    : __('mail.common.greeting'),
                'eventName' => $this->event->name,
                'eventUrl' => PublicUrl::event($this->event),
                'startsAt' => $this->event->start_at?->format('d. m. Y H:i'),
                'venueName' => $venue?->name,
                'venueAddress' => trim((string) $venue?->street),
            ]);
    }
}
