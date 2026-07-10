<?php

namespace App\Notifications;

use App\Models\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Náhradníkovi sa uvoľnilo miesto a bol posunutý na workshop. */
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
        $ticketUrl = rtrim(config('app.frontend_url'), '/') . '/tickets/' . $this->admission->ticket?->uuid;

        $message = (new MailMessage())
            ->subject('Uvoľnilo sa miesto na workshope ' . $workshop)
            ->greeting('Dobrý deň!')
            ->line('Na workshope „' . $workshop . '" (' . $eventName . ') sa uvoľnilo miesto a pridelili sme ho vám.');

        if ($this->admission->ticketType?->starts_at) {
            $message->line('Termín: ' . $this->admission->ticketType->starts_at->format('j. n. Y H:i') . '.');
        }

        return $message
            ->action('Zobraziť lístok a QR kód', $ticketUrl)
            ->line('Ak sa workshopu nemôžete zúčastniť, odhláste sa prosím, aby miesto dostal ďalší náhradník.');
    }
}
