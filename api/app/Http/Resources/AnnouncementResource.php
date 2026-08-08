<?php

namespace App\Http\Resources;

use App\Http\Resources\Traits\HasAllowedStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Číta sa aj z verejného `/api/announcements`, preto tu nie sú žiadne interné
 * polia navyše — verejný zoznam aj tak vracia len publikované oznamy.
 */
class AnnouncementResource extends JsonResource
{
    use HasAllowedStatuses;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'placement' => $this->placement?->value,
            'title' => $this->title,
            'body' => $this->body,
            'variant' => $this->variant?->value,
            'sort_order' => (int) $this->sort_order,
            // `datetime-local` v prehliadači nezoberie sekundy ani pásmo.
            'published_from' => $this->published_from?->format('Y-m-d\TH:i'),
            'published_until' => $this->published_until?->format('Y-m-d\TH:i'),
            'status' => $this->status?->value,
            'status_label' => $this->statusLabel(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
