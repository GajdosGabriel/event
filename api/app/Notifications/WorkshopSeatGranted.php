<?php

namespace App\Notifications;

use App\Models\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Náhradníkovi sa uvoľnilo miesto — ponúkame mu ho.
 *
 * Miesto mu držíme, ale vstupenku s QR kódom dostane až keď ponuku potvrdí.
 * Inak by sme miesto blokovali niekomu, kto oň už nemusí mať záujem.
 */
class WorkshopSeatGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Admission $admission
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
        $base = rtrim((string) config('app.frontend_url'), '/') . '/rsvp/' . $this->admission->confirmation_token;

        $message = (new MailMessage())
            ->subject(__('mail.workshop_seat_granted.subject', ['workshop' => $workshop]))
            ->greeting(__('mail.common.greeting'))
            ->line(__('mail.workshop_seat_granted.intro', ['workshop' => $workshop, 'event' => $eventName]));

        if ($this->admission->ticketType?->starts_at) {
            $message->line(__('mail.workshop_seat_granted.starts_at', [
                'date' => $this->admission->ticketType->starts_at->format('j. n. Y H:i'),
            ]));
        }

        if ($deadline = $this->admission->confirmation_deadline_at) {
            $message->line(__('mail.workshop_seat_granted.deadline', [
                'deadline' => $deadline->locale(app()->getLocale())->translatedFormat('j. F Y, H:i'),
            ]));
        }

        return $message
            ->action(__('mail.workshop_seat_granted.action'), $base . '?do=confirm')
            ->line(__('mail.workshop_seat_granted.after_confirm'))
            ->line(__('mail.workshop_seat_granted.decline', ['url' => $base . '?do=cancel']));
    }
}
