<?php

namespace App\Services\Events;

use App\Enums\AdmissionStatus;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Admission;
use App\Models\Event;
use App\Models\EventInterest;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Kto sú účastníci podujatia — jediný zdroj pravdy pre CSV export, hromadný
 * e-mail aj pripomienku.
 *
 * Objednávka (Ticket) má e-mail objednávateľa, jednotlivé vstupenky (Admission)
 * môžu mať vlastného účastníka. Adresát je preto buď účastník, alebo — keď svoj
 * e-mail nemá — objednávateľ. Zrušené objednávky a zrušené či čakajúce
 * vstupenky sem nepatria: náhradník ešte miesto nemá a zrušený lístok už nie.
 */
class AttendeeDirectory
{
    /**
     * Riadky pre export — jeden na vstupenku, vrátane zrušených a čakajúcich,
     * lebo organizátor v tabuľke potrebuje vidieť aj ich.
     *
     * @return Collection<int, Admission>
     */
    public function rows(Event $event): Collection
    {
        return Admission::query()
            ->where('event_id', $event->id)
            ->with(['ticket.answers.question', 'ticket.answers.option', 'ticketType'])
            ->orderBy('ticket_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Záujemcovia bez vstupenky, najnovší prvý. Do exportu ani hromadného
     * e-mailu nepatria — miesto nemajú, organizátor im píše jednotlivo.
     *
     * @return Collection<int, EventInterest>
     */
    public function interests(Event $event): Collection
    {
        return EventInterest::query()
            ->where('event_id', $event->id)
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * Adresáti hromadného e-mailu — unikátne adresy platných vstupeniek
     * nezrušených objednávok, s menom pre oslovenie.
     *
     * Rozsah zúži okruh: `audience=unpaid` na neuhradené objednávky,
     * `ticket_type_id` na jeden typ lístka. `ticket_ids` je výber organizátora
     * z konkrétnych riadkov — vtedy platí bez ohľadu na stav, lebo aj zrušenému
     * účastníkovi môže chcieť napísať.
     *
     * @param  array{ticket_ids?: array<int, int>, ticket_type_id?: int, audience?: string}  $scope
     * @return Collection<string, array{email: string, name: string}>
     */
    public function recipients(Event $event, array $scope = []): Collection
    {
        if (! empty($scope['ticket_ids'])) {
            return $this->selectedRecipients($event, $scope['ticket_ids']);
        }

        return Admission::query()
            ->where('event_id', $event->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->whereHas('ticket', fn ($q) => $q
                ->where('status', '!=', TicketStatus::Cancelled->value)
                ->when(($scope['audience'] ?? null) === 'unpaid', fn ($q) => $q->whereIn('payment_status', [
                    TicketPaymentStatus::Pending->value,
                    TicketPaymentStatus::Failed->value,
                ])))
            ->when($scope['ticket_type_id'] ?? null, fn ($q, $typeId) => $q->where('ticket_type_id', $typeId))
            ->with('ticket')
            ->get()
            ->map(fn (Admission $admission) => [
                'email' => trim((string) ($admission->attendee_email ?: $admission->ticket?->holder_email)),
                'name' => trim((string) ($admission->attendee_name ?: $admission->ticket?->holder_name)),
            ])
            ->filter(fn (array $row) => filter_var($row['email'], FILTER_VALIDATE_EMAIL) !== false)
            // Jeden človek s viacerými lístkami dostane jeden e-mail, nie tri.
            ->keyBy(fn (array $row) => mb_strtolower($row['email']));
    }

    /**
     * Objednávatelia a účastníci vybraných objednávok tohto podujatia.
     * Id z cudzieho podujatia dotaz ticho vynechá.
     *
     * @param  array<int, int>  $ticketIds
     * @return Collection<string, array{email: string, name: string}>
     */
    private function selectedRecipients(Event $event, array $ticketIds): Collection
    {
        return Ticket::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $ticketIds)
            ->with('admissions')
            ->get()
            ->flatMap(fn (Ticket $ticket) => collect([['email' => $ticket->holder_email, 'name' => $ticket->holder_name]])
                ->merge($ticket->admissions->map(fn (Admission $admission) => [
                    'email' => $admission->attendee_email,
                    'name' => $admission->attendee_name ?: $ticket->holder_name,
                ])))
            ->map(fn (array $row) => ['email' => trim((string) $row['email']), 'name' => trim((string) $row['name'])])
            ->filter(fn (array $row) => filter_var($row['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->keyBy(fn (array $row) => mb_strtolower($row['email']));
    }
}
