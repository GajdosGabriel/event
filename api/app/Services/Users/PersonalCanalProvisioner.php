<?php

namespace App\Services\Users;

use App\Enums\CanalIdentityMode;
use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Vytvorí používateľovi osobný kanál, ak ešte žiadny nemá.
 *
 * Jediné miesto, kde osobný kanál vzniká — volá ho UserObserver po overení
 * e-mailu aj AttendeeRegistrar pri založení účtu účastníka. Guard na
 * existujúci kanál zabezpečí, že sa nevytvorí druhýkrát.
 */
class PersonalCanalProvisioner
{
    public function ensureFor(User $user): ?Canal
    {
        if ($user->canals()->exists()) {
            return null;
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
            'identity_mode' => CanalIdentityMode::Personal->value,
        ]);

        $user->canals()->attach($canal->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->canal_id = $canal->id;
        $user->save();

        $pending?->delete();

        return $canal;
    }
}
