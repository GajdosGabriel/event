<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function create(User $user): bool
    {
        // Fileable-level authorization is handled in the controller.
        return true;
    }

    public function update(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function delete(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function restore(User $user, File $file): bool
    {
        return $this->canManageFileable($user, $file);
    }

    public function forceDelete(User $user, File $file): bool
    {
        return false;
    }

    private function canManageFileable(User $user, File $file): bool
    {
        $fileable = $file->fileable;

        if (!$fileable) {
            return false;
        }

        return $user->can('update', $fileable);
    }
}
