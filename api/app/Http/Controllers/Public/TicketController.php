<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketStoreRequest;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Notifications\TicketIssued;
use App\Repositories\Contracts\TicketRepository;
use App\Services\Tickets\AttendeeRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class TicketController extends Controller
{
    protected $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function store(TicketStoreRequest $request, $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);

        if (! $event->tickets_enabled) {
            abort(422, 'Registrácia na tento event nie je povolená.');
        }

        $properties = $request->validated();

        $user = auth('sanctum')->user();
        $properties['user_id'] = $user?->id;

        // One-click rezervácia: prihlásenému doplníme meno a e-mail z účtu.
        if ($user) {
            $properties['holder_email'] = $properties['holder_email']
                ?? $user->email;
            $properties['holder_name'] = $properties['holder_name']
                ?? $user->pendingProfile?->display_name
                ?? strtok((string) $user->email, '@');
        }

        $ticket = $this->ticketRepository->issueForEvent($event, $properties);
        $ticket->load(['event', 'admissions.ticketType']);

        Notification::route('mail', $ticket->holder_email)->notify(new TicketIssued($ticket));

        // Ďalší účastníci (vstupenky 2..n): účet + osobný kanál + e-mail s lístkom.
        app(AttendeeRegistrar::class)->registerAndNotify($ticket);

        return response()->json(new TicketResource($ticket), 201);
    }

    public function show($uuid): JsonResponse
    {
        $ticket = $this->ticketRepository->findByUuid($uuid);

        if (! $ticket) {
            abort(404);
        }

        $ticket->load(['event', 'admissions.ticketType']);

        return response()->json(new TicketResource($ticket));
    }
}
