<?php

namespace Tests\Feature\Imports;

use App\Enums\RegistrationSource;
use App\Models\Canal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteChurchCongregationsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_28_100000_complete_church_congregations_and_newest_canals.php';

    #[Test]
    public function church_canals_become_congregations_and_claimed_ones_stay(): void
    {
        $this->municipality(242, 'bratislava', 'Bratislava');
        $this->municipality(2822, 'presov', 'Prešov');
        $this->municipality(3240, 'spisska-nova-ves', 'Spišská Nová Ves');

        $bratislava = $this->canal(1172, 'Cirkvi bratskej', 242);
        $presov = $this->canal(1852, 'Cirkev bratská', 242);
        $claimed = $this->canal(1982, 'Kongregácia sestier Božského Vykupiteľa', 3240, ['claimed_at' => now()]);

        (require database_path(self::MIGRATION))->up();

        $bratislava->refresh();
        $this->assertSame('Cirkev bratská Bratislava', $bratislava->name);
        $this->assertSame('cirkev-bratska-bratislava', $bratislava->slug);
        $this->assertSame('Cukrová 4', $bratislava->street);
        $this->assertSame(3, substr_count((string) $bratislava->body, '<p'));

        $presov->refresh();
        $this->assertSame('Cirkev bratská Prešov', $presov->name);
        $this->assertSame(2822, (int) $presov->municipality_id);

        $this->assertNull($claimed->fresh()->street);

        // Druhý beh nič nemení ani nepadne.
        (require database_path(self::MIGRATION))->up();
        $this->assertSame('Cirkev bratská Prešov', $presov->fresh()->name);
    }

    private function canal(int $id, string $name, int $municipality, array $extra = []): Canal
    {
        return Canal::factory()->create([
            'id' => $id,
            'name' => $name,
            'body' => '<p>'.$name.' — organizátor podujatí.</p>',
            'street' => null,
            'municipality_id' => $municipality,
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => null,
            ...$extra,
        ]);
    }

    private function municipality(int $id, string $slug, string $name): void
    {
        if (! DB::table('municipalities')->where('id', $id)->exists()) {
            DB::table('municipalities')->insert([
                'id' => $id, 'fullname' => $name, 'shortname' => $name, 'slug' => $slug, 'zip' => '',
                'district_id' => 0, 'region_id' => 0, 'use' => true,
            ]);
        }
    }
}
