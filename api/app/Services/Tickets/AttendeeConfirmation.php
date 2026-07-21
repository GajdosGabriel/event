<?php

namespace App\Services\Tickets;

use App\Enums\AdmissionStatus;
use App\Enums\AttendeeConfirmationStatus;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Admission;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\AttendeeConfirmed;
use App\Notifications\AttendeeDeclined;
use App\Notifications\AttendeeTicketIssued;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Potvrdzovanie účasti účastníkov, pre ktorých objednávateľ objednal vstupenku.
 *
 * Skupina = všetky vstupenky jednej objednávky patriace jednému e-mailu účastníka
 * (jeden človek môže dostať viac miest). Potvrdenie/odmietnutie sa deje pre celú
 * skupinu naraz cez jeden token.
 */
class AttendeeConfirmation
{
    public function __construct(
        private TicketRepository $tickets,
    ) {
    }

    /**
     * Označí vstupenky objednané pre iných účastníkov ako „čaká na potvrdenie",
     * pridelí token a lehotu. Vráti ich zoskupené podľa e-mailu účastníka.
     *
     * @return Collection<string, Collection<int, Admission>>
     */
    public function prepare(Ticket $ticket): Collection
    {
        $holderEmail = mb_strtolower(trim((string) $ticket->holder_email));
        $deadline = $this->deadlineFor($ticket);

        return DB::transaction(function () use ($ticket, $holderEmail, $deadline) {
            $pending = $ticket->admissions()
                ->where('status', AdmissionStatus::Valid->value)
                ->whereNull('confirmation_status')
                ->whereNotNull('attendee_email')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (Admission $a) => $a->attendee_email !== '' && $a->attendee_email !== $holderEmail);

            foreach ($pending as $admission) {
                $admission->update([
                    'confirmation_status' => AttendeeConfirmationStatus::Pending->value,
                    'confirmation_token' => (string) Str::random(64),
                    'confirmation_deadline_at' => $deadline,
                ]);
            }

            return $pending->groupBy('attendee_email');
        });
    }

    /**
     * Vstupenky patriace k tokenu — konkrétna vstupenka aj jej „súrodenci"
     * (rovnaká objednávka, rovnaký e-mail účastníka). Null, ak token neexistuje.
     *
     * @return Collection<int, Admission>|null
     */
    public function groupForToken(string $token): ?Collection
    {
        $admission = Admission::query()
            ->with(['ticket.event', 'ticketType'])
            ->where('confirmation_token', $token)
            ->first();

        if (! $admission) {
            return null;
        }

        return Admission::query()
            ->with(['ticket.event', 'ticketType'])
            ->where('ticket_id', $admission->ticket_id)
            ->where('attendee_email', $admission->attendee_email)
            ->whereNotNull('confirmation_status')
            ->orderBy('id')
            ->get();
    }

    /**
     * Potvrdí účasť celej skupiny. Účastníkovi pošle vstupenku (QR),
     * objednávateľovi oznámi potvrdenie. Idempotentné.
     */
    public function confirm(Collection $group): void
    {
        $pending = $group->filter(fn (Admission $a) => $a->isPendingConfirmation());

        if ($pending->isEmpty()) {
            return;
        }

        $ids = DB::transaction(function () use ($pending) {
            $confirmed = [];

            foreach ($pending as $admission) {
                $locked = Admission::query()->lockForUpdate()->find($admission->id);

                if (! $locked || ! $locked->isPendingConfirmation()) {
                    continue;
                }

                $locked->update([
                    'confirmation_status' => AttendeeConfirmationStatus::Confirmed->value,
                    'confirmed_at' => now(),
                ]);

                $confirmed[] = $locked->id;
            }

            return $confirmed;
        });

        if ($ids === []) {
            return;
        }

        /** @var Admission $first */
        $first = $pending->first();
        $ticket = $first->ticket()->with(['event', 'admissions.ticketType'])->first();
        $attendeeEmail = (string) $first->attendee_email;

        // Objednávka držaná do potvrdenia (napr. ponuka miesta z čakačky) je teraz
        // platná. Platené objednávky necháme na platobnom stave.
        if ($ticket?->status === TicketStatus::Reserved && $ticket->payment_status === TicketPaymentStatus::None) {
            $ticket->update(['status' => TicketStatus::Confirmed->value]);
        }

        $user = User::where('email', $attendeeEmail)->first();
        $needsActivation = $user !== null && $user->email_verified_at === null;

        // Účastníkovi teraz pošleme jeho vstupenku s QR kódom.
        Notification::route('mail', $attendeeEmail)
            ->notify(new AttendeeTicketIssued($ticket, $ids, $needsActivation));

        // Objednávateľovi dáme vedieť, že účastník potvrdil účasť. Pri samoobslužnej
        // registrácii (napr. prijatie miesta z čakačky) je to tá istá osoba — druhý
        // e-mail o sebe samom by bol mätúci.
        if (! $this->isSelfService($ticket, $attendeeEmail)) {
            Notification::route('mail', $ticket->holder_email)
                ->notify(new AttendeeConfirmed($ticket, $first->attendee_name, $attendeeEmail, count($ids)));
        }
    }

    /**
     * Odmietne / nechá vypršať účasť celej skupiny — uvoľní miesta,
     * posunie prípadných workshopových náhradníkov a oznámi to objednávateľovi.
     */
    public function decline(Collection $group, bool $expired = false): void
    {
        $this->release(
            $group,
            fn (Admission $a) => $a->isPendingConfirmation(),
            $expired ? AttendeeConfirmationStatus::Expired : AttendeeConfirmationStatus::Declined,
            $expired,
        );
    }

    /**
     * Zrušenie už potvrdenej bezplatnej vstupenky samotným účastníkom
     * (odkaz „Zrušiť vstupenku" v e-maile so vstupenkou). Idempotentné.
     *
     * @param Collection<int, Admission> $group
     */
    public function cancel(Collection $group): void
    {
        $this->release(
            $group,
            fn (Admission $a) => $a->isCancellableByAttendee(),
            AttendeeConfirmationStatus::Declined,
        );
    }

    /**
     * Spoločné uvoľnenie miest skupiny — zruší vstupenky, ktoré prejdú cez
     * $eligible (kontroluje sa aj pod zámkom), posunie workshopových
     * náhradníkov a oznámi to objednávateľovi.
     *
     * @param Collection<int, Admission> $group
     */
    private function release(Collection $group, \Closure $eligible, AttendeeConfirmationStatus $status, bool $expired = false): void
    {
        $pending = $group->filter($eligible);

        if ($pending->isEmpty()) {
            return;
        }

        $freedTypes = DB::transaction(function () use ($pending, $eligible, $status) {
            $freed = collect();

            foreach ($pending as $admission) {
                $locked = Admission::query()->with(['ticketType', 'ticket.event'])->lockForUpdate()->find($admission->id);

                if (! $locked || ! $eligible($locked)) {
                    continue;
                }

                if ($locked->status === AdmissionStatus::Valid && $locked->ticketType?->isWorkshop()) {
                    $freed->push($locked->ticketType);
                }

                $locked->update([
                    'confirmation_status' => $status->value,
                    'status' => AdmissionStatus::Cancelled->value,
                ]);
            }

            return $freed->filter()->unique('id');
        });

        foreach ($freedTypes as $type) {
            $this->tickets->promoteFromWaitlist($type);
        }

        /** @var Admission $first */
        $first = $pending->first();
        $ticket = $first->ticket()->with('event')->first();
        $attendeeEmail = (string) $first->attendee_email;

        // Pri samoobslužnej registrácii je objednávateľ ten istý človek, ktorý
        // práve odmietol — správu „X nepotvrdil(a) účasť" mu neposielame.
        if (! $this->isSelfService($ticket, $attendeeEmail)) {
            Notification::route('mail', $ticket->holder_email)
                ->notify(new AttendeeDeclined($ticket, $first->attendee_name, $attendeeEmail, $pending->count(), $expired));
        }
    }

    /** Objednávateľ a účastník sú tá istá osoba — netreba mu písať o sebe samom. */
    private function isSelfService(?Ticket $ticket, string $attendeeEmail): bool
    {
        $holder = mb_strtolower(trim((string) $ticket?->holder_email));

        return $holder !== '' && $holder === mb_strtolower(trim($attendeeEmail));
    }

    /**
     * Uvoľní všetky nepotvrdené rezervácie po lehote. Vráti počet uvoľnených miest.
     */
    public function expirePending(): int
    {
        $due = Admission::query()
            ->with(['ticket.event', 'ticketType'])
            ->where('confirmation_status', AttendeeConfirmationStatus::Pending->value)
            ->whereNotNull('confirmation_deadline_at')
            ->where('confirmation_deadline_at', '<=', now())
            ->orderBy('id')
            ->get();

        $released = 0;

        // Zoskupíme po objednávke + e-maile účastníka, aby objednávateľ dostal
        // jeden e-mail za skupinu, nie za každé miesto zvlášť.
        foreach ($due->groupBy(fn (Admission $a) => $a->ticket_id . '|' . $a->attendee_email) as $group) {
            $this->decline($group, expired: true);
            $released += $group->count();
        }

        return $released;
    }

    /**
     * Lehota na potvrdenie — nikdy nie neskôr než termín registrácie / začiatok podujatia.
     *
     * @param int|null    $hours   Vlastná dĺžka lehoty (default: tickets.confirmation_hours).
     * @param Carbon|null $notAfter Ďalší strop, napr. začiatok workshopu.
     */
    public function deadlineFor(?Ticket $ticket, ?int $hours = null, ?Carbon $notAfter = null): Carbon
    {
        $deadline = now()->addHours($hours ?? (int) config('tickets.confirmation_hours', 48));

        $event = $ticket?->event ?? $ticket?->event()->first();

        foreach ([$event?->registration_deadline_at, $event?->start_at, $notAfter] as $cap) {
            if ($cap !== null && $cap->lt($deadline)) {
                $deadline = $cap;
            }
        }

        return $deadline;
    }
}
