<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CanalInvitation;
use App\Services\Canals\CanalInviter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Prijatie pozvánky do tímu kanála z e-mailu.
 *
 * `show` je verejné (autorizáciou je token v odkaze), aby pozvaný videl, kam ho
 * volajú, ešte pred prihlásením. `accept` už prihlásenie vyžaduje a adresa účtu
 * sa musí zhodovať s adresou pozvánky — viď CanalInviter::accept().
 */
class CanalInvitationController extends Controller
{
    public function __construct(
        private CanalInviter $inviter,
    ) {
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $invitation = $this->resolve($token);
        $user = $request->user();

        return response()->json([
            'data' => [
                'canal' => [
                    'id' => $invitation->canal?->id,
                    'name' => $invitation->canal?->name,
                ],
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                // Adresa pozvánky — týka sa držiteľa odkazu, dostal ju do vlastnej
                // schránky. Front podľa nej vie vysvetliť nesúlad s prihláseným účtom.
                'email' => $invitation->email,
                'invited_by' => $invitation->invitedBy?->displayName(),
                'expires_at' => $invitation->expires_at,
                'status' => $this->status($invitation),
                'email_matches' => $user !== null
                    && mb_strtolower((string) $user->email) === mb_strtolower((string) $invitation->email),
            ],
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->resolve($token);

        $canal = $this->inviter->accept($invitation, $request->user());

        return response()->json([
            'data' => [
                'canal' => [
                    'id' => $canal->id,
                    'name' => $canal->name,
                ],
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'status' => 'accepted',
            ],
        ]);
    }

    private function resolve(string $token): CanalInvitation
    {
        return CanalInvitation::query()
            ->with(['canal', 'invitedBy'])
            ->where('token', $token)
            ->firstOrFail();
    }

    private function status(CanalInvitation $invitation): string
    {
        return match (true) {
            $invitation->accepted_at !== null => 'accepted',
            $invitation->revoked_at !== null => 'revoked',
            $invitation->isExpired() => 'expired',
            default => 'pending',
        };
    }
}
