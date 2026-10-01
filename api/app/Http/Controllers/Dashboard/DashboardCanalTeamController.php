<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CanalNotificationTopic;
use App\Enums\CanalRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\CanalInviteRequest;
use App\Http\Requests\CanalMemberRoleRequest;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\User;
use App\Services\Canals\CanalInviter;
use App\Services\Canals\CanalMembership;
use App\Services\Canals\CanalRecipients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        private CanalRecipients $recipients,
    ) {}

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

    /**
     * Zapne/vypne členovi tému notifikácií. Svoje si nastaví každý člen,
     * cudzie len ten, kto tím spravuje.
     */
    public function updateNotifications(Request $request, Canal $canal, User $user): JsonResponse
    {
        $this->assertMember($canal, $user);

        if ((int) $request->user()->id === (int) $user->id) {
            $this->authorize('viewTeam', $canal);
        } else {
            $this->authorize('manageTeam', $canal);
        }

        $data = $request->validate([
            'topic' => ['required', 'string', Rule::enum(CanalNotificationTopic::class)],
            'enabled' => ['required', 'boolean'],
        ]);

        $this->recipients->set($canal, $user, CanalNotificationTopic::from($data['topic']), (bool) $data['enabled']);

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
     * Zoznam členov + nevybavených pozvánok. Celý e-mail člena vidí len on
     * sám, ostatní (aj správca) maskovaný — plné adresy sú len v admine.
     * Pozvánky nesú adresu, ktorú správca sám zadal, tie idú celé.
     */
    private function teamPayload(Canal $canal): array
    {
        $request = request();
        $authUser = $request->user();
        $canManage = $authUser?->can('manageTeam', $canal) ?? false;

        $notifications = $this->recipients->matrix($canal);

        $members = $canal->users()->get()->map(function (User $member) use ($authUser, $canManage, $notifications) {
            $role = CanalRole::tryFrom((string) $member->pivot->role) ?? CanalRole::Editor;
            $isSelf = (int) $member->id === (int) $authUser?->id;

            return [
                'id' => $member->id,
                'name' => $member->displayName(),
                'email' => $isSelf ? $member->email : $member->maskedEmail(),
                'role' => $role->value,
                'role_label' => $role->label(),
                'is_owner' => (bool) $member->pivot->is_owner,
                'is_self' => $isSelf,
                'joined_at' => $member->pivot->created_at,
                // Nastavenia vidí a mení člen sám a ten, kto tím spravuje.
                'notifications' => $isSelf || $canManage ? ($notifications[$member->id] ?? null) : null,
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
                'notification_topics' => CanalNotificationTopic::options(),
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
