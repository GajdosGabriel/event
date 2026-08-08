<?php

namespace App\Policies;

use App\Enums\ModelStatus;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Traits\DeniesArchivedUpdate;

/**
 * Organizácia je fakturačná identita kanála, nie samostatný tenant — nemá
 * vlastných členov. Prístup sa preto odvodzuje z členstva v kanáloch, ktoré
 * pod ňu patria (`canals.organization_id`), rovnako ako pri CanalPolicy.
 *
 * Globálne spatie právo (`organization.*`) ostáva len ako hrubé sito pre
 * `permission:` middleware na routách — seeder ho dáva aj `canal-editor`,
 * takže samo o sebe nehovorí nič o vlastníctve konkrétnej firmy.
 *
 * V /admin (middleware `role:super-admin`) tieto kontroly nebijú: Gate::before
 * v AuthServiceProvider tam super-adminovi povolí všetko. Scope sa uplatní
 * len v /dashboard, kde je bypass zámerne vypnutý.
 */
class OrganizationPolicy
{
    use DeniesArchivedUpdate;

    public function viewAny(User $user): bool
    {
        return $user->can('organization.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('organization.view')
            && $this->isReachableBy($user, $organization);
    }

    /**
     * Fakturačné údaje z Accountu (IČO, DIČ, IBAN, sídlo) — nie sú to bežné
     * prevádzkové dáta ako profil organizátora. Vidí ich len ten, kto smie
     * firmu aj meniť, teda vlastník kanála; dramaturg (editor) nie.
     */
    public function viewBilling(User $user, Organization $organization): bool
    {
        return $this->isManagedBy($user, $organization, 'canal.update');
    }

    public function create(User $user): bool
    {
        return $user->can('organization.create');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->isNotArchived($organization)
            && $user->can('organization.update')
            && $this->isManagedBy($user, $organization, 'canal.update');
    }

    public function archive(User $user, Organization $organization): bool
    {
        return $organization->status === ModelStatus::Published
            && $user->can('organization.delete')
            && $this->isManagedBy($user, $organization, 'canal.delete');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->isNotArchived($organization)
            && $organization->status !== ModelStatus::Published
            && $user->can('organization.delete')
            && $this->isManagedBy($user, $organization, 'canal.delete');
    }

    public function restore(User $user, Organization $organization): bool
    {
        return $user->can('organization.delete')
            && $this->isManagedBy($user, $organization, 'canal.delete');
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return false;
    }

    /**
     * Dostane sa používateľ k tejto firme cez niektorý zo svojich kanálov?
     *
     * Firma bez kanála je tu zámerne neprístupná — vrátane tých, čo zostali
     * z čias pred `canals.organization_id`. Dashboard väzbu priraďuje hneď
     * v tej istej transakcii ako vytvorenie firmy
     * (DashboardOrganizationController::store), takže osirieť môže len tá,
     * ktorú založil admin; k tej sa dostane opäť len admin.
     */
    private function isReachableBy(User $user, Organization $organization): bool
    {
        return array_key_exists((int) $organization->getKey(), $user->organizationCanalMap());
    }

    /**
     * Smie používateľ firmu meniť? Musí mať dané právo aspoň v jednom kanáli,
     * ktorý pod ňu patrí — teda byť tam vlastníkom, nie len editorom.
     *
     * Berú sa len kanály používateľa (organizationCanalMap je memoizovaná
     * na jeho inštancii). Cudzie kanály tej istej firmy sú tu bezvýznamné —
     * právo aj tak plynie z členstva, nie z firmy.
     */
    private function isManagedBy(User $user, Organization $organization, string $ability): bool
    {
        $canalIds = $user->organizationCanalMap()[(int) $organization->getKey()] ?? [];

        if ($canalIds === []) {
            return false;
        }

        return $user->canalIdsWithAbility($ability)
            ->intersect($canalIds)
            ->isNotEmpty();
    }
}
