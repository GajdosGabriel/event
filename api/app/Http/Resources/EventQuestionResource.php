<?php

namespace App\Http\Resources;

use App\Models\EventQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EventQuestion */
class EventQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'scope' => $this->scope,
            'type' => $this->type,
            'label' => $this->label,
            'help' => $this->help,
            'is_required' => $this->is_required,
            'ticket_type_ids' => $this->ticket_type_ids,
            'editable_by_attendee' => $this->editable_by_attendee,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn (EventQuestionOption $option) => [
                'id' => $option->id,
                'label' => $option->label,
                'price_amount' => $option->price_amount,
                'capacity' => $option->capacity,
                'taken_count' => $request->routeIs('dashboard.*') ? $option->takenCount() : null,
                'remaining_capacity' => $option->remainingCapacity(),
                'is_active' => $option->is_active,
            ])->values()),
        ];
    }
}
