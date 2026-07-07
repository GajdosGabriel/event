<?php

namespace App\Repositories\Eloquent;

use App\Enums\TicketPaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Event;
use App\Models\Ticket;
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

            $quantity = max(1, (int) ($properties['quantity'] ?? 1));

            if ($lockedEvent->capacity !== null) {
                $issuedSeats = (int) Ticket::query()
                    ->where('event_id', $lockedEvent->id)
                    ->whereIn('status', [TicketStatus::Reserved->value, TicketStatus::Confirmed->value])
                    ->lockForUpdate()
                    ->sum('quantity');

                $remaining = max(0, $lockedEvent->capacity - $issuedSeats);

                if ($remaining <= 0) {
                    abort(422, 'Event je už plne obsadený.');
                }

                if ($quantity > $remaining) {
                    abort(422, 'K dispozícii ' . ($remaining === 1 ? 'je' : 'sú') . ' už len ' . $remaining . ' ' . $this->seatsWord($remaining) . '.');
                }
            }

            $isPaid = (int) $lockedEvent->price_amount > 0;

            return Ticket::create([
                'event_id' => $lockedEvent->id,
                'user_id' => $properties['user_id'] ?? null,
                'holder_name' => $properties['holder_name'],
                'holder_email' => $properties['holder_email'],
                'holder_phone' => $properties['holder_phone'] ?? null,
                'quantity' => $quantity,
                'status' => $isPaid ? TicketStatus::Reserved->value : TicketStatus::Confirmed->value,
                'payment_status' => $isPaid ? TicketPaymentStatus::Pending->value : TicketPaymentStatus::None->value,
                'price_amount' => $isPaid ? $lockedEvent->price_amount : null,
                'price_currency' => $isPaid ? $lockedEvent->price_currency : null,
            ])->fresh();
        });
    }

    public function findByUuid(string $uuid): ?Ticket
    {
        return Ticket::query()->where('uuid', $uuid)->first();
    }

    public function findByQrToken(string $qrToken): ?Ticket
    {
        return Ticket::query()->where('qr_token', $qrToken)->first();
    }

    public function checkIn(string $qrToken, User $staff): array
    {
        return DB::transaction(function () use ($qrToken, $staff) {
            /** @var Ticket|null $ticket */
            $ticket = Ticket::query()->where('qr_token', $qrToken)->lockForUpdate()->first();

            if (! $ticket) {
                return ['status' => 'invalid', 'reason' => 'not_found', 'ticket' => null];
            }

            $ticket->loadMissing('event');
            Gate::forUser($staff)->authorize('checkin', $ticket);

            if ($ticket->status === TicketStatus::Cancelled) {
                return ['status' => 'invalid', 'reason' => 'cancelled', 'ticket' => $ticket];
            }

            if ($ticket->checked_in_at !== null) {
                return ['status' => 'already_checked_in', 'ticket' => $ticket->fresh(['checkedInBy'])];
            }

            $ticket->update([
                'checked_in_at' => now(),
                'checked_in_by' => $staff->id,
                'status' => TicketStatus::Confirmed->value,
            ]);

            return ['status' => 'checked_in', 'ticket' => $ticket->fresh(['checkedInBy'])];
        });
    }

    public function cancel($id): Ticket
    {
        /** @var Ticket $ticket */
        $ticket = $this->find($id);
        Gate::authorize('update', $ticket);

        $ticket->update(['status' => TicketStatus::Cancelled->value]);

        return $ticket->fresh();
    }

    public function dashboardIndexForEvent(Event $event, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        Gate::authorize('view', $event);

        $query = Ticket::query()->where('event_id', $event->id)->latest();

        return $this->paginateFilteredQuery($query, $perPage, $filters);
    }

    public function remainingCapacity(Event $event): ?int
    {
        if ($event->capacity === null) {
            return null;
        }

        $issued = (int) Ticket::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [TicketStatus::Reserved->value, TicketStatus::Confirmed->value])
            ->sum('quantity');

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
