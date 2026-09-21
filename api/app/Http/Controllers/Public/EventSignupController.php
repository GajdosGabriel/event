<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Tickets\EventSignup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prihlásenie prihláseného používateľa na podujatie jedným klikom — cieľ
 * tlačidla „Prihlásiť sa" z hlascirkvi.sk, keď už človek účet má (po
 * prihlásení ho sem pošle front). Nový účet ide cez registráciu s `event_id`.
 */
class EventSignupController extends Controller
{
    public function store(Request $request, EventSignup $signup, $eventId): JsonResponse
    {
        $event = $signup->findOpenEvent($eventId);

        if (! $event) {
            abort(422, __('tickets.errors.registration_disabled'));
        }

        $result = $signup->signup($event, $request->user());

        return response()->json([
            'data' => [
                'status' => $result['status'],
                'event' => ['id' => $event->id, 'name' => $event->name],
                'ticket_uuid' => $result['ticket']?->uuid,
            ],
        ]);
    }
}
