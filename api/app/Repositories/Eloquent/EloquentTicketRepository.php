<?php

namespace App\Repositories\Eloquent;

use App\Enums\AdmissionStatus;
use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Admission;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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

            // Kapacita na úrovni podujatia (naprieč typmi).
            if ($lockedEvent->capacity !== null) {
                $issued = (int) Admission::query()
                    ->where('event_id', $lockedEvent->id)
                    ->where('status', AdmissionStatus::Valid->value)
                    ->lockForUpdate()
                    ->count();

                $remaining = max(0, $lockedEvent->capacity - $issued);

                if ($remaining <= 0) {
                    abort(422, 'Event je už plne obsadený.');
                }

                if ($totalSeats > $remaining) {
                    abort(422, 'K dispozícii ' . ($remaining === 1 ? 'je' : 'sú') . ' už len ' . $remaining . ' ' . $this->seatsWord($remaining) . '.');
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
        // typ – vytvoríme predvolený z ceny/kapacity podujatia (spätná
        // kompatibilita so starým „len quantity" payloadom).
        if (! $type) {
            $type = $event->ticketTypes()->create([
                'name' => 'Vstupenka',
                'price_amount' => $event->price_amount,
                'price_currency' => $event->price_currency ?? 'EUR',
                'capacity' => $event->capacity,
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
        return DB::transaction(function () use ($id) {
            /** @var Ticket $ticket */
            $ticket = $this->find($id);
            Gate::authorize('update', $ticket);

            $ticket->update(['status' => TicketStatus::Cancelled->value]);
            $ticket->admissions()->update(['status' => AdmissionStatus::Cancelled->value]);

            return $ticket->fresh(['admissions.ticketType']);
        });
    }

    public function cancelAdmission(int $admissionId): Admission
    {
        return DB::transaction(function () use ($admissionId) {
            /** @var Admission $admission */
            $admission = Admission::query()->findOrFail($admissionId);
            $admission->loadMissing('event');
            Gate::authorize('update', $admission);

            $admission->update(['status' => AdmissionStatus::Cancelled->value]);

            return $admission->fresh(['ticketType']);
        });
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

    public function remainingCapacity(Event $event): ?int
    {
        if ($event->capacity === null) {
            return null;
        }

        $issued = (int) Admission::query()
            ->where('event_id', $event->id)
            ->where('status', AdmissionStatus::Valid->value)
            ->count();

        return max(0, $event->capacity - $issued);
    }

    private function seatsWord(int $count): string
    {
        return $count >= 2 && $count <= 4 ? 'voľné miesta' : 'voľných miest';
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
