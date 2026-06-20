<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use App\Http\Resources\Traits\HasAllowedStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CanalResource extends JsonResource
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
}
