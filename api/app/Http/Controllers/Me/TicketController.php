<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Services\Tickets\TicketOwnership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * „Moje lístky" — vstupenky prihláseného účtu.
 *
 * Doteraz sa k vydanému lístku dalo dostať **len cez odkaz `/tickets/{uuid}`
 * z e-mailu**: kto si e-mail zmazal, prišiel o vstupenku, a účet nemal
 * v aplikácii jedinú stránku, ktorá by patrila jemu — dashboard je celý
 * o organizovaní. Tento výpis je tá stránka.
 *
 * Detail vstupenky s QR kódom zostáva na `/tickets/{uuid}`, kam vedie aj odkaz
 * z e-mailu. Výpis naň len odkazuje: rovnaká stránka pre oba vstupy znamená
 * jedno miesto, kde sa QR kód vykresľuje a rieši čakačka či potvrdzovanie.
 */
class TicketController extends Controller
{
    public function __construct(
        protected TicketOwnership $ownership
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->integer('per_page') ?: 20, 50));

        $list = $request->input('list') === 'past' ? 'past' : 'upcoming';

        $query = $list === 'past'
            ? $this->ownership->past($user)
            : $this->ownership->upcoming($user);

        $tickets = $query
            // Rovnaké eager loady ako verejný výpis podujatí: EventResource
            // serializuje aj appendy (`thumb_image`, `canal`, `venue`, `files`),
            // takže bez nich by `preventLazyLoading()` spadol — a na produkcii
            // by namiesto toho ticho pribudlo pár dotazov na každý riadok.
            ->with([
                'event.canal:id,name,website',
                'event.canal.files',
                'event.venue',
                'event.files',
                'event.tags',
                'admissions.ticketType',
            ])
            // Nadchádzajúce od najbližšieho termínu (čo je na rade, je hore),
            // história od najnovšieho. Objednávky bez termínu ide MySQL zoradiť
            // až za ne — `start_at IS NULL` ako druhý kľúč by ich vysypal medzi
            // ostatné podľa id, čo v zozname vyzerá náhodne.
            ->join('events', 'events.id', '=', 'tickets.event_id')
            ->orderByRaw('events.start_at IS NULL')
            ->orderBy('events.start_at', $list === 'past' ? 'desc' : 'asc')
            ->select('tickets.*')
            ->paginate($perPage)
            ->withQueryString();

        return TicketResource::collection($tickets);
    }
}
