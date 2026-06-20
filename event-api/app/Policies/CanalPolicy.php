<?php

namespace App\Policies;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\User;
use App\Policies\Traits\DeniesArchivedUpdate;

class CanalPolicy
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
    public function view(User $user, Canal $canal): bool
    {
        return $this->canManageCanal($user, $canal);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Canal $canal): bool
    {
        return $this->isNotArchived($canal) && $this->canManageCanal($user, $canal);
    }

    public function publish(User $user, Canal $canal): bool
    {
        return $this->update($user, $canal)
            && $canal->status === ModelStatus::Draft;
    }

    /**
     * Determine whether the user can archive the model (published -> archived).
     */
    public function archive(User $user, Canal $canal): bool
    {
        return $canal->status === ModelStatus::Published
            && $this->isCanalOwner($user, $canal);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Canal $canal): bool
    {
        return $this->isNotArchived($canal)
            && $canal->status !== ModelStatus::Published
            && $this->isCanalOwner($user, $canal);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Canal $canal): bool
    {
        return $this->isCanalOwner($user, $canal);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Canal $canal): bool
    {
        return false;
    }

    private function canManageCanal(User $user, Canal $canal): bool
    {
        return $user->dashboardCanalIds()->contains((int) $canal->id);
    }

    private function isCanalOwner(User $user, Canal $canal): bool
    {
        return $user->ownedCanals()->where('canals.id', $canal->id)->exists();
    }
}
