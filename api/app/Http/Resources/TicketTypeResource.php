<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'kind' => $this->kind,
            'description' => $this->description,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'price_amount' => $this->price_amount,
            'price_currency' => $this->price_currency,
            'capacity' => $this->capacity,
            'max_per_order' => $this->max_per_order,
            'min_per_order' => $this->min_per_order,
            'requires_attendee_name' => $this->requires_attendee_name,
            'open_to_public' => (bool) $this->open_to_public,
            'sale_starts_at' => $this->sale_starts_at,
            'sale_ends_at' => $this->sale_ends_at,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'sold_count' => $this->sold_count,
            'remaining_capacity' => $this->remaining_capacity,
            'on_sale' => $this->on_sale,
            // Dopĺňa verejný zoznam typov — stav prihláseného návštevníka na workshope.
            'viewer_joined' => (bool) ($this->viewer_joined ?? false),
            'viewer_waitlisted' => (bool) ($this->viewer_waitlisted ?? false),
            'viewer_waitlist_position' => $this->viewer_waitlist_position ?? null,
            'waitlist_count' => (int) ($this->waitlist_count ?? 0),
            'created_at' => $this->created_at,
        ];
    }
}
