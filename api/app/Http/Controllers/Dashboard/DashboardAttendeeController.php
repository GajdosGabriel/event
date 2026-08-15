<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Notifications\EventAnnouncement;
use App\Services\Events\AttendeeCsv;
use App\Services\Events\AttendeeDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Práca so zoznamom prihlásených: export a hromadný e-mail (roadmap 3.5).
 *
 * Obe operácie sú nad podujatím, nie nad objednávkami, preto majú vlastný
 * controller — DashboardTicketController rieši jednotlivé objednávky a check-in.
 */
class DashboardAttendeeController extends Controller
{
    public function __construct(
        private readonly AttendeeDirectory $directory,
        private readonly AttendeeCsv $csv,
    ) {
    }

    /** CSV so všetkými vstupenkami podujatia (aj zrušenými — organizátor ich chce vidieť). */
    public function export(string $eventId): StreamedResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        return $this->csv->response($event);
    }

    /**
     * Hromadný e-mail účastníkom. Notifikácia je `ShouldQueue`, takže pri
     * `QUEUE_CONNECTION=database` odchádza na pozadí — request nečaká na
     * doručenie stoviek e-mailov.
     */
    public function email(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('update', $event);

        $data = $request->validate([
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $recipients = $this->directory->recipients($event);

        if ($recipients->isEmpty()) {
            abort(422, __('tickets.errors.no_attendees'));
        }

        $sender = $request->user();

        foreach ($recipients as $recipient) {
            Notification::route('mail', $recipient['email'])->notify(new EventAnnouncement(
                $event,
                $data['subject'],
                $data['body'],
                $sender?->email,
                $sender?->displayName(),
            ));
        }

        return response()->json(['status' => 'queued', 'recipients' => $recipients->count()]);
    }

    /** Koľkým ľuďom by hromadný e-mail odišiel — pre potvrdenie pred odoslaním. */
    public function recipientCount(string $eventId): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $this->authorize('view', $event);

        return response()->json(['recipients' => $this->directory->recipients($event)->count()]);
    }
}
