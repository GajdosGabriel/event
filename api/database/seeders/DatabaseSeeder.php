<?php

namespace Database\Seeders;

use App\Models\Canal;
use App\Models\Event;
use App\Models\PendingProfile;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\Venue;
use App\Enums\ModelStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $users = User::factory()->count(20)->create();
        $users->take(2)->each(function (User $user) {
            $user->update([
                'blocked_at' => now()->subDays(2),
                'blocked_reason' => 'Seeder: policy violation',
            ]);
        });

        $users->slice(2, 2)->each(function (User $user) {
            $user->update(['status' => ModelStatus::Archived]);
        });

        $users->each(function (User $user) {
            PendingProfile::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'display_name' => 'User ' . $user->id,
            ]);
        });

        PendingRegistration::create([
            'email' => 'pending1@example.com',
            'password' => Hash::make('password'),
            'display_name' => 'Pending User 1',
            'registered_via' => 'local',
            'verification_token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHours((int) config('registration.verification_ttl_hours', 48)),
        ]);

        $createdCanals = collect();

        $users->each(function (User $creator) use ($users, $createdCanals) {
            $canal = Canal::factory()->create();
            $createdCanals->push($canal);

            // Assign canal-owner role to the creator if not already assigned
            if (! $creator->hasRole('canal-owner')) {
                $creator->assignRole('canal-owner');
            }

            $canal->users()->attach($creator->id, [
                'is_owner' => true,
                'status' => ModelStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $memberIds = $users
                ->where('id', '!=', $creator->id)
                ->random(rand(2, 5))
                ->pluck('id');

            $canal->users()->attach(
                $memberIds
                    ->mapWithKeys(fn (int $memberId) => [
                        $memberId => [
                            'is_owner' => false,
                            'status' => ModelStatus::Draft->value,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ])
                    ->all()
            );
        });

        $createdCanals->each(function (Canal $canal): void {
            Venue::factory()->forCanal($canal->id, isOwner: true, status: ModelStatus::Published)->create([
                'village_id' => (int) ($canal->municipality_id ?: 4209),
            ]);
        });

        if ($createdCanals->count() >= 2) {
            $sharedCanalIds = $createdCanals->take(3)->pluck('id')->all();
            $ownerCanalId = (int) $sharedCanalIds[0];
            $ownerCanal = $createdCanals->firstWhere('id', $ownerCanalId);

            Venue::factory()->forCanals($sharedCanalIds, $ownerCanalId)->create([
                'village_id' => (int) ($ownerCanal?->municipality_id ?: 4209),
                'name' => 'Zdielane komunitne centrum',
                'body' => 'Seeder venue priradene k viacerym kanalom cez pivot tabulku.',
            ]);
        }

        Event::factory()->count(20)->past()->create();
        Event::factory()->count(20)->future()->create();
        Event::factory()->count(20)->create();

        $userSuperadmin = User::query()->orderBy('id')->first();
        $userSuperadmin?->update([
            'email'             => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
            'password'          => Hash::make(env('SEED_ADMIN_PASSWORD', 'password')),
            'registered_via'    => 'local',
            'provider_id'       => null,
            'canal_id'          => 1,
            'email_verified_at' => now(),
        ]);
        $userSuperadmin?->assignRole('super-admin');

        $userSuperadmin?->canals()->syncWithoutDetaching([
            1 => [
                'is_owner' => true,
                'status' => ModelStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        if ($userSuperadmin !== null) {
            PendingProfile::updateOrCreate([
                'user_id' => $userSuperadmin->id,
            ], [
                'display_name' => 'Gabriel Gajdoš',
            ]);
        }

        Canal::query()->find(1)?->update([
            'name' => 'Gabriel Gajdoš',
        ]);

        $userEditor = User::find(3);
        $userEditor?->assignRole('canal-editor');
    }
}
