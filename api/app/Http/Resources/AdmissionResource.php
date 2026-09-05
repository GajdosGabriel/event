<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionResource extends JsonResource
{
    /**
     * Mená obsluhy v rámci jednej požiadavky.
     *
     * `User::displayName()` sa pýta na osobný kanál a PendingProfile, takže by
     * pri zozname prihlásených strieľal dva dotazy na každú vstupenku. Obsluhy
     * je pritom pár — vo veľkom podujatí traja ľudia na dverách — takže cache
     * má rádovo jednotky položiek a žije len do konca requestu.
     *
     * @var array<int, string>
     */
    private static array $staffNames = [];

    private static function staffName(User $staff): string
    {
        return self::$staffNames[$staff->id] ??= $staff->displayName();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'ticket_id' => $this->ticket_id,
            'event_id' => $this->event_id,
            'attendee_name' => $this->attendee_name,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'confirmation_status' => $this->confirmation_status?->value,
            'confirmation_status_label' => $this->confirmation_status?->label(),
            'confirmation_deadline_at' => $this->confirmation_deadline_at,
            'is_checked_in' => $this->is_checked_in,
            'checked_in_at' => $this->checked_in_at,
            // Meno obsluhy, nie len id: pri check-ine na viacerých zariadeniach
            // je „už prišiel" bez toho, kto ho pustil, informácia, s ktorou sa
            // pri dverách nedá nič spraviť.
            'checked_in_by' => $this->whenLoaded('checkedInBy', fn () => $this->checkedInBy ? [
                'id' => $this->checkedInBy->id,
                'name' => self::staffName($this->checkedInBy),
            ] : null),
            'qr_url' => route('public.admissions.qr', $this->uuid),
            'ticket_type' => $this->whenLoaded('ticketType', fn () => $this->ticketType ? [
                'id' => $this->ticketType->id,
                'name' => $this->ticketType->name,
                'kind' => $this->ticketType->kind,
                'starts_at' => $this->ticketType->starts_at,
            ] : null),
            // Údaje objednávateľa – užitočné pri kontrole na vchode.
            'holder_name' => $this->whenLoaded('ticket', fn () => $this->ticket?->holder_name),
            'event' => $this->whenLoaded('event', fn () => $this->event ? [
                'id' => $this->event->id,
                'name' => $this->event->name,
            ] : null),
        ];
    }
}
