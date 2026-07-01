<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketCheckinRequest;
use App\Http\Resources\TicketDashboardResource;
use App\Models\Event;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardTicketController extends Controller
{
    protected $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function index(Request $request, $eventId): AnonymousResourceCollection
    {
        $event = Event::query()->findOrFail($eventId);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:250'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $data = $this->ticketRepository->dashboardIndexForEvent(
            $event,
            $filters['per_page'] ?? 15,
            ['search' => $filters['search'] ?? null]
        );

        return TicketDashboardResource::collection($data);
    }

    public function show($id): JsonResponse
    {
        $ticket = $this->ticketRepository->dashboardShow($id);

        return response()->json(new TicketDashboardResource($ticket->load(['event', 'checkedInBy'])));
    }

    public function update($id): JsonResponse
    {
        $ticket = $this->ticketRepository->cancel($id);

        return response()->json(new TicketDashboardResource($ticket->load(['event', 'checkedInBy'])));
    }

    public function checkin(TicketCheckinRequest $request): JsonResponse
    {
        $result = $this->ticketRepository->checkIn($request->validated()['qr_token'], $request->user());

        return response()->json([
            'status' => $result['status'],
            'reason' => $result['reason'] ?? null,
            'ticket' => $result['ticket']
                ? new TicketDashboardResource($result['ticket']->load(['event', 'checkedInBy']))
                : null,
        ]);
    }
}
