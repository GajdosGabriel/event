<?php

namespace App\Notifications;

use App\Models\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Používateľ bol zaradený medzi náhradníkov na plný workshop. */
class WorkshopWaitlisted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Admission $admission,
        protected int $position,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workshop = $this->admission->ticketType?->name ?? __('mail.common.workshop_fallback');
        $eventName = $this->admission->event?->name ?? __('mail.common.event_fallback');
        $eventUrl = rtrim(config('app.frontend_url'), '/') . '/events/' . $this->admission->event_id;

        return (new MailMessage())
            ->subject(__('mail.workshop_waitlisted.subject', ['workshop' => $workshop]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.workshop_waitlisted.intro', ['workshop' => $workshop, 'event' => $eventName]))
            ->line(__('mail.workshop_waitlisted.position', ['position' => $this->position]))
            ->line(__('mail.workshop_waitlisted.note'))
            ->action(__('mail.workshop_waitlisted.action'), $eventUrl);
    }
}
