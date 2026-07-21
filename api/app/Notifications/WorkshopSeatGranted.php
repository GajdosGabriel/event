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
        $workshop = $this->admission->ticketType?->name ?? 'workshop';
        $eventName = $this->admission->event?->name ?? 'podujatie';
        $base = rtrim((string) config('app.frontend_url'), '/') . '/rsvp/' . $this->admission->confirmation_token;

        $message = (new MailMessage())
            ->subject('Uvoľnilo sa miesto na workshope ' . $workshop)
            ->greeting('Dobrý deň!')
            ->line('Na workshope „' . $workshop . '" (' . $eventName . ') sa uvoľnilo miesto a ponúkame ho vám ako prvému náhradníkovi.');

        if ($this->admission->ticketType?->starts_at) {
            $message->line('Termín: ' . $this->admission->ticketType->starts_at->format('j. n. Y H:i') . '.');
        }

        if ($deadline = $this->admission->confirmation_deadline_at) {
            $message->line('Miesto vám držíme do **' . $deadline->locale('sk')->translatedFormat('j. F Y, H:i')
                . '**. Ak ho dovtedy nepotvrdíte, ponúkneme ho ďalšiemu náhradníkovi.');
        }

        return $message
            ->action('Potvrdiť miesto', $base . '?do=confirm')
            ->line('Vstupenku s QR kódom vám pošleme hneď po potvrdení.')
            ->line('Ak sa workshopu zúčastniť nemôžete, [odmietnite miesto](' . $base . '?do=cancel) — pustíme naň ďalšieho v poradí.');
    }
}
