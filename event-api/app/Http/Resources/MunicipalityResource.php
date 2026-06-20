<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MunicipalityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $data = parent::toArray($request);

        $data['permissions'] = [
            'view' => $user?->can('view', $this->resource) ?? false,
            'update' => $user?->can('update', $this->resource) ?? false,
            'delete' => $user?->can('delete', $this->resource) ?? false,
            'restore' => $user?->can('restore', $this->resource) ?? false,
        ];

        return $data;
    }
}
