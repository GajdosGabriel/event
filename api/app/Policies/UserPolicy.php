<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\DeniesArchivedUpdate;

class UserPolicy
{
    use DeniesArchivedUpdate;
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $this->isSelfOrSharesCanal($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyCanalAbility('canal.team');
    }

    /**
     * Determine whether the user can update the model.
     *
     * Zámerne prísnejšie než view(): úprava mení aj e-mail, takže by cez ňu šiel
     * prevziať účet. Odkedy sú v kanáli tímoví členovia (brigádnik na vstupe,
     * editor), „zdieľame kanál" na to nestačí — musí ísť o vlastníka kanála.
     */
    public function update(User $user, User $model): bool
    {
        if ((int) $user->id === (int) $model->id) {
            return $this->isNotArchived($model);
        }

        return $this->isNotArchived($model) && $this->canManageOtherUserAsOwner($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $this->isNotArchived($model) && $this->canManageOtherUserAsOwner($user, $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->canManageOtherUserAsOwner($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    private function isSelfOrSharesCanal(User $user, User $model): bool
    {
        if ((int) $user->id === (int) $model->id) {
            return true;
        }

        $userCanalIds = $user->dashboardCanalIds();
        $modelCanalIds = $model->dashboardCanalIds();

        return $userCanalIds->intersect($modelCanalIds)->isNotEmpty();
    }

    private function canManageOtherUserAsOwner(User $user, User $model): bool
    {
        if ((int) $user->id === (int) $model->id) {
            return false;
        }

        $ownedCanalIds = $user->canalIdsWithAbility('canal.team');

        if ($ownedCanalIds->isEmpty()) {
            return false;
        }

        $modelCanalIds = $model->dashboardCanalIds();

        return $ownedCanalIds->intersect($modelCanalIds)->isNotEmpty();
    }
}
