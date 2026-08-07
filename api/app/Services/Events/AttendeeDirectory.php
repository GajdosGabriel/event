<?php

namespace App\Services\Events;

use App\Enums\AdmissionStatus;
use App\Enums\TicketStatus;
use App\Models\Admission;
use App\Models\Event;
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
            ->with(['ticket', 'ticketType'])
            ->orderBy('ticket_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Adresáti hromadného e-mailu — unikátne adresy platných vstupeniek
     * nezrušených objednávok, s menom pre oslovenie.
     *
     * @return Collection<string, array{email: string, name: string}>
     */
    public function recipients(Event $event): Collection
    {
        return Admission::query()
            ->where('event_id', $event->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->whereHas('ticket', fn ($q) => $q->where('status', '!=', TicketStatus::Cancelled->value))
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
}
