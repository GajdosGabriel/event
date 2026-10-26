<?php

namespace App\Http\Controllers\Public;

use App\Enums\AdmissionStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TicketAnswersRequest;
use App\Http\Resources\AdmissionResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Repositories\Contracts\TicketRepository;
use App\Services\Tickets\Questionnaire;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Dodatočná úprava odpovedí dotazníka účastníkom — cez tajný odkaz
 * objednávky (objednávateľ, všetky miesta) alebo miesta (jeden účastník).
 * Rovnako ako zobrazenie lístka stačí poznať uuid.
 */
class TicketAnswerController extends Controller
{
    public function __construct(
        private TicketRepository $tickets,
        private Questionnaire $questionnaire,
    ) {}

    public function updateTicket(TicketAnswersRequest $request, string $uuid): JsonResponse
    {
        $ticket = $this->tickets->findByUuid($uuid) ?? abort(404);
        $this->ensureEditable($ticket);

        DB::transaction(fn () => $this->questionnaire->update(
            $ticket,
            $request->has('answers') ? (array) $request->input('answers') : null,
            (array) $request->input('admissions', []),
            asOrganizer: false,
        ));

        $ticket = $ticket->fresh(['event', 'admissions.ticketType', ...Questionnaire::TICKET_RELATIONS]);

        return response()->json(new TicketResource($ticket));
    }

    public function updateAdmission(TicketAnswersRequest $request, string $uuid): JsonResponse
    {
        $admission = $this->tickets->findAdmissionByUuid($uuid) ?? abort(404);

        if ($admission->status === AdmissionStatus::Cancelled) {
            abort(422, __('tickets.errors.answers_locked'));
        }

        $ticket = $admission->ticket()->firstOrFail();
        $this->ensureEditable($ticket);

        DB::transaction(fn () => $this->questionnaire->update(
            $ticket,
            null,
            [$admission->id => (array) $request->input('answers', [])],
            asOrganizer: false,
        ));

        return response()->json(new AdmissionResource(
            $admission->fresh(['ticketType', 'answers.question', 'answers.option'])
        ));
    }

    private function ensureEditable(Ticket $ticket): void
    {
        if ($ticket->status === TicketStatus::Cancelled || ! $this->questionnaire->attendeeCanEdit($ticket)) {
            abort(422, __('tickets.errors.answers_locked'));
        }
    }
}
