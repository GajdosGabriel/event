<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HandOverCyrilometodiadaMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_30_100000_hand_over_cyrilometodiada_and_drop_organization_named_personal_canal.php';

    #[Test]
    public function it_hands_the_imported_canal_over_and_merges_the_registration_duplicate(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super-admin'));
        $member = User::factory()->create(['id' => 8, 'email' => 'clen@cyrilometodiada.sk', 'canal_id' => null]);

        $imported = $this->organization(276, 'CYRILOMETODIADA', 'import', $admin);
        $duplicate = $this->organization(1956, 'CYRILOMETODIADA, o. z.', 'self', $member);
        $event = Event::factory()->create(['canal_id' => $duplicate->id, 'user_id' => $member->id]);

        (require database_path(self::MIGRATION))->up();

        $imported->refresh();
        $this->assertSame($member->id, (int) $imported->claimed_by_user_id);
        $this->assertSame($imported->id, (int) $event->fresh()->canal_id);
        $this->assertSame($imported->id, (int) Canal::withTrashed()->find($duplicate->id)->merged_into_id);
        $this->assertFalse($imported->users()->where('users.id', $admin->id)->exists());
    }

    #[Test]
    public function it_drops_a_personal_canal_named_after_the_organization_of_the_account(): void
    {
        $user = User::factory()->create(['id' => 6]);
        $personal = Canal::factory()->create([
            'id' => 1948, 'name' => 'Konfederácia politických väzňov Slovenska', 'identity_mode' => 'personal',
        ]);
        $personal->users()->sync([$user->id => ['is_owner' => true, 'role' => 'owner', 'status' => 'published']]);
        $organization = $this->organization(419, 'Konfederácia politických väzňov Slovenska', 'import', $user);
        $organization->forceFill(['claimed_at' => now(), 'claimed_by_user_id' => $user->id])->save();
        $user->forceFill(['canal_id' => $personal->id])->save();

        (require database_path(self::MIGRATION))->up();

        $this->assertSoftDeleted('canals', ['id' => $personal->id]);
        $this->assertSame($organization->id, (int) $user->fresh()->canal_id);
        $this->assertNotNull($organization->fresh());
    }

    private function organization(int $id, string $name, string $source, User $owner): Canal
    {
        $canal = Canal::factory()->create([
            'id' => $id, 'name' => $name, 'identity_mode' => 'organization', 'organization_id' => null,
            'status' => ModelStatus::Published->value, 'registration_source' => $source, 'claimed_at' => null,
        ]);
        $canal->users()->sync([$owner->id => ['is_owner' => true, 'role' => 'owner', 'status' => 'published']]);

        return $canal;
    }
}
