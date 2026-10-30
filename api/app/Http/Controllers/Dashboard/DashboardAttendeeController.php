<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketDashboardResource;
use App\Models\Event;
use App\Models\EventInterest;
use App\Models\EventMessage;
use App\Models\Ticket;
use App\Notifications\TicketIssued;
use App\Repositories\Contracts\TicketRepository;
use App\Services\Events\AttendeeCsv;
use App\Services\Events\AttendeeDirectory;
use App\Services\Events\AttendeeMessenger;
use App\Services\Tickets\AttendeeRegistrar;
use App\Services\Tickets\PaidTicketDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Práca so zoznamom prihlásených: export, správy, hromadné akcie a ručné
 * pridanie účastníka (roadmap 3.5).
 *
 * Operácie sú nad podujatím, nie nad jednou objednávkou, preto majú vlastný
 * controller — DashboardTicketController rieši jednotlivé objednávky a check-in.
 */
class DashboardAttendeeController extends Controller
{
    public function __construct(
        private readonly AttendeeDirectory $directory,
        private readonly AttendeeCsv $csv,
        private readonly AttendeeMessenger $messenger,
        private readonly TicketRepository $tickets,
    ) {}

    /** CSV so všetkými vstupenkami podujatia (aj zrušenými — organizátor ich chce vidieť). */
    public function export(string $eventId): StreamedResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        return $this->csv->response($event);
    }

    /**
     * E-mail účastníkom — všetkým, časti alebo vybraným riadkom. Notifikácia je
     * `ShouldQueue`, takže pri `QUEUE_CONNECTION=database` odchádza na pozadí —
     * request nečaká na doručenie stoviek e-mailov.
     */
    public function email(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $scope = $this->validatedScope($request);
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $recipients = $this->directory->recipients($event, $scope);

        if ($recipients->isEmpty()) {
            abort(422, __('tickets.errors.no_attendees'));
        }

        $this->messenger->announce($event, $request->user(), $data['subject'], $data['body'], $recipients, $this->audienceOf($scope));

        return response()->json(['status' => 'queued', 'recipients' => $recipients->count()]);
    }

    /** Čo už organizátor účastníkom poslal, najnovšie prvé. */
    public function messages(string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        $messages = EventMessage::query()
            ->where('event_id', $event->id)
            ->with('user')
            ->latest()
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $messages->map(fn (EventMessage $message) => [
                'id' => $message->id,
                'kind' => $message->kind,
                'audience' => $message->audience,
                'subject' => $message->subject,
                'body' => $message->body,
                'recipients' => $message->recipients,
                'sender' => $message->user?->displayName(),
                'created_at' => $message->created_at,
            ])->values(),
        ]);
    }

    /** Hromadná akcia nad vybranými objednávkami; čo sa na objednávku nehodí, preskočí. */
    public function bulk(Request $request, PaidTicketDelivery $delivery, string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);

        $data = $request->validate([
            'action' => ['required', Rule::in(['cancel', 'confirm', 'mark_paid'])],
            'ticket_ids' => ['required', 'array', 'min:1', 'max:200'],
            'ticket_ids.*' => ['integer'],
            'notify' => ['sometimes', 'boolean'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $orders = Ticket::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $data['ticket_ids'])
            ->get();

        $done = 0;

        foreach ($orders as $order) {
            try {
                match ($data['action']) {
                    'cancel' => $this->cancelOne($request, $order, $data),
                    'confirm' => $this->tickets->confirm($order->id),
                    'mark_paid' => $delivery->deliver($this->tickets->markPaid($order->id)),
                };
                $done++;
            } catch (HttpExceptionInterface) {
                // Napr. už uhradená objednávka vo výbere — ostatné tým nezastavíme.
            }
        }

        return response()->json(['done' => $done, 'skipped' => count($data['ticket_ids']) - $done]);
    }

    private function cancelOne(Request $request, Ticket $order, array $data): void
    {
        if ($order->status === TicketStatus::Cancelled) {
            abort(422);
        }

        $this->tickets->cancel($order->id);

        if ($data['notify'] ?? false) {
            $this->messenger->cancellation($order, $request->user(), $data['message'] ?? null);
        }
    }

    /**
     * Účastník pridaný organizátorom (telefonát, hosť, účinkujúci). Ide tou istou
     * cestou ako objednávka z formulára, takže platia kapacita aj predajné okná.
     */
    public function store(Request $request, AttendeeRegistrar $registrar, string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);

        if (! $request->user()->canInCanal((int) $event->canal_id, 'ticket.update')) {
            abort(403);
        }

        $data = $request->validate([
            'holder_name' => ['required', 'string', 'max:250'],
            'holder_email' => ['required', 'email', 'max:190'],
            'holder_phone' => ['nullable', 'string', 'max:30'],
            'ticket_type_id' => ['required', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'send_email' => ['sometimes', 'boolean'],
        ]);

        $ticket = $this->tickets->issueForEvent($event, [
            'holder_name' => $data['holder_name'],
            'holder_email' => $data['holder_email'],
            'holder_phone' => $data['holder_phone'] ?? null,
            'items' => [['ticket_type_id' => $data['ticket_type_id'], 'quantity' => $data['quantity'] ?? 1]],
        ]);

        if ($data['send_email'] ?? true) {
            $registrar->registerAndNotify($ticket);
            Notification::route('mail', $ticket->holder_email)
                ->notify(new TicketIssued($ticket->fresh(['event', 'admissions.ticketType'])));
        }

        return response()->json(new TicketDashboardResource(
            $ticket->load(['event', 'admissions.ticketType', 'admissions.checkedInBy'])
        ), 201);
    }

    /** Záujemcovia, ktorým sa nedala vystaviť vstupenka. */
    public function interests(string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        return response()->json([
            'data' => $this->directory->interests($event)->map(fn (EventInterest $interest) => [
                'id' => $interest->id,
                'name' => $interest->name,
                'email' => $interest->email,
                'created_at' => $interest->created_at,
            ])->values(),
        ]);
    }

    /** Vybavený záujemca zmizne zo zoznamu (bez e-mailu). */
    public function destroyInterest(string $eventId, string $interestId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        EventInterest::query()->where('event_id', $event->id)->findOrFail($interestId)->delete();

        return response()->json(['status' => 'deleted']);
    }

    /** Koľkým ľuďom by e-mail odišiel — pre potvrdenie pred odoslaním. */
    public function recipientCount(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        return response()->json(['recipients' => $this->directory->recipients($event, $this->validatedScope($request))->count()]);
    }

    /** @return array{ticket_ids?: array<int, int>, ticket_type_id?: int, audience?: string} */
    private function validatedScope(Request $request): array
    {
        return $request->validate([
            'ticket_ids' => ['sometimes', 'array', 'max:200'],
            'ticket_ids.*' => ['integer'],
            'ticket_type_id' => ['sometimes', 'nullable', 'integer'],
            'audience' => ['sometimes', 'nullable', Rule::in(['all', 'unpaid'])],
        ]);
    }

    private function audienceOf(array $scope): string
    {
        return match (true) {
            ! empty($scope['ticket_ids']) => 'selected',
            ! empty($scope['ticket_type_id']) => 'type',
            default => $scope['audience'] ?? 'all',
        };
    }
}
