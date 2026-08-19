<?php

namespace App\Notifications;

use App\Models\Event;
use App\Services\Calendar\EventCalendarLinks;
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

    /**
     * `$unsubscribeUrl` odlišuje dve publiká tej istej pripomienky. Účastník
     * s lístkom sa odhlásiť nemá čo — pripomienka na akciu, na ktorú je
     * prihlásený, patrí k objednávke — a v jeho e-maile odkaz nie je. Kto si
     * odber vypýtal tlačidlom „Pripomeň mi", ho v pätičke mať musí.
     *
     * Vyplýva z toho aj iný záver e-mailu: účastníkovi pripomíname vstupenku
     * s QR kódom, odberateľ žiadnu nemá.
     */
    public function __construct(
        protected Event $event,
        protected string $attendeeName = '',
        protected ?string $unsubscribeUrl = null,
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

        // Pripomienka je posledná šanca dostať termín do kalendára — časť
        // účastníkov si ho pri objednávke nezapísala.
        $calendar = new EventCalendarLinks($this->event);

        $mail = (new MailMessage())
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
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'outro' => $this->unsubscribeUrl !== null
                    ? __('mail.event_reminder.outro_subscriber')
                    : __('mail.event_reminder.outro'),
                ...$calendar->viewData(),
            ]);

        return $calendar->attachTo($mail);
    }
}
