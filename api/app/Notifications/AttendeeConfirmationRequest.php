<?php

namespace App\Notifications;

use App\Enums\AdmissionStatus;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Žiadosť pre účastníka, aby potvrdil rezerváciu, ktorú preňho urobil
 * objednávateľ. Obsahuje tlačidlá Potvrdiť / Zrušiť lístok a lehotu.
 *
 * @param int[] $admissionIds
 */
class AttendeeConfirmationRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Ticket $ticket,
        protected array $admissionIds,
        protected string $token,
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

        $admissions = $this->ticket->admissions()
            ->with('ticketType')
            ->whereIn('id', $this->admissionIds)
            ->where('status', AdmissionStatus::Valid->value)
            ->orderBy('id')
            ->get();

        $seats = $admissions
            ->values()
            ->map(fn (\App\Models\Admission $admission, int $i) => [
                'label' => $admission->attendee_name ?: ('Vstupenka ' . ($i + 1)),
                'type'  => $admission->ticketType?->name,
            ])
            ->all();

        $base = rtrim((string) config('app.frontend_url'), '/') . '/rsvp/' . $this->token;
        $deadline = $admissions->first()?->confirmation_deadline_at;

        return (new MailMessage())
            ->subject('Potvrďte účasť na ' . $eventName)
            ->markdown('mail.attendee-confirmation-request', [
                'greetingName' => $admissions->first()?->attendee_name,
                'holderName'   => $this->ticket->holder_name,
                'eventName'    => $eventName,
                'isPaid'       => (int) ($this->ticket->price_amount ?? 0) > 0,
                'seats'        => $seats,
                'confirmUrl'   => $base . '?do=confirm',
                'declineUrl'   => $base . '?do=cancel',
                'deadline'     => $deadline?->locale('sk')->translatedFormat('j. F Y, H:i'),
                'needsActivation' => $this->needsActivation,
            ]);
    }
}
