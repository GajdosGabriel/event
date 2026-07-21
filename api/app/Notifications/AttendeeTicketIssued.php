<?php

namespace App\Notifications;

use App\Enums\AdmissionStatus;
use App\Models\Ticket;
use App\Services\Tickets\QrCodeGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail pre ďalšieho účastníka objednávky — obsahuje len jeho vstupenky (QR).
 *
 * @param int[] $admissionIds
 */
class AttendeeTicketIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected array $admissionIds,
        protected bool $needsActivation = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $eventName = $this->ticket->event?->name ?? 'podujatie';

        $generator = app(QrCodeGenerator::class);

        $admissions = $this->ticket->admissions()
            ->with(['ticketType', 'ticket.event'])
            ->whereIn('id', $this->admissionIds)
            ->where('status', AdmissionStatus::Valid->value)
            ->orderBy('id')
            ->get();

        $seats = $admissions
            ->values()
            ->map(fn (\App\Models\Admission $admission, int $i) => [
                'label' => $admission->attendee_name ?: ('Vstupenka ' . ($i + 1)),
                'type'  => $admission->ticketType?->name,
                'png'   => $generator->forToken($admission->qr_token)->getString(),
                // Priamy odkaz na QR (PNG) — fallback, keď klient blokuje vložené obrázky.
                'qrUrl' => route('public.admissions.qr', $admission->uuid),
            ])
            ->all();

        $activationUrl = rtrim((string) config('app.frontend_url'), '/') . '/login';

        // Bezplatnú vstupenku môže účastník sám zrušiť — odkaz vedie na RSVP
        // stránku, kde zrušenie ešte potvrdí (aby ho neurobil náhľad e-mailu).
        $cancelToken = $admissions->first(fn (\App\Models\Admission $a) => $a->isCancellableByAttendee())
            ?->confirmation_token;

        $cancelUrl = $cancelToken
            ? rtrim((string) config('app.frontend_url'), '/') . '/rsvp/' . $cancelToken
            : null;

        return (new MailMessage())
            ->subject('Vaša vstupenka na ' . $eventName)
            ->markdown('mail.attendee-ticket-issued', [
                'greetingName'    => $admissions->first()?->attendee_name,
                'holderName'      => $this->ticket->holder_name,
                'eventName'       => $eventName,
                'isPaid'          => (int) ($this->ticket->price_amount ?? 0) > 0,
                'seats'           => $seats,
                'cancelUrl'       => $cancelUrl,
                'needsActivation' => $this->needsActivation,
                'activationUrl'   => $activationUrl,
            ]);
    }
}
