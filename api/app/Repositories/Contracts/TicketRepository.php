<?php

namespace App\Repositories\Contracts;

use App\Models\Admission;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface TicketRepository extends InterfaceRepository
{
    public function issueForEvent(Event $event, array $properties): Ticket;
    public function findByUuid(string $uuid): ?Ticket;
    public function findAdmissionByUuid(string $uuid): ?Admission;

    /** Check-in vstupenky pomocou naskenovaného QR tokenu. */
    public function checkIn(string $qrToken, User $staff): array;

    /** Manuálny check-in vstupenky pri vchode (bez skenovania). */
    public function manualCheckIn(int $admissionId, User $staff): array;

    /** Zrušenie check-inu (omylom naskenované). */
    public function undoCheckIn(int $admissionId, User $staff): array;

    /** Štatistika príchodov pre podujatie. */
    public function checkinStats(Event $event): array;

    public function cancel($id): Ticket;
    public function cancelAdmission(int $admissionId): Admission;
    public function dashboardIndexForEvent(Event $event, int $perPage = 15, array $filters = []): LengthAwarePaginator;
    public function remainingCapacity(Event $event): ?int;
}
