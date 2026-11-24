<?php

namespace App\Notifications;

use App\Models\Canal;
use App\Models\Event;
use App\Support\MailText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Správcovi kanála: niekto v sprievodcovi plagátu uviedol jeho kanál ako
 * organizátora, ale kanál nespravuje. Podujatie preto vzniklo pod kanálom
 * autora (PosterDraftMaterializer) — správca sa o ňom má dozvedieť a ozvať,
 * ak s ním nemá nič spoločné alebo ho chce mať u seba.
 */
class PosterOrganizerNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Event $event,
        protected Canal $organizer,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->event->loadMissing(['canal', 'venue']);

        $replace = [
            'event' => MailText::name($this->event->name) ?: __('mail.common.event_fallback'),
            'canal' => $this->organizer->name,
            'author' => (string) ($this->event->canal?->name ?? ''),
            'site' => (string) config('app.name'),
        ];

        $mail = (new MailMessage)
            ->subject(__('mail.poster_organizer.subject', [...$replace, 'canal' => MailText::subjectName($this->organizer->name)]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.poster_organizer.intro', $replace));

        if ($when = MailText::range($this->event->start_at, $this->event->end_at)) {
            $mail->line(__('mail.common.when', ['when' => $when]));
        }

        if ($this->event->venue?->name) {
            $mail->line(__('mail.common.where', ['where' => $this->event->venue->name]));
        }

        return $mail
            ->line(__('mail.poster_organizer.draft_note'))
            ->line(__('mail.poster_organizer.reply_note'));
    }
}
