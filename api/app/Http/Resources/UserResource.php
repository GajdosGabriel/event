<?php

namespace App\Http\Resources;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Models\Canal;
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
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'status' => $c->status,
                'role' => $c->pivot->role,
            ]);

        $activeCanalRole = $activeCanal !== null
            ? $this->resource->canalRole((int) $activeCanal->id)
            : null;

        return [
            'id' => $this->id,
            // Nikdy nie surový e-mail — používateľ bez kanála by ho inak ukázal
            // každému, kto ho vidí vo výpise (členovia spoločného kanála).
            'display_name' => $activeCanal?->name ?? $this->resource->displayName(),
            // Na rozlíšenie účtov; celú adresu vidí len on sám a admin (nižšie).
            'email_masked' => $this->resource->maskedEmail(),
            'roles' => $globalRoles,
            'canals' => $canals,
            'canal_context' => [
                'active' => $activeCanal ? [
                    'id' => $activeCanal->id,
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
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],

            // Admin-only management fields. Foreign emails / audit data stay
            // out of the public + dashboard scopes.
            $this->mergeWhen($request->routeIs('admin.*'), fn () => [
                'uuid' => $this->uuid,
                'email' => $this->email,
                'status' => $this->status,
                'status_label' => $this->status?->label(),
                // Číselník pre admin formulár — rovnako ako pri kanáli
                // a podujatí posiela popisky server, front ich neprekladá.
                'allowed_statuses' => ModelStatus::allowedForUser($user),
                'registered_via' => $this->registered_via,
                'email_verified' => $this->email_verified_at !== null,
                'email_verified_at' => $this->email_verified_at,
                // Osobný kanál — v ňom je používateľ vlastníkom aj bez pivotu
                // (viď User::canalRole), preto ho admin formulár ukazuje zvlášť.
                'canal_id' => $this->canal_id,
                // Doklad o súhlase s podmienkami — kedy a s akou verziou.
                'terms_accepted_at' => $this->terms_accepted_at,
                'terms_version' => $this->terms_version,
                'is_blocked' => $this->resource->isBlocked(),
                'blocked_at' => $this->blocked_at,
                'blocked_until' => $this->blocked_until,
                'blocked_reason' => $this->blocked_reason,
                'canals_count' => $canals->count(),
                // Kontakt pre správu portálu: účet má len prihlasovací e-mail,
                // telefón a web sú pri kanáloch — osobnom a tých, čo vlastní.
                'contacts' => $this->adminContacts(),
                'last_login_at' => $this->last_login_at,
                'last_activity' => $this->last_activity,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'deleted_at' => $this->deleted_at,
            ]),
        ];
    }

    /**
     * Osobný kanál + prvých pár vlastnených s vyplneným kontaktom. Import
     * vie jednému účtu pripísať stovky kanálov — tie sa len spočítajú.
     *
     * @return array{items: list<array<string, mixed>>, more: int}
     */
    private function adminContacts(int $limit = 5): array
    {
        $personalId = (int) $this->canal_id;
        $hasContact = fn ($q) => $q->where(fn ($q) => $q
            ->whereNotNull('email')->where('email', '!=', '')
            ->orWhere(fn ($q) => $q->whereNotNull('phone')->where('phone', '!=', ''))
            ->orWhere(fn ($q) => $q->whereNotNull('website')->where('website', '!=', '')));

        $owned = Canal::query()
            ->whereIn('id', $this->ownedCanals()->select('canals.id'))
            ->when($personalId, fn ($q) => $q->where('id', '!=', $personalId))
            ->tap($hasContact);

        $total = (clone $owned)->count();
        $canals = $owned->orderBy('id')->limit($limit)
            ->get(['id', 'name', 'email', 'email_verified_at', 'phone', 'website']);

        $personal = $personalId
            ? Canal::query()->whereKey($personalId)->tap($hasContact)
                ->first(['id', 'name', 'email', 'email_verified_at', 'phone', 'website'])
            : null;
        if ($personal) {
            $canals->prepend($personal);
        }

        $items = $canals->map(fn (Canal $c) => [
            'canal_id' => $c->id,
            'canal_name' => $c->name,
            'personal' => $c->id === (int) $this->canal_id,
            'email' => $c->email,
            'email_verified' => $c->email_verified_at !== null,
            'phone' => $c->phone,
            'website' => $c->website,
        ])->values()->all();

        return ['items' => $items, 'more' => max(0, $total - $limit)];
    }
}
