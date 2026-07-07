<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Ticket $ticket
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketUrl = rtrim(config('app.frontend_url'), '/') . '/tickets/' . $this->ticket->uuid;
        $eventName = $this->ticket->event?->name ?? 'podujatie';
        $quantity = (int) ($this->ticket->quantity ?? 1);

        $message = (new MailMessage())
            ->subject('Váš lístok na ' . $eventName)
            ->greeting('Dobrý deň, ' . $this->ticket->holder_name . '!')
            ->line('Váš lístok na akciu "' . $eventName . '" bol úspešne vytvorený.');

        if ($quantity > 1) {
            $message->line('Počet rezervovaných miest: ' . $quantity . '.');
        }

        return $message
            ->action('Zobraziť lístok a QR kód', $ticketUrl)
            ->line('Lístok si preneste v telefóne alebo vytlačte a predložte ho pri vstupe na akciu.');
    }
}
