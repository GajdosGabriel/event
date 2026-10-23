<?php

namespace Database\Seeders;

use App\Models\PendingProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Testovací super-admin pre lokálny vývoj (prihlásenie v prehliadači pri
 * overovaní zmien). Údaje sú len v api/.env (DEV_ADMIN_EMAIL,
 * DEV_ADMIN_PASSWORD), mimo gitu — skutočné heslo nikoho sa tu nepoužíva.
 *
 *     php artisan db:seed --class=LocalDevAdminSeeder
 *
 * Mimo prostredia `local` nerobí nič. Opakované spustenie heslo len prepíše.
 */
class LocalDevAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('auth.dev_admin.email');
        $password = (string) config('auth.dev_admin.password');

        if (! app()->environment('local') || $email === '' || $password === '') {
            $this->command?->warn('LocalDevAdminSeeder: preskočené (nie je local alebo chýba DEV_ADMIN_EMAIL / DEV_ADMIN_PASSWORD).');

            return;
        }

        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'password' => Hash::make($password),
            'registered_via' => 'local',
            'deleted_at' => null,
            'blocked_at' => null,
            'blocked_until' => null,
        ])->save();

        // Meno pre osobný kanál musí existovať skôr než overenie e-mailu
        // (UserObserver pri ňom kanál zakladá).
        if (! $user->canals()->exists() && ! $user->pendingProfile()->exists()) {
            PendingProfile::create(['user_id' => $user->id, 'display_name' => 'Dev Admin']);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole('super-admin');

        $this->command?->info("LocalDevAdminSeeder: super-admin {$email} pripravený.");
    }
}
