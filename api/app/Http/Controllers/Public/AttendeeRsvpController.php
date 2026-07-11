<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Services\Tickets\AttendeeConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Verejné (tokenom chránené) potvrdenie účasti účastníkom z e-mailu.
 * Nevyžaduje prihlásenie — token v odkaze je autorizáciou.
 */
class AttendeeRsvpController extends Controller
{
    public function __construct(
        private AttendeeConfirmation $confirmation,
    ) {
    }

    public function show(string $token): JsonResponse
    {
        return response()->json($this->present($this->resolve($token)));
    }

    public function confirm(string $token): JsonResponse
    {
        $group = $this->resolve($token);
        $this->confirmation->confirm($group);

        return response()->json($this->present($this->resolve($token)));
    }

    public function decline(string $token): JsonResponse
    {
        $group = $this->resolve($token);
        $this->confirmation->decline($group);

        return response()->json($this->present($this->resolve($token)));
    }

    /** @return Collection<int, Admission> */
    private function resolve(string $token): Collection
    {
        $group = $this->confirmation->groupForToken($token);

        if ($group === null || $group->isEmpty()) {
            abort(404);
        }

        return $group;
    }

    /** @param Collection<int, Admission> $group */
    private function present(Collection $group): array
    {
        /** @var Admission $first */
        $first = $group->first();
        $ticket = $first->ticket;
        $event = $ticket?->event;

        return [
            'status' => $first->confirmation_status?->value,
            'status_label' => $first->confirmation_status?->label(),
            'attendee_name' => $first->attendee_name,
            'holder_name' => $ticket?->holder_name,
            'is_paid' => (int) ($ticket?->price_amount ?? 0) > 0,
            'deadline_at' => $first->confirmation_deadline_at,
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'date_range_label' => $this->dateRangeLabel($event),
            ] : null,
            'seats' => $group->map(fn (Admission $a) => [
                'label' => $a->attendee_name ?: 'Vstupenka',
                'type' => $a->ticketType?->name,
            ])->values(),
        ];
    }

    private function dateRangeLabel(\App\Models\Event $event): ?string
    {
        $start = $event->start_at;
        $end = $event->end_at;

        if ($start === null) {
            return null;
        }

        if ($end === null) {
            return $start->format('d. m. Y H:i');
        }

        return $start->isSameDay($end)
            ? sprintf('%s - %s', $start->format('d. m. Y H:i'), $end->format('H:i'))
            : sprintf('%s - %s', $start->format('d. m. Y H:i'), $end->format('d. m. Y H:i'));
    }
}
