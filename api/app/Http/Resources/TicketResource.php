<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'holder_name' => $this->holder_name,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status->label(),
            'price_amount' => $this->price_amount,
            'price_currency' => $this->price_currency,
            'is_checked_in' => $this->is_checked_in,
            'checked_in_at' => $this->checked_in_at,
            'created_at' => $this->created_at,
            'event' => new EventResource($this->whenLoaded('event')),
        ];
    }
}
