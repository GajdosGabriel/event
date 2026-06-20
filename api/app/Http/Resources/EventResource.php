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
        $user = $request->user();
        $data = parent::toArray($request);

        $data['registration_deadline_at'] = $this->registration_deadline_at;
        $data['date_range_label'] = $this->dateRangeLabel();
        $data['date_range_days'] = $this->dateRangeDays();

        $data['allowed_statuses'] = $this->allowedStatuses($request);

        $isPublished = $this->status === ModelStatus::Published;

        $data['permissions'] = [
            'view' => $user?->can('view', $this->resource) ?? false,
            'update' => $user?->can('update', $this->resource) ?? false,
            'publish' => $user?->can('publish', $this->resource) ?? false,
            'delete' => !$isPublished && ($user?->can('delete', $this->resource) ?? false),
            'archive' => $isPublished && ($user?->can('archive', $this->resource) ?? false),
            'restore' => $user?->can('restore', $this->resource) ?? false,
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
