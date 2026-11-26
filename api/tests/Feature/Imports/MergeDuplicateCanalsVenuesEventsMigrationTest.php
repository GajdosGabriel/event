<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\Imports\EventSourceLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MergeDuplicateCanalsVenuesEventsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_12_02_100000_merge_duplicate_canals_venues_events.php';

    #[Test]
    public function it_merges_duplicates_and_fixes_foreign_websites(): void
    {
        $this->municipality(3246, 'spisske-podhradie', 'Spišské Podhradie');
        $this->municipality(242, 'bratislava', 'Bratislava');
        $this->municipality(4209, 'cele-slovensko', 'Celé Slovensko');

        $diocese = $this->canal(36, 'Spišská diecéza', 3246, 'https://www.kapitula.sk');
        $bishopric = $this->canal(1902, 'Spišské biskupstvo', 3246, 'https://www.kapitula.sk');
        $bishopricEvent = Event::factory()->create(['canal_id' => $bishopric->id, 'user_id' => User::factory()->create()->id]);

        $archive = $this->canal(190, 'Gabriel Gajdoš', 4209, 'https://hlascirkvi.sk');
        $own = $this->canal(189, 'UPeCe Žilina', 4209, 'https://upece.sk');

        $hall = Venue::factory()->create(['id' => 723, 'name' => 'Zrkadlová sieň Primaciálneho paláca', 'village_id' => 242, 'status' => ModelStatus::Published]);
        $duplicateHall = Venue::factory()->create(['id' => 1062, 'name' => 'Zrkadlová sála Primaciálneho paláca', 'village_id' => 242, 'status' => ModelStatus::Published]);

        $start = Carbon::parse('2026-11-19 23:00:00');
        $keep = Event::factory()->create(['id' => 11424, 'start_at' => $start, 'canal_id' => $diocese->id, 'user_id' => User::factory()->create()->id, 'orginal_source' => 'https://www.tkkbs.sk/a']);
        $drop = Event::factory()->create(['id' => 11425, 'start_at' => $start, 'canal_id' => $diocese->id, 'user_id' => User::factory()->create()->id, 'orginal_source' => 'https://www.tkkbs.sk/b']);
        $moved = Event::factory()->create(['id' => 11400, 'start_at' => $start->copy()->addDay(), 'canal_id' => $diocese->id, 'user_id' => User::factory()->create()->id]);
        Event::factory()->create(['id' => 11232, 'start_at' => $start, 'canal_id' => $diocese->id, 'user_id' => User::factory()->create()->id]);

        (require database_path(self::MIGRATION))->up();

        $this->assertSame($diocese->id, (int) $bishopricEvent->fresh()->canal_id);
        $this->assertSame($diocese->id, (int) Canal::withTrashed()->find($bishopric->id)->merged_into_id);

        $this->assertNull($archive->fresh()->website);
        $this->assertSame('https://upece.sk', $own->fresh()->website, 'vlastný web ostáva');

        $this->assertNull(Venue::query()->find($duplicateHall->id));

        $this->assertSoftDeleted('events', ['id' => $drop->id]);
        $this->assertSame($keep->id, EventSourceLookup::find('https://www.tkkbs.sk/b')?->id);
        $this->assertNotNull($moved->fresh(), 'podujatie s iným začiatkom sa nezlučuje');
    }

    private function canal(int $id, string $name, int $municipality, ?string $website): Canal
    {
        return Canal::factory()->create([
            'id' => $id, 'name' => $name, 'municipality_id' => $municipality, 'website' => $website, 'claimed_at' => null,
            'identity_mode' => 'organization', 'organization_id' => null, 'status' => ModelStatus::Published->value,
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
