<?php

namespace App\Policies;

use App\Enums\ModelStatus;
use App\Models\User;
use App\Models\Venue;
use App\Policies\Traits\DeniesArchivedUpdate;

class VenuePolicy
{
    use DeniesArchivedUpdate;
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Venue $venue): bool
    {
        return $venue->activeCanals()
            ->whereIn('canals.id', $user->canalIdsWithAbility('venue.view'))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyCanalAbility('venue.create');
    }

    public function update(User $user, Venue $venue): bool
    {
        return $this->isNotArchived($venue) && $this->ownsVenueThrough($user, $venue, 'venue.update');
    }

    public function publish(User $user, Venue $venue): bool
    {
        return $this->update($user, $venue)
            && $venue->status === ModelStatus::Draft;
    }


    public function archive(User $user, Venue $venue): bool
    {
        return $venue->status === ModelStatus::Published
            && $this->ownsVenueThrough($user, $venue, 'venue.delete');
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $this->isNotArchived($venue)
            && (
                (
                    $venue->status !== ModelStatus::Published
                    && $this->ownsVenueThrough($user, $venue, 'venue.delete')
                )
                || $this->isLinkedToVenueCanal($user, $venue)
            );
    }

    public function restore(User $user, Venue $venue): bool
    {
        return $this->ownsVenueThrough($user, $venue, 'venue.delete');
    }

    public function forceDelete(User $user, Venue $venue): bool
    {
        return false;
    }

    /**
     * Miesto patrí kanálu, v ktorom má používateľ dané právo. Vlastníctvo miesta
     * je na kanáli (canal_venue.is_owner), právo na členstve v tom kanáli.
     */
    private function ownsVenueThrough(User $user, Venue $venue, string $ability): bool
    {
        return $venue->ownerCanals()
            ->whereIn('canals.id', $user->canalIdsWithAbility($ability))
            ->exists();
    }

    /**
     * Miesto zdieľané cudzím kanálom sa nemaže, len odpája — to je pre pripojený
     * kanál úroveň úpravy, nie mazania.
     */
    private function isLinkedToVenueCanal(User $user, Venue $venue): bool
    {
        return $venue->activeCanals()
            ->whereIn('canals.id', $user->canalIdsWithAbility('venue.update'))
            ->exists();
    }
}
