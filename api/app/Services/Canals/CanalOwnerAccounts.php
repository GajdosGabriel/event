<?php

namespace App\Services\Canals;

use App\Models\Canal;
use App\Models\User;
use App\Services\SystemLog\Recorder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Účet člena tímu sa zmenil — adresy kanála a správa kanála sa mu prispôsobia
 * (volá UserObserver). Dvojička CanalEmails::recordOwner() pri prevzatí:
 *
 *  - overený e-mail: adresa vlastníka v jeho kanáloch je overená,
 *  - zmenený e-mail: stará adresa vlastníka odíde, pribudne nová,
 *  - zmazaný účet: jeho adresa z kanálov odíde (osobný údaj, ktorý by
 *    ostatní v tíme ďalej videli); prevzatý kanál bez ďalšieho vlastníka sa
 *    vráti na nespravovaný, aby nevisel „spravovaný" bez nikoho,
 *  - obnovený účet: adresa sa vráti do kanálov, ktoré stále vlastní.
 *    Kanál vrátený pri zmazaní sa sám nevracia — rozhodne admin.
 *
 * Zablokovanie účtu nemení nič: je vratné a komunikáciu už obchádza
 * User::canReceiveMessages().
 */
class CanalOwnerAccounts
{
    public function __construct(
        private CanalEmails $emails,
        private CanalStewardship $stewardship,
    ) {}

    public function verified(User $user, ?Canal $except = null): void
    {
        foreach ($this->ownedManaged($user) as $canal) {
            if (! $canal->is($except)) {
                $this->emails->recordOwner($canal, $user);
            }
        }
    }

    public function emailChanged(User $user, ?string $previous): void
    {
        foreach ($user->canals()->get() as $canal) {
            $this->emails->forgetOwner($canal, $previous);

            if ($canal->pivot->is_owner && $canal->isManaged()) {
                $this->emails->recordOwner($canal, $user);
            }
        }
    }

    public function deleted(User $user): void
    {
        foreach ($user->canals()->get() as $canal) {
            $this->emails->forgetOwner($canal, $user->email);

            // owners() zmazaný účet nevidí — ostali iní vlastníci?
            if ($canal->pivot->is_owner && $canal->isClaimed() && ! $canal->owners()->exists()) {
                $this->release($canal, $user);
            }
        }
    }

    public function restored(User $user): void
    {
        foreach ($this->ownedManaged($user) as $canal) {
            $this->emails->recordOwner($canal, $user);
        }
    }

    /** Prevzatý kanál, ktorému zmazaním účtu ubudol posledný vlastník. */
    private function release(Canal $canal, User $user): void
    {
        try {
            $this->stewardship->revert($canal);
        } catch (RuntimeException $e) {
            Recorder::warning('canals', 'owner_deleted', "Kanál {$canal->name}: vlastník zmazal účet, kanál ostal bez vlastníka",
                status: 'failed',
                userId: $user->id,
                subject: $canal,
                context: ['error' => $e->getMessage()],
            );

            return;
        }

        // revert() vidí len aktívnych členov — členstvo zmazaného účtu
        // odíde tu, aby sa po obnove účtu nevrátil ako vlastník.
        $canal->users()->detach($user->id);

        Recorder::warning('canals', 'owner_deleted', "Kanál {$canal->name}: vlastník zmazal účet, kanál znova nikto nespravuje",
            status: 'released',
            userId: $user->id,
            subject: $canal,
            context: ['member_id' => $user->id],
        );
    }

    /** @return Collection<int, Canal> */
    private function ownedManaged(User $user): Collection
    {
        return $user->ownedCanals()->get()->filter(fn (Canal $canal) => $canal->isManaged())->values();
    }
}
