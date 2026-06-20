<?php

namespace App\Observers;

use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\User;
use App\Models\Canal;
use Illuminate\Support\Str;

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
        if ($user->canals()->exists()) {
            return;
        }

        $pending = $user->pendingProfile()->first();
        $displayName = $pending?->display_name;
        if (! $displayName) {
            $displayName = Str::before($user->email, '@');
        }
        if ($displayName === '') {
            $displayName = 'User ' . $user->id;
        }

        $canal = Canal::create([
            'name' => $displayName,
            'email' => $user->email,
            'status' => 'published',
            'body' => 'Osobný kanál používateľa ' . $displayName,
            'published_at' => now(),
            'registration_source' => RegistrationSource::SELF->value,
            'identity_mode' => CanalIdentityMode::Pseudonymous->value,
        ]);

        $user->canals()->attach($canal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $user->canal_id = $canal->id;
        $user->save();

        $pending?->delete();
    }
}
