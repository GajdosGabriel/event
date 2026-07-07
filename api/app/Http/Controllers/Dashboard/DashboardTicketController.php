<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketCheckinRequest;
use App\Http\Requests\TicketingSettingsRequest;
use App\Http\Resources\AdmissionResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\TicketDashboardResource;
use App\Models\Admission;
use App\Models\Event;
use App\Notifications\TicketIssued;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Notification;

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

        return response()->json(new TicketDashboardResource(
            $ticket->load(['event', 'admissions.ticketType', 'admissions.checkedInBy'])
        ));
    }

    public function update($id): JsonResponse
    {
        $ticket = $this->ticketRepository->cancel($id);

        return response()->json(new TicketDashboardResource(
            $ticket->load(['event', 'admissions.ticketType', 'admissions.checkedInBy'])
        ));
    }

    /** Zrušenie jednej vstupenky (miesta) v objednávke. */
    public function cancelAdmission($admissionId): JsonResponse
    {
        $admission = $this->ticketRepository->cancelAdmission((int) $admissionId);

        return response()->json(new AdmissionResource($admission->load('ticketType')));
    }

    public function checkin(TicketCheckinRequest $request): JsonResponse
    {
        $result = $this->ticketRepository->checkIn($request->validated()['qr_token'], $request->user());

        return $this->checkinResponse($result);
    }

    /** Manuálny check-in (bez skenovania) z pult obsluhy. */
    public function checkinManual(Request $request): JsonResponse
    {
        $data = $request->validate(['admission_id' => ['required', 'integer']]);
        $result = $this->ticketRepository->manualCheckIn($data['admission_id'], $request->user());

        return $this->checkinResponse($result);
    }

    /** Zrušenie omylom vykonaného check-inu. */
    public function checkinUndo(Request $request): JsonResponse
    {
        $data = $request->validate(['admission_id' => ['required', 'integer']]);
        $result = $this->ticketRepository->undoCheckIn($data['admission_id'], $request->user());

        return $this->checkinResponse($result);
    }

    public function checkinStats($eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);

        return response()->json($this->ticketRepository->checkinStats($event));
    }

    /** Nastavenia predaja lístkov pre podujatie. */
    public function settings(TicketingSettingsRequest $request, $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $event->update($request->validated());

        return response()->json(new EventResource($event->fresh()));
    }

    /** Opätovné poslanie potvrdenia s QR kódmi na e-mail objednávateľa. */
    public function resend($id): JsonResponse
    {
        $ticket = $this->ticketRepository->dashboardShow($id);
        $ticket->load(['event', 'admissions.ticketType']);

        Notification::route('mail', $ticket->holder_email)->notify(new TicketIssued($ticket));

        return response()->json(['status' => 'sent']);
    }

    private function checkinResponse(array $result): JsonResponse
    {
        /** @var Admission|null $admission */
        $admission = $result['admission'] ?? null;

        return response()->json([
            'status' => $result['status'],
            'reason' => $result['reason'] ?? null,
            'admission' => $admission
                ? new AdmissionResource($admission->loadMissing(['event', 'ticket', 'ticketType', 'checkedInBy']))
                : null,
        ]);
    }
}
