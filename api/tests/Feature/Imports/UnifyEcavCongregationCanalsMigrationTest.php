<?php

namespace Tests\Feature\Imports;

use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifyEcavCongregationCanalsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_21_100000_unify_ecav_congregation_canals.php';

    #[Test]
    public function it_renames_imported_congregations_and_merges_duplicates(): void
    {
        $short = $this->importedCanal('CZ ECAV Modra');
        $long = $this->importedCanal('Cirkevný zbor ECAV na Slovensku Modra');
        $kralova = $this->importedCanal('Evanjelický cirkevný zbor v Modre-Kráľovej');
        $claimed = Canal::factory()->create([
            'name' => 'Cirkevný zbor ECAV Poltár',
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => now(),
        ]);
        $other = $this->importedCanal('Cirkevné zbory ECAV Kežmarok, Levoča a Sabinov');

        // Zostáva kanál s viac podujatiami, aj keď je novší.
        $user = User::factory()->create();
        Event::factory()->count(2)->create(['canal_id' => $long->id, 'user_id' => $user->id]);
        $moved = Event::factory()->create(['canal_id' => $short->id, 'user_id' => $user->id]);

        (require database_path(self::MIGRATION))->up();

        $this->assertSame('CZ ECAV Modra', $long->fresh()->name);
        $this->assertSame('cz-ecav-modra', $long->fresh()->slug);
        $this->assertSoftDeleted('canals', ['id' => $short->id, 'merged_into_id' => $long->id]);
        $this->assertSame($long->id, $moved->fresh()->canal_id);

        $this->assertSame('CZ ECAV v Modre-Kráľovej', $kralova->fresh()->name);
        $this->assertNull($kralova->fresh()->deleted_at);

        // Prevzatý kanál si názov určuje organizátor.
        $this->assertSame('Cirkevný zbor ECAV Poltár', $claimed->fresh()->name);
        $this->assertSame('Cirkevné zbory ECAV Kežmarok, Levoča a Sabinov', $other->fresh()->name);

        (require database_path(self::MIGRATION))->down();

        $this->assertSame('Cirkevný zbor ECAV na Slovensku Modra', $long->fresh()->name);
        $this->assertNull($short->fresh()->deleted_at);
    }

    private function importedCanal(string $name): Canal
    {
        return Canal::factory()->create([
            'name' => $name,
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => null,
            'organization_id' => null,
        ]);
    }
}
