<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CanalRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\CanalInviteRequest;
use App\Http\Requests\CanalMemberRoleRequest;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\User;
use App\Services\Canals\CanalInviter;
use App\Services\Canals\CanalMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tím kanála: kto v ňom je, s akou rolou, a kto je pozvaný.
 *
 * Routy zámerne nemajú `permission:` middleware — právo je per kanál a rozhoduje
 * o ňom CanalPolicy (viewTeam / manageTeam), nie globálna rola používateľa.
 */
class DashboardCanalTeamController extends Controller
{
    public function __construct(
        private CanalMembership $membership,
        private CanalInviter $inviter,
    ) {
    }

    public function index(Canal $canal): JsonResponse
    {
        $this->authorize('viewTeam', $canal);

        return response()->json($this->teamPayload($canal));
    }

    public function invite(CanalInviteRequest $request, Canal $canal): JsonResponse
    {
        $this->authorize('manageTeam', $canal);

        $this->inviter->invite($canal, $request->validated('email'), $request->role(), $request->user());

        return response()->json($this->teamPayload($canal), 201);
    }

    public function updateRole(CanalMemberRoleRequest $request, Canal $canal, User $user): JsonResponse
    {
        $this->authorize('manageTeam', $canal);
        $this->assertNotSelf($request, $user, 'canal_team.self_role_change');
        $this->assertMember($canal, $user);

        $this->membership->changeRole($canal, $user, $request->role());

        return response()->json($this->teamPayload($canal));
    }

    public function destroy(Request $request, Canal $canal, User $user): JsonResponse
    {
        $this->authorize('manageTeam', $canal);
        $this->assertNotSelf($request, $user, 'canal_team.self_remove');
        $this->assertMember($canal, $user);

        $this->membership->detach($canal, $user);

        return response()->json($this->teamPayload($canal));
    }

    public function resendInvitation(Canal $canal, CanalInvitation $invitation): JsonResponse
    {
        $this->authorize('manageTeam', $canal);
        $this->assertBelongsToCanal($canal, $invitation);

        $this->inviter->resend($invitation);

        return response()->json($this->teamPayload($canal));
    }

    public function destroyInvitation(Canal $canal, CanalInvitation $invitation): JsonResponse
    {
        $this->authorize('manageTeam', $canal);
        $this->assertBelongsToCanal($canal, $invitation);

        $this->inviter->revoke($invitation);

        return response()->json($this->teamPayload($canal));
    }

    /**
     * Zoznam členov + nevybavených pozvánok. E-mail vidí len ten, kto tím
     * spravuje — adresu do pozvánky sám zadal. Ostatným členom sa cudzie
     * adresy nezobrazujú.
     */
    private function teamPayload(Canal $canal): array
    {
        $request = request();
        $authUser = $request->user();
        $canManage = $authUser?->can('manageTeam', $canal) ?? false;

        $members = $canal->users()->get()->map(function (User $member) use ($authUser, $canManage) {
            $role = CanalRole::tryFrom((string) $member->pivot->role) ?? CanalRole::Editor;
            $isSelf = (int) $member->id === (int) $authUser?->id;

            return [
                'id' => $member->id,
                'name' => $member->displayName(),
                'email' => ($canManage || $isSelf) ? $member->email : null,
                'role' => $role->value,
                'role_label' => $role->label(),
                'is_owner' => (bool) $member->pivot->is_owner,
                'is_self' => $isSelf,
                'joined_at' => $member->pivot->created_at,
            ];
        })->values();

        $invitations = $canManage
            ? $canal->invitations()->pending()->with('invitedBy')->latest()->get()->map(fn (CanalInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role->value,
                'role_label' => $i->role->label(),
                'invited_by' => $i->invitedBy?->displayName(),
                'expires_at' => $i->expires_at,
                'created_at' => $i->created_at,
            ])->values()
            : collect();

        return [
            'data' => [
                'members' => $members,
                'invitations' => $invitations,
            ],
            'meta' => [
                'roles' => CanalRole::options(),
                'permissions' => [
                    'manage' => $canManage,
                ],
            ],
        ];
    }

    private function assertMember(Canal $canal, User $user): void
    {
        abort_unless($canal->users()->where('users.id', $user->id)->exists(), 404);
    }

    private function assertBelongsToCanal(Canal $canal, CanalInvitation $invitation): void
    {
        abort_unless((int) $invitation->canal_id === (int) $canal->id, 404);
    }

    /**
     * Vlastník si nesmie meniť ani brať vlastnú rolu — inak by sa dal kanál
     * omylom uzamknúť. Odísť z tímu je samostatná akcia (detach cez iného
     * vlastníka), nie vedľajší efekt správy tímu.
     */
    private function assertNotSelf(Request $request, User $user, string $messageKey): void
    {
        abort_if((int) $request->user()->id === (int) $user->id, 422, __($messageKey));
    }
}
