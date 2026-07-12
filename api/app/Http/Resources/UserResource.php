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

            // Admin-only management fields. Foreign emails / audit data stay
            // out of the public + dashboard scopes.
            $this->mergeWhen($request->routeIs('admin.*'), fn () => [
                'email'             => $this->email,
                'status'            => $this->status,
                'registered_via'    => $this->registered_via,
                'email_verified'    => $this->email_verified_at !== null,
                'is_blocked'        => $this->resource->isBlocked(),
                'blocked_until'     => $this->blocked_until,
                'canals_count'      => $canals->count(),
                'last_login_at'     => $this->last_login_at,
                'last_activity'     => $this->last_activity,
                'created_at'        => $this->created_at,
                'deleted_at'        => $this->deleted_at,
            ]),
        ];
    }
}
