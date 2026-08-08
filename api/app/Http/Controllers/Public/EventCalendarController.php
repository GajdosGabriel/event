<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use App\Services\Calendar\IcsGenerator;
use Illuminate\Http\Response;

/**
 * Stiahnutie podujatia ako `.ics`. Odkaz vedie z e-mailu o vystavenom lístku —
 * ten istý súbor je v e-maile aj ako príloha, toto je záchranná cesta pre
 * klientov, ktorí prílohy nezobrazia.
 *
 * Viditeľnosť rieši `publicShow()` (len publikované) — rovnako ako verejný detail.
 */
class EventCalendarController extends Controller
{
    public function __construct(
        protected EventRepository $eventRepository
    ) {}

    public function show($id, IcsGenerator $generator): Response
    {
        $event = $this->eventRepository->publicShow($id);

        if (! $event) {
            abort(404);
        }

        $ics = $generator->forEvent($event);

        // Podujatie bez termínu nemá čo zapísať do kalendára.
        if ($ics === null) {
            abort(404);
        }

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$generator->filename($event).'"',
        ]);
    }
}
