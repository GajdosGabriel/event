<?php

namespace App\Repositories\Contracts;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface TicketRepository extends InterfaceRepository
{
    public function issueForEvent(Event $event, array $properties): Ticket;
    public function findByUuid(string $uuid): ?Ticket;
    public function findByQrToken(string $qrToken): ?Ticket;
    public function checkIn(string $qrToken, User $staff): array;
    public function cancel($id): Ticket;
    public function dashboardIndexForEvent(Event $event, int $perPage = 15, array $filters = []): LengthAwarePaginator;
    public function remainingCapacity(Event $event): ?int;
}
