<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\TicketRepository;
use App\Services\Tickets\QrCodeGenerator;
use Illuminate\Http\Response;

class TicketQrController extends Controller
{
    protected $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function show($uuid, QrCodeGenerator $generator): Response
    {
        // Spätná kompatibilita: staré odkazy /tickets/{uuid}/qr → QR prvej
        // vstupenky objednávky. Nové odkazy používajú /admissions/{uuid}/qr.
        $ticket = $this->ticketRepository->findByUuid($uuid);

        $admission = $ticket?->admissions()->orderBy('id')->first();

        if (! $admission || $ticket->awaitsPayment()) {
            abort(404);
        }

        $result = $generator->forToken($admission->qr_token);

        return response($result->getString(), 200)
            ->header('Content-Type', $result->getMimeType());
    }
}
