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
        $canalIds = $user->dashboardCanalIds();

        return $venue->activeCanals()
            ->whereIn('canals.id', $canalIds)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->dashboardCanalIds()->isNotEmpty();
    }

    public function update(User $user, Venue $venue): bool
    {
        return $this->isNotArchived($venue) && $this->isOwnerOfVenueCanal($user, $venue);
    }

    public function publish(User $user, Venue $venue): bool
    {
        return $this->update($user, $venue)
            && $venue->status === ModelStatus::Draft;
    }


    public function archive(User $user, Venue $venue): bool
    {
        return $venue->status === ModelStatus::Published
            && $this->isOwnerOfVenueCanal($user, $venue);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $this->isNotArchived($venue)
            && (
                (
                    $venue->status !== ModelStatus::Published
                    && $this->isOwnerOfVenueCanal($user, $venue)
                )
                || $this->isLinkedToVenueCanal($user, $venue)
            );
    }

    public function restore(User $user, Venue $venue): bool
    {
        return $this->isOwnerOfVenueCanal($user, $venue);
    }

    public function forceDelete(User $user, Venue $venue): bool
    {
        return false;
    }

    private function isOwnerOfVenueCanal(User $user, Venue $venue): bool
    {
        $ownedCanalIds = $user->ownedCanals()->pluck('canals.id')->map(fn($id) => (int) $id);

        return $venue->ownerCanals()
            ->whereIn('canals.id', $ownedCanalIds)
            ->exists();
    }

    private function isLinkedToVenueCanal(User $user, Venue $venue): bool
    {
        return $venue->activeCanals()
            ->whereIn('canals.id', $user->dashboardCanalIds())
            ->exists();
    }
}
