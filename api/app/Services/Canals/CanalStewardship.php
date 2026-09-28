<?php

namespace App\Services\Canals;

use App\Enums\CanalRole;
use App\Models\Canal;
use App\Models\CanalClaim;
use App\Models\User;
use App\Services\Imports\CollectionCanal;
use App\Services\Imports\ImportedCanalManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Prevzatie kanála, ktorý nikto nespravuje (importovaný/systémový), reálnym
 * organizátorom. Viď docs/canal-ownership.md.
 *
 * Pôvod kanála (`registration_source`) ostáva — mení sa len správa:
 * `claimed_at` + nový vlastník, technický vlastník z importu odchádza. Od tej
 * chvíle Canal::isManaged() vracia true, takže správy aj prihlášky idú novému
 * vlastníkovi a import kanál už nepremenuje ani si ho neprivlastní späť.
 */
class CanalStewardship
{
    public function __construct(
        private CanalMembership $membership,
        private ImportedCanalManager $imports,
        private CanalAuditor $auditor,
    ) {}

    public function complete(Canal $canal, User $user, ?CanalClaim $claim = null): void
    {
        if ($canal->isManaged()) {
            return;
        }

        // Zberný kanál (vyveska.sk…) drží podujatia rôznych organizátorov —
        // prevzatím by jeden z nich dostal do rúk aj cudzie podujatia.
        if (CollectionCanal::is($canal)) {
            throw ValidationException::withMessages([
                'token' => __('canal_team.collection_not_claimable'),
            ]);
        }

        $systemOwner = $this->systemOwner();

        $removed = null;

        DB::transaction(function () use ($canal, $user, $systemOwner, &$removed) {
            // Najprv nový vlastník — inak by detach technického vlastníka
            // narazil na poistku „posledný vlastník".
            $this->membership->attach($canal, $user, CanalRole::Owner);

            if ($systemOwner && $systemOwner->id !== $user->id
                && $canal->users()->where('users.id', $systemOwner->id)->exists()) {
                $this->membership->detach($canal, $systemOwner);
                $removed = $systemOwner;
            }

            $canal->forceFill([
                'claimed_at' => now(),
                'claimed_by_user_id' => $user->id,
            ])->save();
        });

        $this->auditor->claimed($canal, $user, $removed, $claim);
    }

    /**
     * Vráti prevzatie späť (napadnuté a administrátorom zrušené): kanál
     * dostane späť technického vlastníka, tím z obdobia správy odchádza
     * a kanál je znova nespravovaný. Pôvod ani podujatia sa nemenia.
     */
    public function revert(Canal $canal): void
    {
        $systemOwner = $this->systemOwner();

        if ($systemOwner === null) {
            throw new RuntimeException('Na vrátenie prevzatia treba super-admina (technického vlastníka).');
        }

        DB::transaction(function () use ($canal, $systemOwner) {
            // Najprv späť na nespravovaný — zmeny členstva nižšie potom
            // neposielajú e-maily o vlastníctve (tie patria spravovaným kanálom);
            // o vrátení sa žiadateľ dozvie z CanalClaimNotice.
            $canal->forceFill(['claimed_at' => null, 'claimed_by_user_id' => null])->save();

            // Technický vlastník skôr, než odídu ostatní — inak by poistka
            // „posledný vlastník" nepustila odobrať preberajúceho.
            $this->membership->attach($canal, $systemOwner, CanalRole::Owner);

            foreach ($canal->users()->where('users.id', '!=', $systemOwner->id)->get() as $member) {
                $this->membership->detach($canal, $member);
            }
        });
    }

    private function systemOwner(): ?User
    {
        try {
            return $this->imports->systemOwner();
        } catch (RuntimeException) {
            return null;
        }
    }
}
