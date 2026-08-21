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
        return $user->canInCanal((int) $canal->id, 'canal.view');
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
    /**
     * Archivovaný kanál sa editovať smie — archív je zámok proti mazaniu, nie
     * proti oprave. Viď rovnaký komentár vo VenuePolicy::update().
     */
    public function update(User $user, Canal $canal): bool
    {
        return $user->canInCanal((int) $canal->id, 'canal.update');
    }

    public function publish(User $user, Canal $canal): bool
    {
        return $this->update($user, $canal)
            && $canal->status !== ModelStatus::Published;
    }

    public function unpublish(User $user, Canal $canal): bool
    {
        return $this->update($user, $canal)
            && $canal->status === ModelStatus::Published;
    }

    /**
     * Determine whether the user can archive the model (published -> archived).
     */
    public function archive(User $user, Canal $canal): bool
    {
        return $canal->status === ModelStatus::Published
            && $user->canInCanal((int) $canal->id, 'canal.delete');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Canal $canal): bool
    {
        // Referenčný zámok tu nie je zámerne — viď VenuePolicy::delete().
        return $this->isNotArchived($canal)
            && $canal->status !== ModelStatus::Published
            && $user->canInCanal((int) $canal->id, 'canal.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Canal $canal): bool
    {
        return $user->canInCanal((int) $canal->id, 'canal.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Canal $canal): bool
    {
        return false;
    }

    /**
     * Zloženie tímu vidí každý člen kanála — kto ešte v tíme je, je bežná
     * prevádzková informácia. Meniť ho smie len vlastník.
     */
    public function viewTeam(User $user, Canal $canal): bool
    {
        return $user->canInCanal((int) $canal->id, 'canal.view');
    }

    public function manageTeam(User $user, Canal $canal): bool
    {
        return $this->isNotArchived($canal) && $user->canInCanal((int) $canal->id, 'canal.team');
    }
}
