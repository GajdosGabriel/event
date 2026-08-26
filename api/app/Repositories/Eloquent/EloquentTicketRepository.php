<?php

namespace App\Repositories\Eloquent;

use App\Enums\AdmissionStatus;
use App\Enums\AttendeeConfirmationStatus;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketTypeKind;
use App\Models\Admission;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\TicketIssued;
use App\Notifications\WorkshopSeatGranted;
use App\Notifications\WorkshopWaitlisted;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\TicketRepository;
use App\Services\Tickets\AttendeeConfirmation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class EloquentTicketRepository extends AbstractRepository implements TicketRepository
{
    public function entity(): string
    {
        return Ticket::class;
    }

    /**
     * Vydá objednávku (registráciu) a rozdelí ju na jednotlivé vstupenky.
     *
     * $properties['items'] = [['ticket_type_id' => int, 'quantity' => int, 'attendees' => [['name' => ?string], ...]], ...]
     */
    public function issueForEvent(Event $event, array $properties): Ticket
    {
        return DB::transaction(function () use ($event, $properties) {
            /** @var Event $lockedEvent */
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);

            // Registračné okno – podujatie už prebehlo alebo uplynul termín registrácie.
            if ($lockedEvent->end_at !== null && $lockedEvent->end_at->isPast()) {
                abort(422, __('tickets.errors.event_finished'));
            }

            if ($lockedEvent->registration_deadline_at !== null && $lockedEvent->registration_deadline_at->isPast()) {
                abort(422, __('tickets.errors.deadline_passed'));
            }

            $items = $this->normalizeItems($lockedEvent, $properties);

            $totalSeats = 0;
            $mainSeats = 0;
            $totalPrice = 0;
            $currency = null;
            $resolved = [];

            foreach ($items as $item) {
                $quantity = max(0, (int) ($item['quantity'] ?? 0));

                if ($quantity === 0) {
                    continue;
                }

                /** @var TicketType|null $type */
                $type = TicketType::query()
                    ->where('event_id', $lockedEvent->id)
                    ->lockForUpdate()
                    ->find($item['ticket_type_id'] ?? null);

                if (! $type || ! $type->is_active) {
                    abort(422, __('tickets.errors.type_unavailable'));
                }

                if ($type->sale_starts_at !== null && $type->sale_starts_at->isFuture()) {
                    abort(422, __('tickets.errors.sale_not_started', ['name' => $type->name]));
                }

                if ($type->sale_ends_at !== null && $type->sale_ends_at->isPast()) {
                    abort(422, __('tickets.errors.sale_ended', ['name' => $type->name]));
                }

                if ($quantity < $type->min_per_order) {
                    abort(422, __('tickets.errors.min_per_order', ['name' => $type->name, 'count' => $type->min_per_order]));
                }

                if ($quantity > $type->max_per_order) {
                    abort(422, __('tickets.errors.max_per_order', ['name' => $type->name, 'count' => $type->max_per_order]));
                }

                if ($type->capacity !== null) {
                    $sold = (int) Admission::query()
                        ->where('ticket_type_id', $type->id)
                        ->where('status', AdmissionStatus::Valid->value)
                        ->lockForUpdate()
                        ->count();

                    $remaining = max(0, $type->capacity - $sold);

                    if ($quantity > $remaining) {
                        abort(422, trans_choice('tickets.counts.remaining', $remaining, ['name' => $type->name]));
                    }
                }

                $totalSeats += $quantity;
                if (! $type->isWorkshop()) {
                    $mainSeats += $quantity;
                }
                $totalPrice += (int) ($type->price_amount ?? 0) * $quantity;
                $currency = $currency ?? $type->price_currency;
                $resolved[] = [
                    'type' => $type,
                    'quantity' => $quantity,
                    'attendees' => $item['attendees'] ?? [],
                ];
            }

            if ($totalSeats === 0) {
                abort(422, __('tickets.errors.nothing_selected'));
            }

            // Workshopy sú viazané na hlavnú vstupenku — nárok je počet platných
            // hlavných vstupeniek (existujúcich + v tejto objednávke). Výnimky:
            //  • workshop s open_to_public sa dá objednať aj bez hlavnej vstupenky,
            //  • podujatie bez hlavného typu (samostatné workshopy).
            // Takéto workshopy obmedzuje len vlastná kapacita a max_per_order.
            $workshopLines = array_filter(
                $resolved,
                fn ($line) => $line['type']->isWorkshop() && ! $line['type']->isOpenWorkshop(),
            );

            if ($workshopLines !== [] && $this->eventHasActiveMainType($lockedEvent)) {
                $entitlement = $mainSeats + $this->existingMainSeats(
                    $lockedEvent,
                    $properties['user_id'] ?? null,
                    $properties['holder_email'] ?? null,
                );

                if ($entitlement === 0) {
                    abort(422, __('tickets.errors.workshop_requires_ticket'));
                }

                foreach ($workshopLines as $line) {
                    if ($line['quantity'] > $entitlement) {
                        abort(422, trans_choice('tickets.counts.workshop_entitlement', $entitlement, ['name' => $line['type']->name]));
                    }
                }
            }

            $isPaid = $totalPrice > 0;

            /** @var Ticket $order */
            $order = Ticket::create([
                'event_id' => $lockedEvent->id,
                'user_id' => $properties['user_id'] ?? null,
                'holder_name' => $properties['holder_name'],
                'holder_email' => $properties['holder_email'],
                'holder_phone' => $properties['holder_phone'] ?? null,
                'quantity' => $totalSeats,
                'status' => $isPaid ? TicketStatus::Reserved->value : TicketStatus::Confirmed->value,
                'payment_status' => $isPaid ? TicketPaymentStatus::Pending->value : TicketPaymentStatus::None->value,
                'price_amount' => $isPaid ? $totalPrice : null,
                'price_currency' => $isPaid ? ($currency ?? 'EUR') : null,
            ]);

            foreach ($resolved as $line) {
                for ($i = 0; $i < $line['quantity']; $i++) {
                    $attendeeEmail = mb_strtolower(trim((string) ($line['attendees'][$i]['email'] ?? '')));

                    Admission::create([
                        'ticket_id' => $order->id,
                        'ticket_type_id' => $line['type']->id,
                        'event_id' => $lockedEvent->id,
                        'attendee_name' => $line['attendees'][$i]['name'] ?? null,
                        'attendee_email' => $attendeeEmail !== '' ? $attendeeEmail : null,
                        'status' => AdmissionStatus::Valid->value,
                    ]);
                }
            }

            return $order->fresh(['admissions.ticketType', 'event']);
        });
    }

    /**
     * Jednoklikové prihlásenie prihláseného používateľa na workshop.
     * Ak je workshop plný, používateľ sa zaradí medzi náhradníkov.
     */
    public function joinWorkshop(Event $event, TicketType $type, User $user): Admission
    {
        $this->assertWorkshopChangeable($event, $type);

        if ($this->userWorkshopAdmissions($type, $user)->exists()) {
            abort(422, __('tickets.errors.workshop_already_joined'));
        }

        // Plný workshop → čakačka. Kapacitu čítame pod zámkom, aby dvaja
        // súčasní záujemcovia nesadli na to isté posledné miesto.
        $waitlisted = DB::transaction(function () use ($event, $type, $user) {
            $locked = TicketType::query()->lockForUpdate()->findOrFail($type->id);

            if ($locked->capacity === null || $this->remainingSeats($locked) > 0) {
                return null;
            }

            return $this->createWaitlistAdmission($event, $locked, $user);
        });

        if ($waitlisted !== null) {
            $waitlisted->load(['ticketType', 'event', 'ticket']);

            Notification::route('mail', $waitlisted->ticket->holder_email)
                ->notify(new WorkshopWaitlisted($waitlisted, $this->waitlistPosition($waitlisted)));

            return $waitlisted;
        }

        $order = $this->issueForEvent($event, [
            'user_id' => $user->id,
            'holder_name' => $user->displayName(),
            'holder_email' => $user->email,
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 1]],
        ]);

        return $order->admissions()->firstOrFail();
    }

    /**
     * Odhlásenie z workshopu — zruší používateľove miesta (aj miesto v čakačke).
     * Ak sa tým uvoľnilo miesto, posunie prvého náhradníka.
     */
    public function leaveWorkshop(Event $event, TicketType $type, User $user): void
    {
        $this->assertWorkshopChangeable($event, $type, joining: false);

        $freedSeat = DB::transaction(function () use ($type, $user) {
            $admissions = $this->userWorkshopAdmissions($type, $user)->lockForUpdate()->get();

            if ($admissions->isEmpty()) {
                abort(422, __('tickets.errors.workshop_not_joined'));
            }

            // Miesto sa uvoľní len zrušením platného miesta, nie odchodom z čakačky.
            $freed = $admissions->contains(fn ($a) => $a->status === AdmissionStatus::Valid);

            foreach ($admissions as $admission) {
                $this->markCancelled($admission);
            }

            $this->cancelEmptyOrders($admissions->pluck('ticket_id'));

            return $freed;
        });

        if ($freedSeat) {
            $this->promoteFromWaitlist($type);
        }
    }

    /**
     * Samoobslužné zrušenie registrácie prihláseného používateľa na podujatie.
     * Zruší jeho objednávky s platnou hlavnou vstupenkou (aj naviazané workshopy)
     * a uvoľnené workshopové miesta posunie prvému náhradníkovi (FIFO).
     */
    public function cancelOwnRegistration(Event $event, User $user): void
    {
        $freedTypes = DB::transaction(function () use ($event, $user) {
            /** @var \Illuminate\Support\Collection<int, Ticket> $tickets */
            $tickets = Ticket::query()
                ->where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->where('status', '!=', TicketStatus::Cancelled->value)
                ->with('admissions.ticketType')
                ->lockForUpdate()
                ->get();

            // „Registrácia na podujatie" = platná hlavná vstupenka (nie len workshop).
            $hasMainSeat = $tickets
                ->flatMap(fn (Ticket $t) => $t->admissions)
                ->contains(fn (Admission $a) => $a->status === AdmissionStatus::Valid
                    && ! ($a->ticketType?->isWorkshop() ?? false));

            if (! $hasMainSeat) {
                abort(422, __('tickets.errors.event_not_joined'));
            }

            $freed = collect();

            foreach ($tickets as $ticket) {
                $freed = $freed->merge(
                    $ticket->admissions
                        ->where('status', AdmissionStatus::Valid)
                        ->pluck('ticketType')
                        ->filter(fn (?TicketType $t) => $t?->isWorkshop()),
                );

                $ticket->update(['status' => TicketStatus::Cancelled->value]);
                $this->cancelAdmissionsOf($ticket);
            }

            return $freed->filter()->unique('id');
        });

        foreach ($freedTypes as $type) {
            $this->promoteFromWaitlist($type);
        }
    }

    /**
     * Uvoľnilo sa miesto → ponúkne ho prvému náhradníkovi (FIFO).
     *
     * Miesto mu držíme, ale ešte nie je jeho — vstupenku s QR kódom dostane až
     * po potvrdení ponuky. Ak do lehoty nepotvrdí, ponuka prepadne (cron
     * `app:tickets-expire-unconfirmed`) a miesto ide ďalšiemu v poradí.
     * Volá sa po každom zrušení platného miesta na workshope.
     */
    public function promoteFromWaitlist(TicketType $type): ?Admission
    {
        if (! $type->isWorkshop()) {
            return null;
        }

        $event = $type->event()->first();

        // Po začiatku podujatia už čakačku neposúvame.
        if ($event === null || $event->workshopChangesLocked()) {
            return null;
        }

        $confirmation = app(AttendeeConfirmation::class);

        $promoted = DB::transaction(function () use ($type, $confirmation) {
            $locked = TicketType::query()->lockForUpdate()->find($type->id);

            if (! $locked || ! $locked->is_active || $this->remainingSeats($locked) <= 0) {
                return null;
            }

            /** @var Admission|null $next */
            $next = Admission::query()
                ->where('ticket_type_id', $locked->id)
                ->where('status', AdmissionStatus::Waitlisted->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            // Valid = miesto je obsadené (ráta sa do kapacity), aby ho medzitým
            // nedostal nikto iný. Pending = ešte ho však musí prijať.
            $next->update([
                'status' => AdmissionStatus::Valid->value,
                'confirmation_status' => AttendeeConfirmationStatus::Pending->value,
                'confirmation_token' => (string) Str::random(64),
                'confirmation_deadline_at' => $confirmation->deadlineFor(
                    $next->ticket,
                    (int) config('tickets.waitlist_confirmation_hours', 24),
                    $locked->starts_at,
                ),
                'meta' => array_merge((array) $next->meta, ['from_waitlist' => true]),
            ]);

            return $next;
        });

        if ($promoted === null) {
            return null;
        }

        $promoted->load(['ticketType', 'event', 'ticket']);

        Notification::route('mail', $promoted->attendee_email ?: $promoted->ticket->holder_email)
            ->notify(new WorkshopSeatGranted($promoted));

        return $promoted;
    }

    /** Id workshopov podujatia, na ktoré je používateľ práve prihlásený. */
    public function joinedWorkshopTypeIds(Event $event, User $user): array
    {
        return $this->userWorkshopTypeIds($event, $user, AdmissionStatus::Valid);
    }

    /** Id workshopov podujatia, na ktorých je používateľ náhradníkom. */
    public function waitlistedWorkshopTypeIds(Event $event, User $user): array
    {
        return $this->userWorkshopTypeIds($event, $user, AdmissionStatus::Waitlisted);
    }

    /** Poradie náhradníka v čakačke (1 = najbližší na rade). */
    public function waitlistPosition(Admission $admission): int
    {
        return 1 + (int) Admission::query()
            ->where('ticket_type_id', $admission->ticket_type_id)
            ->where('status', AdmissionStatus::Waitlisted->value)
            ->where('id', '<', $admission->id)
            ->count();
    }

    /** Počet náhradníkov na workshope. */
    public function waitlistCount(TicketType $type): int
    {
        return (int) Admission::query()
            ->where('ticket_type_id', $type->id)
            ->where('status', AdmissionStatus::Waitlisted->value)
            ->count();
    }

    private function userWorkshopTypeIds(Event $event, User $user, AdmissionStatus $status): array
    {
        return Admission::query()
            ->where('event_id', $event->id)
            ->where('status', $status->value)
            ->whereHas('ticketType', fn ($t) => $t->where('kind', TicketTypeKind::Workshop->value))
            ->whereHas('ticket', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', '!=', TicketStatus::Cancelled->value))
            ->pluck('ticket_type_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Zruší miesta objednávky a poznačí, z akého stavu — vďaka tomu vie
     * obnovenie vrátiť náhradníka späť do čakačky a nie rovno na miesto.
     */
    private function cancelAdmissionsOf(Ticket $ticket): void
    {
        $admissions = $ticket->admissions()
            ->whereIn('status', [AdmissionStatus::Valid->value, AdmissionStatus::Waitlisted->value])
            ->get();

        foreach ($admissions as $admission) {
            $this->markCancelled($admission, withOrder: true);
        }
    }

    /** Zrušenie miesta so značkou pôvodného stavu (pre neskoršie obnovenie). */
    private function markCancelled(Admission $admission, bool $withOrder = false): void
    {
        $admission->update([
            'status' => AdmissionStatus::Cancelled->value,
            'meta' => array_merge((array) $admission->meta, array_filter([
                'cancelled_from' => $admission->status->value,
                'cancelled_with_order' => $withOrder ?: null,
            ], fn ($v) => $v !== null)),
        ]);
    }

    /** Vrátenie miesta do stavu spred zrušenia (platné miesto / čakačka). */
    private function markRestored(Admission $admission): void
    {
        $admission->update([
            'status' => $this->cancelledFrom($admission)->value,
            'meta' => Arr::except((array) $admission->meta, ['cancelled_from', 'cancelled_with_order']),
        ]);
    }

    /** Stav, v ktorom bolo miesto pred zrušením (staré zrušenia značku nemajú). */
    private function cancelledFrom(Admission $admission): AdmissionStatus
    {
        return AdmissionStatus::tryFrom((string) ($admission->meta['cancelled_from'] ?? ''))
            ?? AdmissionStatus::Valid;
    }

    /**
     * Ochrana pred „prebookovaním" pri obnovení — miesto medzitým mohol dostať
     * niekto iný (napr. náhradník z čakačky).
     *
     * @param  \Illuminate\Support\Collection<int, Admission>  $admissions
     */
    private function assertSeatsAvailableFor($admissions): void
    {
        $seats = $admissions
            ->filter(fn (Admission $a) => $this->cancelledFrom($a) === AdmissionStatus::Valid)
            ->filter(fn (Admission $a) => $a->ticketType !== null)
            ->groupBy('ticket_type_id');

        foreach ($seats as $group) {
            /** @var TicketType $type */
            $type = $group->first()->ticketType;

            if ($group->count() > $this->remainingSeats($type)) {
                abort(422, __('tickets.errors.restore_capacity', ['name' => $type->name]));
            }
        }
    }

    /** Stav, do ktorého sa objednávka vracia po obnovení. */
    private function reactivatedStatus(Ticket $ticket): TicketStatus
    {
        return $ticket->payment_status === TicketPaymentStatus::Pending
            ? TicketStatus::Reserved
            : TicketStatus::Confirmed;
    }

    /**
     * Po obnovení pošleme objednávateľovi vstupenky znova — QR kódy z pôvodného
     * e-mailu sú síce platné, ale medzitým dostal potvrdenie o zrušení.
     */
    private function notifyRestored(?Ticket $ticket): void
    {
        if ($ticket === null || $ticket->holder_email === null) {
            return;
        }

        $ticket = $ticket->fresh(['event', 'admissions.ticketType']);

        if ($ticket === null || $ticket->admissions_total === 0) {
            return;
        }

        Notification::route('mail', $ticket->holder_email)
            ->notify(new TicketIssued($ticket, restored: true));
    }

    /**
     * Radenie zoznamu prihlásených. Okrem poradia objednávok (id = najnovšie /
     * najstaršie) vie radiť podľa priezviska — v zozname pri vchode sa hľadá
     * práve podľa neho, nie podľa krstného mena.
     *
     * Priezvisko = posledné slovo mena (Ing. Gabriel Gajdoš → Gajdoš);
     * jednoslovné meno („gajdosgabo") ostáva samo sebou.
     *
     * Radí sa podľa `id`, nie `created_at` — dve objednávky vytvorené v tej
     * istej sekunde by inak mali nestabilné poradie a stránkovanie by mohlo
     * jednu z nich preskočiť.
     */
    private function applyAttendeeSort($query, ?string $sort): void
    {
        $surname = "SUBSTRING_INDEX(TRIM(tickets.holder_name), ' ', -1)";

        match ($sort) {
            'oldest' => $query->reorder()->orderBy('tickets.id'),
            'surname' => $query->reorder()->orderByRaw("$surname ASC")->orderBy('tickets.holder_name'),
            'surname_desc' => $query->reorder()->orderByRaw("$surname DESC")->orderByDesc('tickets.holder_name'),
            default => $query->reorder()->orderByDesc('tickets.id'),
        };
    }

    /** Voľné miesta workshopu (null kapacita = neobmedzené → veľké číslo). */
    private function remainingSeats(TicketType $type): int
    {
        if ($type->capacity === null) {
            return PHP_INT_MAX;
        }

        $taken = (int) Admission::query()
            ->where('ticket_type_id', $type->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->count();

        return max(0, $type->capacity - $taken);
    }

    /** Zaradenie medzi náhradníkov — objednávka bez platného miesta. */
    private function createWaitlistAdmission(Event $event, TicketType $type, User $user): Admission
    {
        // Aj náhradník musí byť účastníkom podujatia — okrem otvorených workshopov
        // a podujatí bez hlavného typu vstupenky.
        if (! $type->isOpenWorkshop()
            && $this->eventHasActiveMainType($event)
            && $this->existingMainSeats($event, $user->id, $user->email) === 0) {
            abort(422, __('tickets.errors.workshop_requires_ticket'));
        }

        $order = Ticket::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'holder_name' => $user->displayName(),
            'holder_email' => $user->email,
            'quantity' => 1,
            'status' => TicketStatus::Reserved->value,
            'payment_status' => TicketPaymentStatus::None->value,
        ]);

        // Meno + e-mail sú tu povinné: keď na náhradníka príde rad, ponuku miesta
        // preňho hľadáme (a posielame) práve podľa e-mailu účastníka.
        return Admission::create([
            'ticket_id' => $order->id,
            'ticket_type_id' => $type->id,
            'event_id' => $event->id,
            'attendee_name' => $user->displayName(),
            'attendee_email' => mb_strtolower(trim((string) $user->email)),
            'status' => AdmissionStatus::Waitlisted->value,
        ]);
    }

    /** Objednávka bez platného miesta sa zruší celá. */
    private function cancelEmptyOrders($ticketIds): void
    {
        foreach (collect($ticketIds)->unique() as $ticketId) {
            $ticket = Ticket::query()->find($ticketId);

            if ($ticket && ! $ticket->admissions()->whereIn('status', [
                AdmissionStatus::Valid->value,
                AdmissionStatus::Waitlisted->value,
            ])->exists()) {
                $ticket->update(['status' => TicketStatus::Cancelled->value]);
            }
        }
    }

    private function assertWorkshopChangeable(Event $event, TicketType $type, bool $joining = true): void
    {
        if (! $type->isWorkshop() || $type->event_id !== $event->id) {
            abort(404);
        }

        if ($event->workshopChangesLocked()) {
            abort(422, __($joining
                ? 'tickets.errors.workshop_locked_join'
                : 'tickets.errors.workshop_locked_leave'));
        }
    }

    /** Aktívne miesta používateľa na workshope — platné aj miesto v čakačke. */
    private function userWorkshopAdmissions(TicketType $type, User $user)
    {
        return Admission::query()
            ->where('ticket_type_id', $type->id)
            ->whereIn('status', [AdmissionStatus::Valid->value, AdmissionStatus::Waitlisted->value])
            ->whereHas('ticket', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', '!=', TicketStatus::Cancelled->value));
    }

    /**
     * Má podujatie aspoň jeden aktívny hlavný (nie workshop) typ vstupenky?
     * Ak nie, workshopy sú samostatné registrácie a neviažu sa na hlavnú vstupenku.
     */
    private function eventHasActiveMainType(Event $event): bool
    {
        return $event->ticketTypes()
            ->where('is_active', true)
            ->where('kind', '!=', TicketTypeKind::Workshop->value)
            ->exists();
    }

    /**
     * Počet platných hlavných vstupeniek, ktoré už objednávateľ
     * (podľa účtu alebo e-mailu) na podujatí má.
     */
    private function existingMainSeats(Event $event, ?int $userId, ?string $email): int
    {
        $email = mb_strtolower(trim((string) $email));

        if ($userId === null && $email === '') {
            return 0;
        }

        return (int) Admission::query()
            ->mainSeats($event->id)
            ->whereHas('ticket', function ($q) use ($userId, $email) {
                $q->where('status', '!=', TicketStatus::Cancelled->value)
                    ->where(function ($qq) use ($userId, $email) {
                        if ($userId !== null) {
                            $qq->orWhere('user_id', $userId);
                        }
                        if ($email !== '') {
                            $qq->orWhereRaw('LOWER(holder_email) = ?', [$email]);
                        }
                    });
            })
            ->count();
    }

    /**
     * Prijme nový items[] tvar, ale je spätne kompatibilný so starým
     * payloadom (len quantity → default typ lístka podujatia).
     */
    private function normalizeItems(Event $event, array $properties): array
    {
        $items = $properties['items'] ?? [];

        if (! empty($items)) {
            return $items;
        }

        $type = TicketType::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        // Podujatie má povolené lístky, ale zatiaľ nemá nakonfigurovaný žiadny
        // typ – vytvoríme predvolený z ceny podujatia (spätná kompatibilita so
        // starým „len quantity" payloadom).
        if (! $type) {
            $type = $event->ticketTypes()->create([
                'name' => 'Vstupenka',
                'price_amount' => $event->price_amount,
                'price_currency' => $event->price_currency ?? 'EUR',
                'is_active' => true,
            ]);
        }

        return [[
            'ticket_type_id' => $type->id,
            'quantity' => max(1, (int) ($properties['quantity'] ?? 1)),
        ]];
    }

    public function findByUuid(string $uuid): ?Ticket
    {
        return Ticket::query()->where('uuid', $uuid)->first();
    }

    public function findAdmissionByUuid(string $uuid): ?Admission
    {
        return Admission::query()->where('uuid', $uuid)->first();
    }

    public function checkIn(string $qrToken, User $staff): array
    {
        return DB::transaction(function () use ($qrToken, $staff) {
            /** @var Admission|null $admission */
            $admission = Admission::query()->where('qr_token', $qrToken)->lockForUpdate()->first();

            if (! $admission) {
                return ['status' => 'invalid', 'reason' => 'not_found', 'admission' => null];
            }

            $admission->loadMissing('event', 'ticket', 'ticketType');
            Gate::forUser($staff)->authorize('checkin', $admission);

            if ($admission->status === AdmissionStatus::Cancelled) {
                return ['status' => 'invalid', 'reason' => 'cancelled', 'admission' => $admission];
            }

            // Náhradník ešte nemá miesto — pri vchode ho nepustíme.
            if ($admission->status === AdmissionStatus::Waitlisted) {
                return ['status' => 'invalid', 'reason' => 'waitlisted', 'admission' => $admission];
            }

            // Nepotvrdená rezervácia ešte nie je platná — účastník neklikol „Potvrdiť".
            if ($admission->isPendingConfirmation()) {
                return ['status' => 'invalid', 'reason' => 'unconfirmed', 'admission' => $admission];
            }

            if ($admission->checked_in_at !== null) {
                return ['status' => 'already_checked_in', 'admission' => $admission->fresh(['checkedInBy', 'ticket', 'ticketType'])];
            }

            $admission->update([
                'checked_in_at' => now(),
                'checked_in_by' => $staff->id,
            ]);

            return ['status' => 'checked_in', 'admission' => $admission->fresh(['checkedInBy', 'ticket', 'ticketType'])];
        });
    }

    public function manualCheckIn(int $admissionId, User $staff): array
    {
        return DB::transaction(function () use ($admissionId, $staff) {
            /** @var Admission|null $admission */
            $admission = Admission::query()->lockForUpdate()->find($admissionId);

            if (! $admission) {
                return ['status' => 'invalid', 'reason' => 'not_found', 'admission' => null];
            }

            $admission->loadMissing('event', 'ticket', 'ticketType');
            Gate::forUser($staff)->authorize('checkin', $admission);

            if ($admission->status === AdmissionStatus::Cancelled) {
                return ['status' => 'invalid', 'reason' => 'cancelled', 'admission' => $admission];
            }

            // Náhradník ešte nemá miesto — pri vchode ho nepustíme.
            if ($admission->status === AdmissionStatus::Waitlisted) {
                return ['status' => 'invalid', 'reason' => 'waitlisted', 'admission' => $admission];
            }

            // Nepotvrdená rezervácia ešte nie je platná — účastník neklikol „Potvrdiť".
            if ($admission->isPendingConfirmation()) {
                return ['status' => 'invalid', 'reason' => 'unconfirmed', 'admission' => $admission];
            }

            if ($admission->checked_in_at !== null) {
                return ['status' => 'already_checked_in', 'admission' => $admission->fresh(['checkedInBy', 'ticket', 'ticketType'])];
            }

            $admission->update([
                'checked_in_at' => now(),
                'checked_in_by' => $staff->id,
            ]);

            return ['status' => 'checked_in', 'admission' => $admission->fresh(['checkedInBy', 'ticket', 'ticketType'])];
        });
    }

    public function undoCheckIn(int $admissionId, User $staff): array
    {
        return DB::transaction(function () use ($admissionId, $staff) {
            /** @var Admission|null $admission */
            $admission = Admission::query()->lockForUpdate()->find($admissionId);

            if (! $admission) {
                return ['status' => 'invalid', 'reason' => 'not_found', 'admission' => null];
            }

            $admission->loadMissing('event', 'ticket', 'ticketType');
            Gate::forUser($staff)->authorize('checkin', $admission);

            $admission->update([
                'checked_in_at' => null,
                'checked_in_by' => null,
            ]);

            return ['status' => 'reverted', 'admission' => $admission->fresh(['ticket', 'ticketType'])];
        });
    }

    public function checkinStats(Event $event): array
    {
        Gate::authorize('view', $event);

        $base = Admission::query()
            ->where('event_id', $event->id)
            ->where('status', AdmissionStatus::Valid->value);

        $total = (int) (clone $base)->count();
        $arrived = (int) (clone $base)->whereNotNull('checked_in_at')->count();

        return [
            'total' => $total,
            'arrived' => $arrived,
            'remaining' => max(0, $total - $arrived),
        ];
    }

    /**
     * Prehľad pre bočný panel zoznamu prihlásených: koľko ľudí už prešlo
     * dverami, ako sú na tom objednávky, platby a jednotlivé typy lístkov.
     */
    public function attendeeSummary(Event $event): array
    {
        Gate::authorize('view', $event);

        $valid = fn () => Admission::query()
            ->where('event_id', $event->id)
            ->where('status', AdmissionStatus::Valid->value);

        $total = (int) $valid()->count();
        $arrived = (int) $valid()->whereNotNull('checked_in_at')->count();

        $orders = Ticket::query()
            ->where('event_id', $event->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (Ticket $row) => $row->status->value)
            ->map(fn (Ticket $row) => (int) $row->aggregate);

        // Peniaze rátame len zo živých objednávok — zrušená objednávka
        // už nie je ani pohľadávka, ani tržba.
        $payments = Ticket::query()
            ->where('event_id', $event->id)
            ->where('status', '!=', TicketStatus::Cancelled->value)
            ->selectRaw('payment_status, COUNT(*) as aggregate, COALESCE(SUM(price_amount), 0) as amount')
            ->groupBy('payment_status')
            ->get()
            ->keyBy(fn (Ticket $row) => $row->payment_status->value);

        $types = TicketType::query()
            ->where('event_id', $event->id)
            ->withCount([
                'admissions as sold' => fn ($q) => $q->where('status', AdmissionStatus::Valid->value),
                'admissions as arrived' => fn ($q) => $q
                    ->where('status', AdmissionStatus::Valid->value)
                    ->whereNotNull('checked_in_at'),
                'admissions as waitlisted' => fn ($q) => $q->where('status', AdmissionStatus::Waitlisted->value),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'admissions' => [
                'total' => $total,
                'arrived' => $arrived,
                'remaining' => max(0, $total - $arrived),
                'cancelled' => (int) Admission::query()
                    ->where('event_id', $event->id)
                    ->where('status', AdmissionStatus::Cancelled->value)
                    ->count(),
                'waitlisted' => (int) Admission::query()
                    ->where('event_id', $event->id)
                    ->where('status', AdmissionStatus::Waitlisted->value)
                    ->count(),
            ],
            'orders' => [
                'total' => (int) $orders->sum(),
                'confirmed' => (int) ($orders[TicketStatus::Confirmed->value] ?? 0),
                'reserved' => (int) ($orders[TicketStatus::Reserved->value] ?? 0),
                'cancelled' => (int) ($orders[TicketStatus::Cancelled->value] ?? 0),
            ],
            'payments' => [
                'currency' => $event->price_currency ?? 'EUR',
                'paid_amount' => (int) ($payments[TicketPaymentStatus::Paid->value]->amount ?? 0),
                'pending_amount' => (int) ($payments[TicketPaymentStatus::Pending->value]->amount ?? 0),
                'pending_count' => (int) ($payments[TicketPaymentStatus::Pending->value]->aggregate ?? 0),
            ],
            'types' => $types->map(fn (TicketType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'kind' => $type->kind->value,
                'capacity' => $type->capacity,
                'sold' => (int) $type->sold,
                'arrived' => (int) $type->arrived,
                'waitlisted' => (int) $type->waitlisted,
            ])->all(),
        ];
    }

    public function cancel($id): Ticket
    {
        [$ticket, $freedTypes] = DB::transaction(function () use ($id) {
            /** @var Ticket $ticket */
            $ticket = $this->find($id);
            Gate::authorize('update', $ticket);

            // Workshopy, na ktorých objednávka držala platné miesto — po zrušení
            // sa uvoľnia pre náhradníkov.
            $freed = $ticket->admissions()
                ->where('status', AdmissionStatus::Valid->value)
                ->with('ticketType')
                ->get()
                ->pluck('ticketType')
                ->filter(fn (?TicketType $t) => $t?->isWorkshop())
                ->unique('id');

            $ticket->update(['status' => TicketStatus::Cancelled->value]);
            $this->cancelAdmissionsOf($ticket);

            return [$ticket->fresh(['admissions.ticketType']), $freed];
        });

        foreach ($freedTypes as $type) {
            $this->promoteFromWaitlist($type);
        }

        return $ticket;
    }

    public function cancelAdmission(int $admissionId): Admission
    {
        [$admission, $freedType] = DB::transaction(function () use ($admissionId) {
            /** @var Admission $admission */
            $admission = Admission::query()->findOrFail($admissionId);
            $admission->loadMissing('event', 'ticketType');
            Gate::authorize('update', $admission);

            $freed = $admission->status === AdmissionStatus::Valid && $admission->ticketType?->isWorkshop()
                ? $admission->ticketType
                : null;

            $this->markCancelled($admission);

            return [$admission->fresh(['ticketType']), $freed];
        });

        if ($freedType !== null) {
            $this->promoteFromWaitlist($freedType);
        }

        return $admission;
    }

    /**
     * Obnovenie zrušenej objednávky — vráti ju aj s miestami späť do hry.
     *
     * Späť idú len miesta zrušené spolu s objednávkou; tie, ktoré organizátor
     * zrušil jednotlivo skôr, zostávajú zrušené (obnoví ich `restoreAdmission`).
     * Náhradník sa vracia do čakačky, nie na miesto.
     */
    public function restoreCancelled($id): Ticket
    {
        $ticket = DB::transaction(function () use ($id) {
            /** @var Ticket $ticket */
            $ticket = $this->find($id);
            Gate::authorize('update', $ticket);

            if ($ticket->status !== TicketStatus::Cancelled) {
                abort(422, __('tickets.errors.restore_not_cancelled'));
            }

            $cancelled = $ticket->admissions()
                ->where('status', AdmissionStatus::Cancelled->value)
                ->with('ticketType')
                ->lockForUpdate()
                ->get();

            $withOrder = $cancelled->filter(fn (Admission $a) => (bool) ($a->meta['cancelled_with_order'] ?? false));

            // Zrušenia spred zavedenia značky ju nemajú — vtedy vraciame všetko.
            $restoring = $withOrder->isNotEmpty() ? $withOrder : $cancelled;

            $this->assertSeatsAvailableFor($restoring);

            foreach ($restoring as $admission) {
                $this->markRestored($admission);
            }

            $ticket->update(['status' => $this->reactivatedStatus($ticket)->value]);

            return $ticket->fresh(['admissions.ticketType', 'admissions.checkedInBy']);
        });

        $this->notifyRestored($ticket);

        return $ticket;
    }

    /** Obnovenie jednej zrušenej vstupenky (miesta) v objednávke. */
    public function restoreAdmission(int $admissionId): Admission
    {
        $admission = DB::transaction(function () use ($admissionId) {
            /** @var Admission $admission */
            $admission = Admission::query()->lockForUpdate()->findOrFail($admissionId);
            $admission->loadMissing('event', 'ticket', 'ticketType');
            Gate::authorize('update', $admission);

            if ($admission->status !== AdmissionStatus::Cancelled) {
                abort(422, __('tickets.errors.restore_not_cancelled_seat'));
            }

            $this->assertSeatsAvailableFor(collect([$admission]));
            $this->markRestored($admission);

            // Objednávka nemôže zostať zrušená, keď opäť drží platné miesto.
            $ticket = $admission->ticket;

            if ($ticket !== null && $ticket->status === TicketStatus::Cancelled) {
                $ticket->update(['status' => $this->reactivatedStatus($ticket)->value]);
            }

            return $admission->fresh(['ticketType', 'ticket']);
        });

        $this->notifyRestored($admission->ticket);

        return $admission;
    }

    /**
     * Zmazanie zrušenej objednávky zo zoznamu prihlásených (soft delete).
     * Nikomu sa nič neposiela — je to len upratanie zoznamu.
     */
    public function deleteCancelled($id): void
    {
        DB::transaction(function () use ($id) {
            /** @var Ticket $ticket */
            $ticket = $this->find($id);
            Gate::authorize('update', $ticket);

            if ($ticket->status !== TicketStatus::Cancelled) {
                abort(422, __('tickets.errors.delete_not_cancelled'));
            }

            $ticket->admissions()->delete();
            $ticket->delete();
        });
    }

    /** Zmazanie jednej zrušenej vstupenky zo zoznamu (soft delete, bez e-mailu). */
    public function deleteAdmission(int $admissionId): void
    {
        DB::transaction(function () use ($admissionId) {
            /** @var Admission $admission */
            $admission = Admission::query()->findOrFail($admissionId);
            $admission->loadMissing('event');
            Gate::authorize('update', $admission);

            if ($admission->status !== AdmissionStatus::Cancelled) {
                abort(422, __('tickets.errors.delete_not_cancelled_seat'));
            }

            $admission->delete();
        });
    }

    /** Potvrdenie rezervácie organizátorom (napr. po dohode mimo systému). */
    public function confirm($id): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->find($id);
        Gate::authorize('update', $ticket);

        if ($ticket->status !== TicketStatus::Reserved) {
            abort(422, __('tickets.errors.confirm_not_reserved'));
        }

        $ticket->update(['status' => TicketStatus::Confirmed->value]);

        return $ticket->fresh(['admissions.ticketType', 'admissions.checkedInBy']);
    }

    /**
     * Ručné označenie platby (prevod na účet, platba na mieste).
     * Uhradená rezervácia sa tým zároveň stáva potvrdenou objednávkou.
     */
    public function markPaid($id): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->find($id);
        Gate::authorize('update', $ticket);

        if (! in_array($ticket->payment_status, [TicketPaymentStatus::Pending, TicketPaymentStatus::Failed], true)) {
            abort(422, __('tickets.errors.payment_not_pending'));
        }

        $ticket->update([
            'payment_status' => TicketPaymentStatus::Paid->value,
            'status' => $ticket->status === TicketStatus::Reserved
                ? TicketStatus::Confirmed->value
                : $ticket->status->value,
        ]);

        return $ticket->fresh(['admissions.ticketType', 'admissions.checkedInBy']);
    }

    public function dashboardIndexForEvent(Event $event, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('view', $event);

        // `event` kvôli TicketPolicy — kontroluje kanál podujatia pri každom riadku.
        $query = Ticket::query()
            ->where('event_id', $event->id)
            ->with(['event', 'admissions.ticketType', 'admissions.checkedInBy']);

        if (! empty($filters['payment'])) {
            $query->where('payment_status', $filters['payment']);
        }

        // Typ lístka ani stav vstupu nie sú na objednávke, ale na jej miestach —
        // objednávka prejde filtrom, keď mu vyhovie aspoň jedno jej miesto.
        if (! empty($filters['ticket_type_id'])) {
            $query->whereHas('admissions', fn ($q) => $q->where('ticket_type_id', (int) $filters['ticket_type_id']));
        }

        if (($filters['checkin'] ?? '') === 'arrived') {
            $query->whereHas('admissions', fn ($q) => $q->whereNotNull('checked_in_at'));
        } elseif (($filters['checkin'] ?? '') === 'pending') {
            $query->whereHas('admissions', fn ($q) => $q
                ->where('status', AdmissionStatus::Valid->value)
                ->whereNull('checked_in_at'));
        }

        $this->applyAttendeeSort($query, $filters['sort'] ?? null);

        // Radenie si riešime sami (podľa priezviska), `bySort` by ho prepísalo.
        return $this->paginateFilteredQuery($query, $perPage, array_diff_key($filters, ['sort' => null]));
    }

    public function publicIndexQuery()
    {
        return $this->model()->newQuery()->whereRaw('1 = 0');
    }

    public function dashboardIndexQuery()
    {
        $canalIds = auth('sanctum')->user()?->dashboardCanalIds() ?? collect();

        return $this->latestFirst(
            $this->model()->whereHas('event', fn ($q) => $q->whereIn('canal_id', $canalIds))
        );
    }

    public function dashboardShow($id)
    {
        $ticket = $this->dashboardIndexQuery()->where('id', $id)->firstOrFail();
        Gate::authorize('view', $ticket);

        return $ticket;
    }
}
