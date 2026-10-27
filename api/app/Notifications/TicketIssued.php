<?php

namespace App\Notifications;

use App\Enums\AdmissionStatus;
use App\Models\Ticket;
use App\Services\Calendar\EventCalendarLinks;
use App\Services\Tickets\PaymentInstructions;
use App\Services\Tickets\QrCodeGenerator;
use App\Services\Tickets\Questionnaire;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketIssued extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $restored  Objednávka bola obnovená po zrušení — mení sa len
     *                          úvod e-mailu, vstupenky a QR kódy sú rovnaké.
     * @param  bool  $bellOnly  Len do zvončeka — objednávateľovi, ktorý objednal
     *                          na iný e-mail (vstupenka mu e-mailom nechodí).
     */
    public function __construct(
        protected Ticket $ticket,
        protected bool $restored = false,
        protected bool $bellOnly = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->bellOnly
            ? [\App\Notifications\Channels\BellChannel::class]
            : [\App\Notifications\Channels\BellChannel::class, 'mail'];
    }

    public function toBell(object $notifiable): array
    {
        $key = $this->ticket->awaitsPayment() ? 'mail.ticket_payment.subject' : 'mail.ticket_issued.subject';

        return ['message' => __($key, ['event' => $this->ticket->loadMissing('event')->event?->name]), 'link' => '/tickets/'.$this->ticket->uuid];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketUrl = rtrim(config('app.frontend_url'), '/').'/tickets/'.$this->ticket->uuid;
        $eventName = $this->ticket->event?->name ?? __('mail.common.event_fallback');

        // Neuhradená platená objednávka: zatiaľ len rekapitulácia a suma, QR až po úhrade.
        if ($this->ticket->awaitsPayment()) {
            return $this->paymentRequest($eventName, $ticketUrl);
        }

        $generator = app(QrCodeGenerator::class);

        // QR kódy patria jednotlivým vstupenkám (admissions), nie objednávke.
        // Náhradník ešte nemá miesto — QR mu nevydáme (rovnako ako pri vchode).
        $admissions = $this->ticket->admissions()
            ->with(['ticketType', 'answers.question', 'answers.option'])
            ->where('status', AdmissionStatus::Valid->value)
            ->orderBy('id')
            ->get();

        // Vstupenky objednané pre iných čakajú na ich potvrdenie — QR objednávateľovi
        // ešte nedávame, aby nescanoval miesto, ktoré účastník ešte neprijal.
        $pendingCount = $admissions->filter(fn (\App\Models\Admission $a) => $a->isPendingConfirmation())->count();

        // Účastníci, ktorí ešte nepotvrdili — bez QR, ale s tým, čo sa za nich vyplnilo.
        $pendingSeats = $admissions
            ->filter(fn (\App\Models\Admission $a) => $a->isPendingConfirmation())
            ->values()
            ->map(fn (\App\Models\Admission $admission) => [
                'label' => (string) $admission->attendee_name,
                'type' => $admission->ticketType?->name,
                'answers' => app(Questionnaire::class)->present($admission->answers),
            ])
            ->all();

        $seats = $admissions
            ->reject(fn (\App\Models\Admission $a) => $a->isPendingConfirmation())
            ->values()
            ->map(fn (\App\Models\Admission $admission, int $i) => [
                // Miesto bez mena patrí objednávateľovi.
                'label' => $admission->attendee_name ?: ($i === 0 ? $this->ticket->holder_name : __('mail.common.seat_label', ['number' => $i + 1])),
                'type' => $admission->ticketType?->name,
                'answers' => app(Questionnaire::class)->present($admission->answers),
                'png' => $generator->forToken($admission->qr_token)->getString(),
                // Priamy odkaz na QR (PNG) — fallback, keď klient blokuje vložené obrázky.
                'qrUrl' => route('public.admissions.qr', $admission->uuid),
            ])
            ->all();

        $calendar = new EventCalendarLinks($this->ticket->event);

        $key = $this->restored ? 'mail.ticket_restored' : 'mail.ticket_issued';

        $mail = (new MailMessage)
            ->subject(__($key.'.subject', ['event' => $eventName]))
            ->markdown('mail.ticket-issued', [
                'greetingName' => $this->ticket->holder_name,
                'intro' => __($key.'.intro', ['event' => $eventName]),
                'eventName' => $eventName,
                'quantity' => (int) ($this->ticket->quantity ?? 1),
                'seats' => $seats,
                'pendingCount' => $pendingCount,
                'pendingSeats' => $pendingSeats,
                'paidAmount' => (int) $this->ticket->price_amount > 0
                    ? number_format((int) $this->ticket->price_amount / 100, 2, ',', ' ').' '.($this->ticket->price_currency ?? 'EUR')
                    : null,
                'answers' => app(Questionnaire::class)->present(
                    $this->ticket->answers()->whereNull('admission_id')->with(['question', 'option'])->get()
                ),
                'ticketUrl' => $ticketUrl,
                ...$calendar->viewData(),
            ]);

        return $calendar->attachTo($mail);
    }

    /**
     * Výzva na úhradu: čo si objednávateľ objednal (miesta, odpovede dotazníka
     * s príplatkami) a koľko má zaplatiť. Vstupenky s QR príde až po tom, čo
     * organizátor platbu označí ako uhradenú.
     */
    private function paymentRequest(string $eventName, string $ticketUrl): MailMessage
    {
        $questionnaire = app(Questionnaire::class);

        $seats = $this->ticket->admissions()
            ->with(['ticketType', 'answers.question', 'answers.option'])
            ->where('status', AdmissionStatus::Valid->value)
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (\App\Models\Admission $admission, int $i) => [
                'label' => $admission->attendee_name ?: ($i === 0 ? $this->ticket->holder_name : __('mail.common.seat_label', ['number' => $i + 1])),
                'type' => $admission->ticketType?->name,
                'answers' => $questionnaire->present($admission->answers),
            ])
            ->all();

        return (new MailMessage)
            ->subject(__('mail.ticket_payment.subject', ['event' => $eventName]))
            ->markdown('mail.ticket-payment-requested', [
                'greetingName' => $this->ticket->holder_name,
                'eventName' => $eventName,
                'seats' => $seats,
                'answers' => $questionnaire->present(
                    $this->ticket->answers()->whereNull('admission_id')->with(['question', 'option'])->get()
                ),
                'amount' => number_format((int) $this->ticket->price_amount / 100, 2, ',', ' ').' '.($this->ticket->price_currency ?? 'EUR'),
                // Kam a s akým VS zaplatiť; bez účtu organizácie ostane všeobecná výzva.
                'payment' => app(PaymentInstructions::class)->for($this->ticket),
                'ticketUrl' => $ticketUrl,
            ]);
    }
}
