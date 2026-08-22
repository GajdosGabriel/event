<?php

namespace App\Policies;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\User;

class CanalPolicy
{
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
     * Archivovaný kanál sa editovať smie — archív znamená „mimo prevádzky", nie
     * „nedotknuteľné". Viď rovnaký komentár vo VenuePolicy::update().
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
     * Stavový zámok: mazať sa dá len to, čo nie je vonku (`published`).
     *
     * Archivované sa nekontroluje — kanál s podujatiami, miestami či členmi drží
     * referenčný zámok bez ohľadu na stav, a prázdny archivovaný kanál si
     * vlastník aj tak odomkol prepnutím stavu na koncept. Viď
     * VenuePolicy::delete(), platí to isté.
     *
     * Referenčný zámok tu nie je zámerne — patrí do modelu a odchádza ako 422.
     */
    public function delete(User $user, Canal $canal): bool
    {
        return $canal->status !== ModelStatus::Published
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

    /**
     * Ani tu sa archív nekontroluje — je to rovnaký mäkký zámok ako pri mazaní
     * (obišiel ho jeden prepnutý stav) a archív pri kanáli znamená „mimo
     * prevádzky", nie zmrazený záznam. Vyradený kanál sa aj tak dá vrátiť medzi
     * koncepty a tím v ňom upratať; zamykať to len o krok skôr nič nechránilo.
     */
    public function manageTeam(User $user, Canal $canal): bool
    {
        return $user->canInCanal((int) $canal->id, 'canal.team');
    }
}
