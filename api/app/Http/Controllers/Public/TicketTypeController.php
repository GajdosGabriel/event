<?php

namespace App\Http\Controllers\Public;

use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketTypeResource;
use App\Models\Admission;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Repositories\Contracts\TicketRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketTypeController extends Controller
{
    protected $ticketRepository;

    public function __construct(TicketRepository $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    /** Verejný zoznam aktívnych typov lístkov pre registračný formulár. */
    public function index($eventId): AnonymousResourceCollection
    {
        $event = Event::query()->findOrFail($eventId);

        $types = $event->ticketTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Má prihlásený návštevník platnú vstupenku? (odomkne workshopy v UI,
        // autoritatívne to stráži issueForEvent)
        $user = auth('sanctum')->user();
        $viewerRegistered = $user !== null && Admission::query()
            ->mainSeats($event->id)
            ->whereHas('ticket', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        // Na ktoré workshopy je prihlásený / kde je náhradníkom — kvôli tlačidlu.
        $joinedTypeIds = $user !== null
            ? $this->ticketRepository->joinedWorkshopTypeIds($event, $user)
            : [];
        $waitlistedTypeIds = $user !== null
            ? $this->ticketRepository->waitlistedWorkshopTypeIds($event, $user)
            : [];

        $types->each(function ($type) use ($user, $joinedTypeIds, $waitlistedTypeIds) {
            $type->setAttribute('viewer_joined', in_array($type->id, $joinedTypeIds, true));

            if (! $type->isWorkshop()) {
                return;
            }

            $waitlisted = in_array($type->id, $waitlistedTypeIds, true);
            $type->setAttribute('viewer_waitlisted', $waitlisted);
            $type->setAttribute('waitlist_count', $this->ticketRepository->waitlistCount($type));
            $type->setAttribute(
                'viewer_waitlist_position',
                $waitlisted ? $this->viewerWaitlistPosition($type, $user) : null,
            );
        });

        return TicketTypeResource::collection($types)->additional([
            'meta' => [
                'viewer_registered' => $viewerRegistered,
                'workshop_changes_locked' => $event->workshopChangesLocked(),
            ],
        ]);
    }

    private function viewerWaitlistPosition(TicketType $type, User $user): ?int
    {
        $admission = Admission::query()
            ->where('ticket_type_id', $type->id)
            ->where('status', AdmissionStatus::Waitlisted->value)
            ->whereHas('ticket', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->first();

        return $admission ? $this->ticketRepository->waitlistPosition($admission) : null;
    }
}
