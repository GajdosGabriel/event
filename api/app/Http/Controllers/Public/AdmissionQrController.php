<?php

namespace App\Http\Controllers\Public;

use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\TicketRepository;
use App\Services\Tickets\QrCodeGenerator;
use Illuminate\Http\Response;

class AdmissionQrController extends Controller
{
    protected $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    public function show($uuid, QrCodeGenerator $generator): Response
    {
        $admission = $this->ticketRepository->findAdmissionByUuid($uuid);

        if (! $admission) {
            abort(404);
        }

        // Náhradník ešte nemá miesto — QR kód mu nevydáme.
        if ($admission->status === AdmissionStatus::Waitlisted) {
            abort(404);
        }

        // Nepotvrdená rezervácia ešte nie je platná vstupenka — QR kód nevydáme.
        if ($admission->isPendingConfirmation()) {
            abort(404);
        }

        $result = $generator->forToken($admission->qr_token);

        return response($result->getString(), 200)
            ->header('Content-Type', $result->getMimeType());
    }
}
