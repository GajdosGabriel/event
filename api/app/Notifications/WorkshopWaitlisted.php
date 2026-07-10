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
        $workshop = $this->admission->ticketType?->name ?? 'workshop';
        $eventName = $this->admission->event?->name ?? 'podujatie';
        $eventUrl = rtrim(config('app.frontend_url'), '/') . '/events/' . $this->admission->event_id;

        return (new MailMessage())
            ->subject('Ste náhradník na workshop ' . $workshop)
            ->greeting('Dobrý deň!')
            ->line('Workshop „' . $workshop . '" na akcii „' . $eventName . '" je momentálne plný, zaradili sme vás medzi náhradníkov.')
            ->line('Vaše poradie: ' . $this->position . '.')
            ->line('Ak sa miesto uvoľní, automaticky vám ho pridelíme a pošleme vám lístok s QR kódom.')
            ->action('Zobraziť podujatie', $eventUrl);
    }
}
