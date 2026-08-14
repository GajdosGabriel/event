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
 * Hromadný e-mail organizátora účastníkom podujatia (roadmap 3.5).
 *
 * Predmet aj telo píše organizátor; text ide do e-mailu escapovaný (šablóna
 * používa `{{ }}`), takže sa cez neho nedá poslať HTML. Reply-to smeruje na
 * organizátora — účastník má odpovedať jemu, nie portálu.
 */
class EventAnnouncement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected string $subject,
        protected string $body,
        protected ?string $replyToEmail = null,
        protected ?string $replyToName = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Oznam chodí aj po zmene termínu. `.ics` nesie rovnaké UID a vyššie
        // SEQUENCE, takže kalendár pôvodný záznam prepíše — nevznikne duplikát.
        $calendar = new EventCalendarLinks($this->event);

        $mail = (new MailMessage())
            ->subject($this->subject)
            ->markdown('mail.event-announcement', [
                'eventName' => $this->event->name,
                'eventUrl' => PublicUrl::event($this->event),
                'body' => $this->body,
                ...$calendar->viewData(),
            ]);

        if ($this->replyToEmail !== null) {
            $mail->replyTo($this->replyToEmail, $this->replyToName ?? '');
        }

        return $calendar->attachTo($mail);
    }
}
