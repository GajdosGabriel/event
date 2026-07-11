<?php

namespace App\Repositories\Contracts;

use App\Models\Admission;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
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

    /** Jednoklikové prihlásenie používateľa na workshop; pri plnom workshope zaradí medzi náhradníkov. */
    public function joinWorkshop(Event $event, TicketType $type, User $user): Admission;

    /** Odhlásenie používateľa z workshopu (aj z čakačky). */
    public function leaveWorkshop(Event $event, TicketType $type, User $user): void;

    /** Samoobslužné zrušenie registrácie používateľa na podujatie (posunie náhradníkov). */
    public function cancelOwnRegistration(Event $event, User $user): void;

    /** Posunie prvého náhradníka na uvoľnené miesto workshopu. */
    public function promoteFromWaitlist(TicketType $type): ?Admission;

    /** Id workshopov podujatia, na ktoré je používateľ prihlásený. */
    public function joinedWorkshopTypeIds(Event $event, User $user): array;

    /** Id workshopov podujatia, na ktorých je používateľ náhradníkom. */
    public function waitlistedWorkshopTypeIds(Event $event, User $user): array;

    /** Poradie náhradníka v čakačke (1 = najbližší na rade). */
    public function waitlistPosition(Admission $admission): int;

    /** Počet náhradníkov na workshope. */
    public function waitlistCount(TicketType $type): int;

    public function cancel($id): Ticket;
    public function cancelAdmission(int $admissionId): Admission;
    public function dashboardIndexForEvent(Event $event, int $perPage = 15, array $filters = []): LengthAwarePaginator;
}
