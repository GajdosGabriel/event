<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Users\PersonalCanalProvisioner;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        if ($user->email_verified_at !== null) {
            $this->ensureVerifiedCanal($user);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $wasUnverified = $user->getOriginal('email_verified_at') === null;
        if ($wasUnverified && $user->email_verified_at !== null) {
            $this->ensureVerifiedCanal($user);
        }
    }

    protected function ensureVerifiedCanal(User $user): void
    {
        app(PersonalCanalProvisioner::class)->ensureFor($user);
    }
}
