<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketStoreRequest;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Notifications\TicketIssued;
use App\Repositories\Contracts\TicketRepository;
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
        $properties['user_id'] = auth('sanctum')->id();

        $ticket = $this->ticketRepository->issueForEvent($event, $properties);
        $ticket->load('event');

        Notification::route('mail', $ticket->holder_email)->notify(new TicketIssued($ticket));

        return response()->json(new TicketResource($ticket), 201);
    }

    public function show($uuid): JsonResponse
    {
        $ticket = $this->ticketRepository->findByUuid($uuid);

        if (! $ticket) {
            abort(404);
        }

        $ticket->load('event');

        return response()->json(new TicketResource($ticket));
    }
}
