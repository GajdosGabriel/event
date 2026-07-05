<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use App\Http\Resources\Traits\HasAllowedStatuses;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    use HasAllowedStatuses;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The nested canal/venue are only output as a slim shape below, but the model's
        // appended accessors would otherwise serialize their full image/pivot appends
        // (extra file/pivot queries per row). Drop those appends on the loaded relations
        // before parent serialization; real columns (id, name, address…) stay available.
        foreach (['canal', 'venue'] as $relation) {
            if ($this->resource->relationLoaded($relation)) {
                $this->resource->getRelation($relation)?->setAppends([]);
            }
        }

        $user = $request->user();
        $data = parent::toArray($request);

        $data['registration_deadline_at'] = $this->registration_deadline_at;
        $data['date_range_label'] = $this->dateRangeLabel();
        $data['date_range_days'] = $this->dateRangeDays();
        $data['status_label'] = $this->statusLabel();

        $data['tickets_enabled'] = $this->tickets_enabled;
        $data['capacity'] = $this->capacity;
        $data['remaining_capacity'] = $this->remaining_capacity;
        $data['price_amount'] = $this->price_amount;
        $data['price_currency'] = $this->price_currency;

        $data['allowed_statuses'] = $this->allowedStatuses($request);

        // Nested canal/venue are exposed via the Event model's appended accessors,
        // which serialize the whole related model (all columns + their own image/pivot
        // appends). The event views only need a handful of fields, so trim to those:
        // this shrinks the payload and avoids leaking canal/venue email/body publicly.
        $canal = $this->canal;
        $data['canal'] = $canal ? [
            'id' => $canal->id,
            'name' => $canal->name,
        ] : null;

        $venue = $this->venue;
        $data['venue'] = $venue ? [
            'id' => $venue->id,
            'name' => $venue->name,
            'street' => $venue->street,
            'postcode' => $venue->postcode,
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'phone' => $venue->phone,
            'website' => $venue->website,
            'opening_hours' => $venue->opening_hours,
        ] : null;

        $isPublished = $this->status === ModelStatus::Published;

        $data['permissions'] = [
            'view' => $user?->can('view', $this->resource) ?? false,
            'update' => $user?->can('update', $this->resource) ?? false,
            'publish' => $user?->can('publish', $this->resource) ?? false,
            'delete' => !$isPublished && ($user?->can('delete', $this->resource) ?? false),
            'archive' => $isPublished && ($user?->can('archive', $this->resource) ?? false),
            'restore' => $user?->can('restore', $this->resource) ?? false,
            'view_tickets' => $user?->can('view', $this->resource) ?? false,
            'checkin' => ($user?->can('view', $this->resource) ?? false) && ($user?->can('ticket.checkin') ?? false),
        ];

        return $data;
    }

    private function dateRangeLabel(): ?string
    {
        if (! $this->start_at instanceof CarbonInterface) {
            return null;
        }

        if (! $this->end_at instanceof CarbonInterface) {
            return $this->start_at->format('d. m. Y H:i');
        }

        if ($this->start_at->isSameDay($this->end_at)) {
            return sprintf(
                '%s - %s',
                $this->start_at->format('d. m. Y H:i'),
                $this->end_at->format('H:i'),
            );
        }

        return sprintf(
            '%s - %s',
            $this->start_at->format('d. m. Y H:i'),
            $this->end_at->format('d. m. Y H:i'),
        );
    }

    private function dateRangeDays(): ?array
    {
        if (! $this->start_at instanceof CarbonInterface) {
            return null;
        }

        $days = [
            'start' => $this->localizedDayName($this->start_at),
            'end' => null,
        ];

        if (
            $this->end_at instanceof CarbonInterface
            && ! $this->start_at->isSameDay($this->end_at)
        ) {
            $days['end'] = $this->localizedDayName($this->end_at);
        }

        return $days;
    }

    private function localizedDayName(CarbonInterface $date): string
    {
        return [
            0 => 'Nedeľa',
            1 => 'Pondelok',
            2 => 'Utorok',
            3 => 'Streda',
            4 => 'Štvrtok',
            5 => 'Piatok',
            6 => 'Sobota',
        ][$date->dayOfWeek];
    }
}
