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

        $canals = $this->canals()
            ->select('canals.id', 'canals.name', 'canals.slug', 'canals.status')
            ->get()
            ->map(fn ($c) => [
                'id'     => $c->id,
                'name'   => $c->name,
                'slug'   => $c->slug,
                'status' => $c->status,
            ]);

        return [
            'id'           => $this->id,
            'display_name' => $activeCanal?->name ?? $this->email,
            'email'        => $this->email,
            'roles'        => $globalRoles,
            'canals'       => $canals,
            'canal_context' => [
                'active' => $activeCanal ? [
                    'id'   => $activeCanal->id,
                    'name' => $activeCanal->name,
                    'slug' => $activeCanal->slug,
                ] : null,
                'is_owner' => $activeCanal !== null
                    ? $this->ownedCanals()->where('canals.id', $activeCanal->id)->exists()
                    : false,
            ],
            'permissions' => [
                'view'    => $user?->can('view', $this->resource) ?? false,
                'update'  => $user?->can('update', $this->resource) ?? false,
                'delete'  => $user?->can('delete', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],
        ];
    }
}
