<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionResource extends JsonResource
{
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
            'checked_in_by' => $this->whenLoaded('checkedInBy', fn () => $this->checkedInBy ? ['id' => $this->checkedInBy->id] : null),
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
