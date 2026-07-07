<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketTypeController extends Controller
{
    /** Verejný zoznam aktívnych typov lístkov pre registračný formulár. */
    public function index($eventId): AnonymousResourceCollection
    {
        $event = Event::query()->findOrFail($eventId);

        $types = $event->ticketTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return TicketTypeResource::collection($types);
    }
}
