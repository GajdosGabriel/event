<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CanalRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRoleSyncRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function roles(): JsonResponse
    {
        $this->ensureSuperAdmin();

        $roles = Role::query()
            ->where('name', '!=', CanalRole::Owner->globalRole())
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json($roles);
    }

    public function permissions(): JsonResponse
    {
        $this->ensureSuperAdmin();

        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json($permissions);
    }

    public function syncUserRoles(string $id, UserRoleSyncRequest $request): JsonResponse
    {
        $this->ensureSuperAdmin();

        $targetUser = User::query()->findOrFail($id);
        $requestedRoles = collect($request->validated('roles'))
            ->reject(fn (string $role): bool => $role === CanalRole::Owner->globalRole())
            ->values()
            ->all();

        abort_if(empty($requestedRoles), 422, 'At least one assignable role is required.');

        // Role odvodené z členstva v kanáli sa ručne nepriraďujú ani neberú —
        // spravuje ich CanalMembership. Bez tohto by ich syncRoles zmazal a
        // vlastník kanála by prišiel o prístup do dashboardu.
        $derived = $targetUser->getRoleNames()
            ->filter(fn (string $name) => in_array($name, CanalRole::globalRoles(), true));

        $targetUser->syncRoles($derived->merge($requestedRoles)->unique()->values()->all());

        return response()->json([
            'user_id' => $targetUser->id,
            'roles' => $targetUser->fresh()->getRoleNames()->values(),
            'permissions' => $targetUser->fresh()->getPermissionNames()->values(),
        ]);
    }

    private function ensureSuperAdmin(): void
    {
        abort_unless(auth('sanctum')->check() && auth('sanctum')->user()->hasRole('super-admin'), 403);
    }
}
