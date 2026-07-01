<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'event_id' => $this->event_id,
            'holder_name' => $this->holder_name,
            'holder_email' => $this->holder_email,
            'holder_phone' => $this->holder_phone,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status->label(),
            'price_amount' => $this->price_amount,
            'price_currency' => $this->price_currency,
            'is_checked_in' => $this->is_checked_in,
            'checked_in_at' => $this->checked_in_at,
            'checked_in_by' => $this->whenLoaded('checkedInBy', fn () => $this->checkedInBy ? ['id' => $this->checkedInBy->id] : null),
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
            'permissions' => [
                'update' => $user?->can('update', $this->resource) ?? false,
                'checkin' => $user?->can('checkin', $this->resource) ?? false,
            ],
        ];
    }
}
