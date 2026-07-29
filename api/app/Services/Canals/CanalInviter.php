<?php

namespace App\Services\Canals;

use App\Enums\CanalRole;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\User;
use App\Notifications\CanalInvitationSent;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Pozvánky do tímu kanála.
 *
 * Odkaz z e-mailu je autorizáciou v tom zmysle, že bez neho pozvánku nikto
 * nenájde — prijať ju však musí prihlásený účet s tou istou adresou. Preposlaný
 * odkaz tak cudziemu účtu prístup do kanála nedá.
 *
 * Účet pozvanému zámerne nezakladáme dopredu (na rozdiel od GuestAccountProvisioner
 * pri vstupenkách): kým pozvánku neprijme, nemá v systéme čo robiť.
 */
class CanalInviter
{
    /** Koľko dní je pozvánka platná. */
    public const TTL_DAYS = 14;

    public function __construct(
        private CanalMembership $membership,
    ) {
    }

    public function invite(Canal $canal, string $email, CanalRole $role, User $inviter): CanalInvitation
    {
        $email = mb_strtolower(trim($email));

        $existingMember = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existingMember && $canal->users()->where('users.id', $existingMember->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('canal_team.already_member'),
            ]);
        }

        // Staršia nevybavená pozvánka na tú istú adresu sa zruší — platí vždy
        // najnovšia, inak by sa dala rola meniť tým, ktorý odkaz obeť použije.
        $canal->invitations()
            ->pending()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->update(['revoked_at' => now()]);

        $invitation = $canal->invitations()->create([
            'email' => $email,
            'role' => $role->value,
            'invited_by_user_id' => $inviter->id,
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);

        $this->notify($invitation);

        return $invitation;
    }

    /** Znovu pošle e-mail k nevybavenej pozvánke a predĺži jej platnosť. */
    public function resend(CanalInvitation $invitation): CanalInvitation
    {
        $this->assertPending($invitation);

        $invitation->forceFill(['expires_at' => now()->addDays(self::TTL_DAYS)])->save();

        $this->notify($invitation);

        return $invitation;
    }

    public function revoke(CanalInvitation $invitation): void
    {
        $this->assertPending($invitation);

        $invitation->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * Prijatie pozvánky prihláseným účtom. Adresa účtu sa musí zhodovať s tou,
     * na ktorú pozvánka prišla — inak by preposlaný odkaz pustil do kanála
     * kohokoľvek.
     */
    public function accept(CanalInvitation $invitation, User $user): Canal
    {
        $this->assertPending($invitation);

        if (mb_strtolower((string) $user->email) !== mb_strtolower((string) $invitation->email)) {
            throw ValidationException::withMessages([
                'email' => __('canal_team.email_mismatch', ['email' => $invitation->email]),
            ]);
        }

        $canal = $invitation->canal()->firstOrFail();

        $this->membership->attach($canal, $user, $invitation->role);

        $invitation->forceFill([
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->id,
        ])->save();

        return $canal;
    }

    private function notify(CanalInvitation $invitation): void
    {
        Notification::route('mail', $invitation->email)
            ->notify(new CanalInvitationSent(
                $invitation->loadMissing(['canal', 'invitedBy'])
            ));
    }

    private function assertPending(CanalInvitation $invitation): void
    {
        if ($invitation->isPending()) {
            return;
        }

        throw ValidationException::withMessages([
            'token' => $invitation->accepted_at !== null
                ? __('canal_team.already_accepted')
                : __('canal_team.invitation_invalid'),
        ]);
    }
}
