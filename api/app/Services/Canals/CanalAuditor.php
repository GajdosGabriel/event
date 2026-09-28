<?php

namespace App\Services\Canals;

use App\Enums\CanalRole;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Models\CanalInvitation;
use App\Models\User;
use App\Notifications\CanalOwnershipChanged;
use App\Services\SystemLog\MailSimulator;
use App\Services\SystemLog\Recorder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Denník zmien tímu a vlastníctva kanála (system_logs, kanál `canals`)
 * a z nich odvodené e-maily CanalOwnershipChanged.
 *
 * Zapisuje sa každá zmena členstva; e-mail ide len pri zmene vlastníka
 * spravovaného kanála. Technický vlastník importu sa neoslovuje a nikto
 * nedostane e-mail o tom, čo sám urobil. Kým platí
 * `canals.simulate_ownership_mail`, e-maily sa len zapíšu ako `mail.simulated`.
 */
class CanalAuditor
{
    public function memberChanged(Canal $canal, User $member, ?CanalRole $from, ?CanalRole $to, bool $notify = true, array $context = []): void
    {
        if ($from === $to) {
            return;
        }

        $actor = $this->actor();
        $event = match (true) {
            $from === null => 'member_added',
            $to === null => 'member_removed',
            default => 'role_changed',
        };

        Recorder::info('canals', $event, $this->describe($canal, $event, $from, $to),
            status: 'ok',
            recipient: $member->email,
            userId: $member->id,
            subject: $canal,
            context: [
                'member_id' => $member->id,
                'from' => $from?->value,
                'to' => $to?->value,
                'actor_id' => $actor?->id,
            ] + $context,
        );

        $wasOwner = $from?->isOwner() ?? false;
        $isOwner = $to?->isOwner() ?? false;

        if (! $notify || $wasOwner === $isOwner || ! $canal->isManaged()) {
            return;
        }

        $recipients = $canal->owners()->get()
            ->push($member)
            ->unique('id')
            ->reject(fn (User $user) => $actor !== null && $user->is($actor))
            ->values();

        MailSimulator::send(
            $recipients,
            new CanalOwnershipChanged($canal, $isOwner ? CanalOwnershipChanged::OWNER_ADDED : CanalOwnershipChanged::OWNER_REMOVED, $member, $to),
            $this->simulate(),
            $canal,
        );
    }

    /**
     * Prevzatie kanála. Potvrdenie ide tomu, kto ho prevzal, a upozornenie na
     * kontaktnú adresu kanála, ak je iná — tak sa o prevzatí dozvie aj
     * organizácia, keby odkaz prijal niekto nepovolaný.
     */
    public function claimed(Canal $canal, User $claimant, ?User $technicalOwner, ?CanalClaim $claim = null): void
    {
        Recorder::info('canals', 'claimed', "Kanál {$canal->name} prevzal nový vlastník",
            status: 'ok',
            recipient: $claimant->email,
            userId: $claimant->id,
            subject: $canal,
            context: [
                'member_id' => $claimant->id,
                'removed_technical_owner_id' => $technicalOwner?->id,
                'claim_id' => $claim?->id,
                'method' => $claim?->method->value,
                'actor_id' => $this->actor()?->id,
            ],
        );

        // Kontakt kanála aj adresa, ktorá prevzatie preukázala (ak sú iné
        // než adresa preberajúceho) — obe sa o prevzatí musia dozvedieť.
        $own = mb_strtolower((string) $claimant->email);
        $contacts = collect([$claim?->contact_email, $canal->email])
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && $email !== $own && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->map(fn ($email) => Notification::route('mail', $email));

        MailSimulator::send(
            collect([$claimant])->concat($contacts),
            new CanalOwnershipChanged($canal, CanalOwnershipChanged::CLAIMED, $claimant, CanalRole::Owner, $claim),
            $this->simulate(),
            $canal,
        );
    }

    /** Zápis kroku žiadosti o prevzatie (requested, confirmed, approved, rejected, contested, reverted, expired). */
    public function claim(CanalClaim $claim, string $action, ?string $note = null): void
    {
        $canal = $claim->canal()->first();

        Recorder::info('canals', 'claim_' . $action,
            "Kanál {$canal?->name}: žiadosť o prevzatie — {$action} ({$claim->method->value})",
            status: 'ok',
            recipient: $claim->user?->email ?? $claim->contact_email,
            userId: $claim->user_id,
            subject: $canal,
            context: [
                'claim_id' => $claim->id,
                'method' => $claim->method->value,
                'status' => $claim->status->value,
                'note' => $note,
                'actor_id' => $this->actor()?->id,
            ],
        );
    }

    /** `sent`, `resent`, `accepted`, `revoked`, `claim_created` (pozvánka systému na prevzatie). */
    public function invitation(CanalInvitation $invitation, string $action, ?User $user = null): void
    {
        $canal = $invitation->canal()->first();
        $what = match ($action) {
            'sent' => 'pozvánka odoslaná',
            'resent' => 'pozvánka odoslaná znova',
            'accepted' => 'pozvánka prijatá',
            'revoked' => 'pozvánka zrušená',
            'claim_created' => 'vytvorená pozvánka na prevzatie',
            default => $action,
        };

        Recorder::info('canals', 'invitation_' . $action,
            "Kanál {$canal?->name}: {$what} ({$invitation->role->value})",
            status: 'ok',
            recipient: $invitation->email,
            userId: $user?->id,
            subject: $canal,
            context: [
                'invitation_id' => $invitation->id,
                'role' => $invitation->role->value,
                'invited_by_user_id' => $invitation->invited_by_user_id,
                'actor_id' => $this->actor()?->id,
            ],
        );
    }

    private function describe(Canal $canal, string $event, ?CanalRole $from, ?CanalRole $to): string
    {
        return match ($event) {
            'member_added' => "Kanál {$canal->name}: nový člen ({$to?->value})",
            'member_removed' => "Kanál {$canal->name}: odobratý člen ({$from?->value})",
            default => "Kanál {$canal->name}: zmena roly ({$from?->value} → {$to?->value})",
        };
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function simulate(): bool
    {
        return (bool) config('canals.simulate_ownership_mail', true);
    }
}
