<?php

namespace App\Http\Controllers\Public;

use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdmissionResource;
use App\Models\Event;
use App\Models\TicketType;
use App\Notifications\TicketIssued;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class WorkshopRegistrationController extends Controller
{
    protected $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    /** Prihlásenie na workshop (jeden klik); pri plnom workshope zaradí medzi náhradníkov. */
    public function store($eventId, $typeId): JsonResponse
    {
        [$event, $type, $user] = $this->resolve($eventId, $typeId);

        $admission = $this->ticketRepository->joinWorkshop($event, $type, $user);

        // Náhradníkovi lístok s QR neposielame — o zaradení mu dal vedieť
        // repozitár (WorkshopWaitlisted), miesto ešte nemá.
        if ($admission->status !== AdmissionStatus::Waitlisted) {
            $ticket = $admission->ticket()->with(['event', 'admissions.ticketType'])->first();
            Notification::route('mail', $ticket->holder_email)->notify(new TicketIssued($ticket));
        }

        $admission->load(['ticketType', 'event']);

        return response()->json(new AdmissionResource($admission), 201);
    }

    /** Odhlásenie z workshopu. */
    public function destroy($eventId, $typeId): JsonResponse
    {
        [$event, $type, $user] = $this->resolve($eventId, $typeId);

        $this->ticketRepository->leaveWorkshop($event, $type, $user);

        return response()->json(['status' => 'ok']);
    }

    /** @return array{0: Event, 1: TicketType, 2: \App\Models\User} */
    private function resolve($eventId, $typeId): array
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            abort(401);
        }

        $event = Event::query()->findOrFail($eventId);

        if (! $event->tickets_enabled) {
            abort(422, 'Registrácia na tento event nie je povolená.');
        }

        $type = TicketType::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->findOrFail($typeId);

        return [$event, $type, $user];
    }
}
