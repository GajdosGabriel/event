<?php

namespace App\Services\Canals;

use App\Enums\CanalClaimMethod;
use App\Enums\CanalClaimStatus;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Models\CanalInvitation;
use App\Models\User;
use App\Notifications\CanalClaimNotice;
use App\Services\Imports\CollectionCanal;
use App\Services\SystemLog\MailSimulator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Prevzatie kanála ako proces (docs/canal-ownership.md, fáza 3).
 *
 * Tri cesty, všetky končia v CanalStewardship::complete():
 *  - pozvánka systému na kontaktnú adresu (recordInvitation) — prijať ju
 *    smie ktorýkoľvek účet, token dokazuje prístup ku schránke,
 *  - žiadosť z verejnej stránky potvrdená z kontaktnej schránky (confirm),
 *  - žiadosť, ktorú schváli administrátor (approve).
 *
 * Každé dokončené prevzatie dostane lehotu, počas ktorej ho kontaktná
 * adresa môže napadnúť (contest); o napadnutom rozhodne administrátor
 * (resolve — ponechať alebo vrátiť späť).
 */
class CanalClaims
{
    /** Platnosť odkazu na potvrdenie z kontaktnej schránky. */
    public const TOKEN_DAYS = 7;

    /** Lehota na námietku po prevzatí. */
    public const CONTEST_DAYS = 7;

    /** Koľko nevybavených žiadostí môže mať jeden účet naraz. */
    public const MAX_OPEN_PER_USER = 3;

    public function __construct(
        private CanalStewardship $stewardship,
        private CanalAuditor $auditor,
        private CanalEmails $emails,
    ) {}

    /** Dá sa kanál vôbec prevziať? Inak ValidationException s dôvodom. */
    public function assertClaimable(Canal $canal): void
    {
        $reason = match (true) {
            $canal->trashed() => 'canal_claims.not_found',
            $canal->isManaged() => 'canal_claims.already_managed',
            CollectionCanal::is($canal) => 'canal_team.collection_not_claimable',
            default => null,
        };

        if ($reason !== null) {
            throw ValidationException::withMessages(['canal' => __($reason)]);
        }
    }

    public function isClaimable(Canal $canal): bool
    {
        return ! $canal->trashed() && ! $canal->isManaged() && ! CollectionCanal::is($canal);
    }

    /** Platná kontaktná adresa kanála, na ktorú sa dá poslať potvrdenie. */
    public function contactOf(Canal $canal): ?string
    {
        $email = mb_strtolower(trim((string) $canal->email));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Žiadosť z verejnej stránky. */
    public function request(Canal $canal, User $user, CanalClaimMethod $method, ?string $message = null): CanalClaim
    {
        $this->assertClaimable($canal);

        if ($method === CanalClaimMethod::Invitation) {
            throw ValidationException::withMessages(['method' => __('canal_claims.invalid_method')]);
        }

        $pending = CanalClaim::query()
            ->where('canal_id', $canal->id)
            ->where('user_id', $user->id)
            ->where('status', CanalClaimStatus::Pending->value)
            ->get()
            ->first(fn (CanalClaim $claim) => $claim->isPending());

        if ($pending) {
            throw ValidationException::withMessages(['canal' => __('canal_claims.already_requested')]);
        }

        // Každá žiadosť cez kontakt píše do cudzej schránky — jeden účet nesmie
        // naraz „obťažovať" desiatky organizácií.
        $open = CanalClaim::query()
            ->where('user_id', $user->id)
            ->where('status', CanalClaimStatus::Pending->value)
            ->get()
            ->filter(fn (CanalClaim $claim) => $claim->isPending())
            ->count();

        if ($open >= self::MAX_OPEN_PER_USER) {
            throw ValidationException::withMessages(['canal' => __('canal_claims.too_many', ['max' => self::MAX_OPEN_PER_USER])]);
        }

        $contact = $method === CanalClaimMethod::ContactEmail ? $this->contactOf($canal) : null;

        if ($method === CanalClaimMethod::ContactEmail && $contact === null) {
            throw ValidationException::withMessages(['method' => __('canal_claims.no_contact')]);
        }

        $claim = CanalClaim::query()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'method' => $method->value,
            'status' => CanalClaimStatus::Pending->value,
            'contact_email' => $contact,
            'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
            'token' => $contact ? Str::random(64) : null,
            'expires_at' => $contact ? now()->addDays(self::TOKEN_DAYS) : null,
        ]);

        $this->auditor->claim($claim, 'requested');

        $contact
            ? $this->send([Notification::route('mail', $contact)], $claim, CanalClaimNotice::VERIFY)
            : $this->send($this->admins(), $claim, CanalClaimNotice::REQUESTED);

        return $claim;
    }

    /** Kontaktná schránka žiadosť potvrdila (odkaz z e-mailu VERIFY). */
    public function confirm(CanalClaim $claim): Canal
    {
        $this->assertPending($claim);

        return $this->complete($claim, 'confirmed');
    }

    public function approve(CanalClaim $claim, User $admin, ?string $note = null): Canal
    {
        $this->assertPending($claim);

        $claim->forceFill([
            'decided_by_user_id' => $admin->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        return $this->complete($claim, 'approved');
    }

    public function reject(CanalClaim $claim, User $admin, ?string $note = null): void
    {
        $this->assertPending($claim);

        $claim->forceFill([
            'status' => CanalClaimStatus::Rejected->value,
            'decided_by_user_id' => $admin->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();

        $this->auditor->claim($claim, 'rejected', $note);

        if ($claim->user) {
            $this->send([$claim->user], $claim, CanalClaimNotice::REJECTED);
        }
    }

    /**
     * Prevzatie cez prijatú pozvánku systému. Volá CanalInviter::accept()
     * namiesto priameho CanalStewardship, aby aj táto cesta mala doklad
     * a lehotu na námietku.
     */
    public function recordInvitation(CanalInvitation $invitation, User $user): Canal
    {
        $canal = $invitation->canal()->firstOrFail();
        $this->assertClaimable($canal);

        $claim = CanalClaim::query()->create([
            'canal_id' => $canal->id,
            'user_id' => $user->id,
            'method' => CanalClaimMethod::Invitation->value,
            'status' => CanalClaimStatus::Pending->value,
            'contact_email' => mb_strtolower((string) $invitation->email),
            'invitation_id' => $invitation->id,
        ]);

        return $this->complete($claim, 'accepted');
    }

    /** Kontaktná adresa s prevzatím nesúhlasí (odkaz z e-mailu o prevzatí). */
    public function contest(CanalClaim $claim, ?string $note = null): void
    {
        if (! $claim->isContestable()) {
            throw ValidationException::withMessages(['token' => __('canal_claims.not_contestable')]);
        }

        $claim->forceFill([
            'status' => CanalClaimStatus::Contested->value,
            'contested_at' => now(),
            'contest_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ])->save();

        $this->auditor->claim($claim, 'contested', $claim->contest_note);
        $this->send($this->admins(), $claim, CanalClaimNotice::CONTESTED);
    }

    /**
     * Rozhodnutie o napadnutom prevzatí: vrátiť späť (kanál znova nikto
     * nespravuje) alebo ponechať (námietka sa zamieta, ďalšia už nejde).
     */
    public function resolve(CanalClaim $claim, User $admin, bool $revert, ?string $note = null): void
    {
        if ($claim->status !== CanalClaimStatus::Contested) {
            throw ValidationException::withMessages(['claim' => __('canal_claims.not_contested')]);
        }

        $canal = $claim->canal()->firstOrFail();

        DB::transaction(function () use ($claim, $admin, $revert, $note, $canal) {
            if ($revert) {
                $this->stewardship->revert($canal);
            }

            $claim->forceFill([
                'status' => ($revert ? CanalClaimStatus::Reverted : CanalClaimStatus::Completed)->value,
                'contest_until' => $revert ? $claim->contest_until : now(),
                'decided_by_user_id' => $admin->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();
        });

        $this->auditor->claim($claim, $revert ? 'reverted' : 'contest_dismissed', $note);

        if ($revert && $claim->user) {
            $this->send([$claim->user], $claim, CanalClaimNotice::REVERTED);
        }
    }

    private function complete(CanalClaim $claim, string $action): Canal
    {
        $canal = $claim->canal()->firstOrFail();
        $user = $claim->user()->firstOrFail();

        $this->assertClaimable($canal);

        // Žiadosť aj správa kanála sa menia spolu — keby prevzatie zlyhalo,
        // žiadosť nesmie ostať „dokončená".
        DB::transaction(function () use ($claim, $canal, $user, $action) {
            $claim->forceFill([
                'status' => CanalClaimStatus::Completed->value,
                'completed_at' => now(),
                'token' => null,
                'contest_token' => Str::random(64),
                'contest_until' => now()->addDays(self::CONTEST_DAYS),
            ])->save();

            // Ostatné otvorené žiadosti o ten istý kanál stratili zmysel.
            CanalClaim::query()
                ->where('canal_id', $canal->id)
                ->where('id', '!=', $claim->id)
                ->where('status', CanalClaimStatus::Pending->value)
                ->update(['status' => CanalClaimStatus::Expired->value, 'token' => null, 'updated_at' => now()]);

            $this->auditor->claim($claim, $action);
            $this->stewardship->complete($canal, $user, $claim);
        });

        // Token prišiel z tejto schránky (pozvánka aj potvrdenie žiadosti) —
        // adresa žije a patrí organizátorovi. Schválenie adminom to nedokazuje.
        if ($claim->method !== CanalClaimMethod::AdminReview) {
            $this->emails->confirm($canal, $claim->contact_email, 'claim', create: true);
        }

        return $canal->fresh();
    }

    private function assertPending(CanalClaim $claim): void
    {
        if ($claim->isPending()) {
            return;
        }

        if ($claim->status === CanalClaimStatus::Pending) {
            $claim->forceFill(['status' => CanalClaimStatus::Expired->value, 'token' => null])->save();
            $this->auditor->claim($claim, 'expired');
        }

        throw ValidationException::withMessages(['token' => __('canal_claims.not_pending')]);
    }

    /** @return Collection<int, User> */
    private function admins(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))
            ->whereNotNull('email')
            ->get();
    }

    private function send(iterable $recipients, CanalClaim $claim, string $kind): void
    {
        MailSimulator::send(
            $recipients,
            new CanalClaimNotice($claim, $kind),
            (bool) config('canals.simulate_claim_mail', true),
            $claim->canal()->first(),
        );
    }
}
