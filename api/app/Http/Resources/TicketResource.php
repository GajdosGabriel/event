<?php

namespace App\Http\Resources;

use App\Services\Tickets\Questionnaire;
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
            'awaits_payment' => $this->awaitsPayment(),
            'checked_in_count' => $this->checked_in_count,
            'admissions_total' => $this->admissions_total,
            'admissions' => AdmissionResource::collection($this->whenLoaded('admissions')),
            // Dotazník: odpovede za objednávku (odpovede za miesto sú pri admissions).
            'answers' => $this->whenLoaded('answers', fn () => app(Questionnaire::class)->present($this->answers->whereNull('admission_id'))),
            'answers_editable' => $this->whenLoaded('event', fn () => app(Questionnaire::class)->attendeeCanEdit($this->resource)),
            'answers_editable_until' => $this->whenLoaded('event', fn () => app(Questionnaire::class)->editableUntil($this->event)),
            'created_at' => $this->created_at,
            'event' => new EventResource($this->whenLoaded('event')),
        ];
    }
}
