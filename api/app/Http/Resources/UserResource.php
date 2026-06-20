<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $activeCanal = $this->canal;
        $allRoles = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')
            : $this->getRoleNames();
        $globalRoles = $allRoles
            ->reject(fn (string $role) => $role === 'canal-owner')
            ->values();

        return [
            'id' => $this->id,
            'roles' => $globalRoles,
            'canal_context' => [
                'active' => [
                    'id' => $activeCanal?->id,
                    'name' => $activeCanal?->name,
                ],
                'is_owner' => $activeCanal !== null
                    ? $this->ownedCanals()->where('canals.id', $activeCanal->id)->exists()
                    : false,
            ],
            'permissions' => [
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],
        ];
    }
}
