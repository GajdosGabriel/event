<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CanalRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRoleSyncRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardRoleController extends Controller
{
    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->where('name', '!=', CanalRole::Owner->globalRole())
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json($roles);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json($permissions);
    }

    public function syncUserRoles(string $id, UserRoleSyncRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $targetUser = User::query()->findOrFail($id);
        $requestedRoles = collect($request->validated('roles'))
            ->reject(fn (string $role): bool => $role === CanalRole::Owner->globalRole())
            ->values()
            ->all();

        abort_if(empty($requestedRoles), 422, 'At least one assignable role is required.');

        $this->authorizeRoleSync($authUser, $targetUser, $requestedRoles);

        // Role odvodené z členstva v kanáli spravuje CanalMembership — ručný
        // sync ich nesmie zhodiť, inak by člen prišiel o prístup do dashboardu.
        $derived = $targetUser->getRoleNames()
            ->filter(fn (string $name) => in_array($name, CanalRole::globalRoles(), true));

        $targetUser->syncRoles($derived->merge($requestedRoles)->unique()->values()->all());

        return response()->json([
            'user_id' => $targetUser->id,
            'roles' => $targetUser->fresh()->getRoleNames()->values(),
            'permissions' => $targetUser->fresh()->getPermissionNames()->values(),
        ]);
    }

    private function authorizeRoleSync(User $authUser, User $targetUser, array $requestedRoles): void
    {
        if ($authUser->hasRole('super-admin')) {
            return;
        }

        abort_if((int) $authUser->id === (int) $targetUser->id, 403, 'You cannot change your own roles.');
        abort_if(in_array('super-admin', $requestedRoles, true), 403, 'Only super-admin can assign the super-admin role.');

        $ownedCanalIds = $authUser->ownedCanals()
            ->pluck('canals.id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $targetCanalIds = $targetUser->dashboardCanalIds();

        abort_if(
            $ownedCanalIds->isEmpty() || $ownedCanalIds->intersect($targetCanalIds)->isEmpty(),
            403,
            'You are not allowed to manage roles for this user.'
        );
    }
}
