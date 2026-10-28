<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Account\AccountMailing;
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

        app(AccountMailing::class)->syncContact($user->email);
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

        // Adresár v Accounte: štítky `user`/`verified` (pri zmene adresy aj
        // stará adresa, ktorá o účet prišla).
        if ($user->wasChanged(['email', 'email_verified_at'])) {
            $mailing = app(AccountMailing::class);
            $mailing->syncContact($user->email);

            if ($user->wasChanged('email')) {
                $mailing->syncContact($user->getOriginal('email'));
            }
        }
    }

    public function deleted(User $user): void
    {
        app(AccountMailing::class)->syncContact($user->email);
    }

    protected function ensureVerifiedCanal(User $user): void
    {
        app(PersonalCanalProvisioner::class)->ensureFor($user);
    }
}
