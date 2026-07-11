<?php

namespace App\Notifications;

use App\Enums\AdmissionStatus;
use App\Models\Ticket;
use App\Services\Tickets\QrCodeGenerator;
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

        $generator = app(QrCodeGenerator::class);

        // QR kódy patria jednotlivým vstupenkám (admissions), nie objednávke.
        // Náhradník ešte nemá miesto — QR mu nevydáme (rovnako ako pri vchode).
        $admissions = $this->ticket->admissions()
            ->with('ticketType')
            ->where('status', AdmissionStatus::Valid->value)
            ->orderBy('id')
            ->get();

        // Vstupenky objednané pre iných čakajú na ich potvrdenie — QR objednávateľovi
        // ešte nedávame, aby nescanoval miesto, ktoré účastník ešte neprijal.
        $pendingCount = $admissions->filter(fn (\App\Models\Admission $a) => $a->isPendingConfirmation())->count();

        $seats = $admissions
            ->reject(fn (\App\Models\Admission $a) => $a->isPendingConfirmation())
            ->values()
            ->map(fn (\App\Models\Admission $admission, int $i) => [
                'label' => $admission->attendee_name ?: ('Vstupenka ' . ($i + 1)),
                'type'  => $admission->ticketType?->name,
                'png'   => $generator->forToken($admission->qr_token)->getString(),
                // Priamy odkaz na QR (PNG) — fallback, keď klient blokuje vložené obrázky.
                'qrUrl' => route('public.admissions.qr', $admission->uuid),
            ])
            ->all();

        return (new MailMessage())
            ->subject('Váš lístok na ' . $eventName)
            ->markdown('mail.ticket-issued', [
                'greetingName' => $this->ticket->holder_name,
                'eventName'    => $eventName,
                'quantity'     => (int) ($this->ticket->quantity ?? 1),
                'seats'        => $seats,
                'pendingCount' => $pendingCount,
                'ticketUrl'    => $ticketUrl,
            ]);
    }
}
