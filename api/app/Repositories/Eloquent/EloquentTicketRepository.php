<?php

namespace App\Repositories\Eloquent;

use App\Enums\AdmissionStatus;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Enums\TicketTypeKind;
use App\Models\Admission;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\WorkshopSeatGranted;
use App\Notifications\WorkshopWaitlisted;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

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
                abort(422, 'Podujatie už prebehlo, registrácia nie je možná.');
            }

            if ($lockedEvent->registration_deadline_at !== null && $lockedEvent->registration_deadline_at->isPast()) {
                abort(422, 'Termín registrácie už uplynul.');
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
                    abort(422, 'Vybraný typ lístka nie je k dispozícii.');
                }

                if ($type->sale_starts_at !== null && $type->sale_starts_at->isFuture()) {
                    abort(422, 'Predaj lístka „' . $type->name . '" ešte nezačal.');
                }

                if ($type->sale_ends_at !== null && $type->sale_ends_at->isPast()) {
                    abort(422, 'Predaj lístka „' . $type->name . '" už skončil.');
                }

                if ($quantity < $type->min_per_order) {
                    abort(422, 'Minimálny počet lístkov „' . $type->name . '" na objednávku je ' . $type->min_per_order . '.');
                }

                if ($quantity > $type->max_per_order) {
                    abort(422, 'Maximálny počet lístkov „' . $type->name . '" na objednávku je ' . $type->max_per_order . '.');
                }

                if ($type->capacity !== null) {
                    $sold = (int) Admission::query()
                        ->where('ticket_type_id', $type->id)
                        ->where('status', AdmissionStatus::Valid->value)
                        ->lockForUpdate()
                        ->count();

                    $remaining = max(0, $type->capacity - $sold);

                    if ($quantity > $remaining) {
                        abort(422, 'Pre „' . $type->name . '" ' . ($remaining === 1 ? 'ostáva' : 'ostávajú') . ' už len ' . $remaining . '.');
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
                abort(422, 'Nevybrali ste žiadny lístok.');
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
                    abort(422, 'Na workshopy sa môžu prihlásiť len účastníci registrovaní na podujatie.');
                }

                foreach ($workshopLines as $line) {
                    if ($line['quantity'] > $entitlement) {
                        abort(422, 'Na workshop „' . $line['type']->name . '" môžete objednať najviac ' . $entitlement . ' ' . ($entitlement === 1 ? 'miesto' : ($entitlement <= 4 ? 'miesta' : 'miest')) . ' — podľa počtu vstupeniek na podujatie.');
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
                    Admission::create([
                        'ticket_id' => $order->id,
                        'ticket_type_id' => $line['type']->id,
                        'event_id' => $lockedEvent->id,
                        'attendee_name' => $line['attendees'][$i]['name'] ?? null,
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
            abort(422, 'Na tento workshop ste už prihlásený.');
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
            'holder_name' => $this->displayNameFor($user),
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
                abort(422, 'Na tento workshop nie ste prihlásený.');
            }

            // Miesto sa uvoľní len zrušením platného miesta, nie odchodom z čakačky.
            $freed = $admissions->contains(fn ($a) => $a->status === AdmissionStatus::Valid);

            foreach ($admissions as $admission) {
                $admission->update(['status' => AdmissionStatus::Cancelled->value]);
            }

            $this->cancelEmptyOrders($admissions->pluck('ticket_id'));

            return $freed;
        });

        if ($freedSeat) {
            $this->promoteFromWaitlist($type);
        }
    }

    /**
     * Uvoľnilo sa miesto → posunie prvého náhradníka (FIFO) a pošle mu lístok.
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

        $promoted = DB::transaction(function () use ($type) {
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

            $next->update(['status' => AdmissionStatus::Valid->value]);
            $next->ticket?->update(['status' => TicketStatus::Confirmed->value]);

            return $next;
        });

        if ($promoted === null) {
            return null;
        }

        $promoted->load(['ticketType', 'event', 'ticket']);

        Notification::route('mail', $promoted->ticket->holder_email)
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
            abort(422, 'Na workshopy sa môžu prihlásiť len účastníci registrovaní na podujatie.');
        }

        $order = Ticket::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'holder_name' => $this->displayNameFor($user),
            'holder_email' => $user->email,
            'quantity' => 1,
            'status' => TicketStatus::Reserved->value,
            'payment_status' => TicketPaymentStatus::None->value,
        ]);

        return Admission::create([
            'ticket_id' => $order->id,
            'ticket_type_id' => $type->id,
            'event_id' => $event->id,
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
            abort(422, $joining
                ? 'Podujatie už začalo — prihlásenie na workshopy sa už nedá meniť.'
                : 'Podujatie už začalo — odhlásenie z workshopu už nie je možné.');
        }
    }

    private function displayNameFor(User $user): string
    {
        return $user->pendingProfile?->display_name ?? strtok((string) $user->email, '@');
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
            $ticket->admissions()->update(['status' => AdmissionStatus::Cancelled->value]);

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

            $admission->update(['status' => AdmissionStatus::Cancelled->value]);

            return [$admission->fresh(['ticketType']), $freed];
        });

        if ($freedType !== null) {
            $this->promoteFromWaitlist($freedType);
        }

        return $admission;
    }

    public function dashboardIndexForEvent(Event $event, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('view', $event);

        $query = Ticket::query()
            ->where('event_id', $event->id)
            ->with(['admissions.ticketType', 'admissions.checkedInBy'])
            ->latest();

        return $this->paginateFilteredQuery($query, $perPage, $filters);
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
