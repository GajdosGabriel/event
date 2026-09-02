<?php

namespace App\Http\Resources;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
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
        // Role odvodené z členstva v kanáli sa navonok neukazujú — o právach
        // hovorí rola v konkrétnom kanáli (canal_context.role), nie globálna.
        $globalRoles = $allRoles
            ->reject(fn (string $role) => in_array($role, CanalRole::globalRoles(), true))
            ->values();

        $canals = $this->canals()
            ->select('canals.id', 'canals.name', 'canals.slug', 'canals.status')
            ->get()
            ->map(fn ($c) => [
                'id'     => $c->id,
                'name'   => $c->name,
                'slug'   => $c->slug,
                'status' => $c->status,
                'role'   => $c->pivot->role,
            ]);

        $activeCanalRole = $activeCanal !== null
            ? $this->resource->canalRole((int) $activeCanal->id)
            : null;

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
                // Rola v práve aktívnom kanáli — front podľa nej skrýva akcie,
                // ktoré by mu backend aj tak zamietol (policy je zdroj pravdy).
                'role' => $activeCanalRole?->value,
                'role_label' => $activeCanalRole?->label(),
            ],
            // Vlastný e-mail smie návštevník vidieť kdekoľvek (predvyplní si ním
            // objednávku); cudzie e-maily zostávajú len v admin scope nižšie.
            $this->mergeWhen($user?->id === $this->resource->id, fn () => [
                'email' => $this->email,
            ]),

            'permissions' => [
                'view'    => $user?->can('view', $this->resource) ?? false,
                'update'  => $user?->can('update', $this->resource) ?? false,
                'delete'  => $user?->can('delete', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],

            // Admin-only management fields. Foreign emails / audit data stay
            // out of the public + dashboard scopes.
            $this->mergeWhen($request->routeIs('admin.*'), fn () => [
                'uuid'              => $this->uuid,
                'email'             => $this->email,
                'status'            => $this->status,
                'status_label'      => $this->status?->label(),
                // Číselník pre admin formulár — rovnako ako pri kanáli
                // a podujatí posiela popisky server, front ich neprekladá.
                'allowed_statuses'  => ModelStatus::allowedForUser($user),
                'registered_via'    => $this->registered_via,
                'email_verified'    => $this->email_verified_at !== null,
                'email_verified_at' => $this->email_verified_at,
                // Osobný kanál — v ňom je používateľ vlastníkom aj bez pivotu
                // (viď User::canalRole), preto ho admin formulár ukazuje zvlášť.
                'canal_id'          => $this->canal_id,
                // Doklad o súhlase s podmienkami — kedy a s akou verziou.
                'terms_accepted_at' => $this->terms_accepted_at,
                'terms_version'     => $this->terms_version,
                'is_blocked'        => $this->resource->isBlocked(),
                'blocked_at'        => $this->blocked_at,
                'blocked_until'     => $this->blocked_until,
                'blocked_reason'    => $this->blocked_reason,
                'canals_count'      => $canals->count(),
                'last_login_at'     => $this->last_login_at,
                'last_activity'     => $this->last_activity,
                'created_at'        => $this->created_at,
                'updated_at'        => $this->updated_at,
                'deleted_at'        => $this->deleted_at,
            ]),
        ];
    }
}
