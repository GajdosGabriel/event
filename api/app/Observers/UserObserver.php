<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SystemLog\Recorder;
use App\Services\Users\PersonalCanalProvisioner;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Účty vznikajú na viacerých miestach (overenie registrácie, Google,
        // Facebook, admin) — observer ich zachytí všetky naraz.
        Recorder::info('auth', 'registered', 'Nový účet ('.($user->registered_via ?: 'local').')',
            status: 'ok',
            recipient: $user->email,
            userId: $user->id,
            ip: app()->runningInConsole() ? null : request()->ip(),
        );

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
