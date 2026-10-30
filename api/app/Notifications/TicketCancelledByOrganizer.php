<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Účastníkovi: organizátor zrušil jeho objednávku. Dôvod píše organizátor, preto
 * odpoveď smeruje jemu, nie portálu.
 */
class TicketCancelledByOrganizer extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected ?string $reason = null,
        protected ?string $replyToEmail = null,
        protected ?string $replyToName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->event->name ?: __('mail.common.event_fallback');

        $mail = (new MailMessage)
            ->subject(self::subjectFor($event))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.ticket_cancelled_by_organizer.intro', ['event' => $event]));

        if ($this->reason !== null && $this->reason !== '') {
            $mail->line(__('mail.ticket_cancelled_by_organizer.reason', ['reason' => $this->reason]));
        }

        if ($this->replyToEmail !== null) {
            $mail->replyTo($this->replyToEmail, $this->replyToName ?? '');
        }

        return $mail->line(__('mail.ticket_cancelled_by_organizer.outro'));
    }

    public static function subjectFor(string $event): string
    {
        return __('mail.ticket_cancelled_by_organizer.subject', ['event' => $event]);
    }
}
