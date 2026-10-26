<?php

namespace App\Http\Resources;

use App\Services\Tickets\Questionnaire;
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
            'quantity' => $this->quantity,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status->label(),
            'price_amount' => $this->price_amount,
            'price_currency' => $this->price_currency,
            'awaits_payment' => $this->awaitsPayment(),
            'checked_in_count' => $this->checked_in_count,
            'admissions_total' => $this->admissions_total,
            'admissions' => AdmissionResource::collection($this->whenLoaded('admissions')),
            'answers' => $this->whenLoaded('answers', fn () => app(Questionnaire::class)->present($this->answers->whereNull('admission_id'))),
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
            'permissions' => [
                'update' => $user?->can('update', $this->resource) ?? false,
                'checkin' => $user?->can('ticket.checkin') ?? false,
            ],
        ];
    }
}
