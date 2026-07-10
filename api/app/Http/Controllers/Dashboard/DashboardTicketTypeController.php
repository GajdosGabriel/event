<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketTypeStoreRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardTicketTypeController extends Controller
{
    protected $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function index($eventId): AnonymousResourceCollection
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        $types = $event->ticketTypes()->orderBy('sort_order')->orderBy('id')->get();

        // Počet náhradníkov na workshopoch — organizátor ho vidí v prehľade.
        $types->each(fn ($type) => $type->isWorkshop()
            ? $type->setAttribute('waitlist_count', $this->ticketRepository->waitlistCount($type))
            : null);

        return TicketTypeResource::collection($types);
    }

    public function store(TicketTypeStoreRequest $request, $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $type = $event->ticketTypes()->create($request->validated());

        return response()->json(new TicketTypeResource($type), 201);
    }

    public function update(TicketTypeStoreRequest $request, $eventId, $typeId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $type = $event->ticketTypes()->findOrFail($typeId);
        $type->update($request->validated());

        return response()->json(new TicketTypeResource($type->fresh()));
    }

    public function destroy($eventId, $typeId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $type = $event->ticketTypes()->findOrFail($typeId);

        // Ak už boli k typu vydané vstupenky, len ho deaktivuj + soft-delete,
        // aby ostala zachovaná história objednávok.
        if ($type->admissions()->exists()) {
            $type->update(['is_active' => false]);
        }

        $type->delete();

        return response()->json(['status' => 'ok']);
    }
}
