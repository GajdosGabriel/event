<?php

namespace App\Policies;

use App\Enums\ModelStatus;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Traits\DeniesArchivedUpdate;

class OrganizationPolicy
{
    use DeniesArchivedUpdate;
    public function viewAny(User $user): bool
    {
        return $user->can('organization.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('organization.view');
    }

    public function create(User $user): bool
    {
        return $user->can('organization.create');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->isNotArchived($organization) && $user->can('organization.update');
    }

    public function archive(User $user, Organization $organization): bool
    {
        return $organization->status === ModelStatus::Published
            && $user->can('organization.delete');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->isNotArchived($organization)
            && $organization->status !== ModelStatus::Published
            && $user->can('organization.delete');
    }

    public function restore(User $user, Organization $organization): bool
    {
        return $user->can('organization.delete');
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return false;
    }
}
