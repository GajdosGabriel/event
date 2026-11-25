<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\DuplicateDecision;
use App\Models\Event;
use App\Models\File;
use App\Models\User;
use App\Models\Venue;
use App\Services\Canals\CanalEmails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteCanalsVenuesOf24NovemberMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_29_100000_complete_canals_venues_of_24_november.php';

    #[Test]
    public function it_completes_records_merges_duplicates_and_moves_misplaced_events(): void
    {
        Storage::fake('public');
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create();

        $this->municipality(2333, 'nitra', 'Nitra');
        $this->municipality(3596, 'trnava', 'Trnava');
        $this->municipality(3201, 'smizany', 'Smižany');
        $this->municipality(242, 'bratislava', 'Bratislava');

        $bishopric = Canal::factory()->create([
            'id' => 347, 'name' => 'Nitrianske biskupstvo', 'municipality_id' => 2333, 'claimed_at' => null,
            'identity_mode' => 'organization', 'organization_id' => null, 'status' => ModelStatus::Published->value,
            'website' => 'https://ksnr.sk', 'street' => 'Samova 14', 'body' => '<p>a</p><p>b</p><p>c</p>',
        ]);
        app(CanalEmails::class)->sync($bishopric, 'rektorat@ksnr.sk', []);
        $square = Canal::factory()->create([
            'id' => 1986, 'name' => 'Námestie Jána Pavla II', 'municipality_id' => 2333, 'claimed_at' => null,
            'identity_mode' => 'organization', 'organization_id' => null, 'status' => ModelStatus::Published->value,
            'registration_source' => RegistrationSource::IMPORT->value,
        ]);
        $squareEvent = Event::factory()->create(['user_id' => $user->id, 'canal_id' => $square->id]);

        $cathedral = Venue::factory()->create(['id' => 123, 'name' => 'Katedrála sv. Jána Krstiteľa (Trnava)', 'village_id' => 3596, 'status' => ModelStatus::Published]);
        $hall = Venue::factory()->create([
            'id' => 127, 'name' => 'Mariánska sála', 'village_id' => 3596, 'status' => ModelStatus::Published,
            'street' => 'Františkánska', 'website' => 'https://www.trnava.sk',
            'body' => '<p>Sála pri františkánskom kláštore.</p><p>b</p><p>c</p>',
        ]);
        $misplaced = Event::factory()->create(['user_id' => $user->id, 'id' => 11453, 'venue_id' => $cathedral->id]);
        $other = Event::factory()->create(['user_id' => $user->id, 'venue_id' => $cathedral->id]);

        $shrine = Venue::factory()->create(['id' => 850, 'name' => 'Diecézna svätyňa Božieho milosrdenstva', 'village_id' => 3201, 'status' => ModelStatus::Published]);
        $draft = Venue::factory()->create(['id' => 1117, 'name' => 'Centrum Božieho milosrdenstva', 'village_id' => 3201, 'status' => ModelStatus::Draft]);

        $aula = Venue::factory()->create([
            'id' => 1118, 'name' => 'Šafárikovo nám. 6', 'village_id' => 242, 'status' => ModelStatus::Published,
            'street' => 'Šafárikovo námestie', 'postcode' => null, 'body' => null,
        ]);

        (require database_path(self::MIGRATION))->up();

        $bishopric->refresh();
        $this->assertSame('https://www.biskupstvo-nitra.sk', $bishopric->website);
        $this->assertSame('Námestie Jána Pavla II. 7', $bishopric->street);
        $this->assertSame('nitra@kbs.sk', app(CanalEmails::class)->primary($bishopric)?->email);
        $this->assertContains('rektorat@ksnr.sk', $bishopric->emails()->pluck('email')->all());
        $this->assertSame($bishopric->id, (int) $squareEvent->fresh()->canal_id);

        $this->assertSame($hall->id, (int) $misplaced->fresh()->venue_id);
        $this->assertSame($cathedral->id, (int) $other->fresh()->venue_id, 'iné podujatia katedrály sa nehýbu');
        $hall->refresh();
        $this->assertSame('Františkánska 33A', $hall->street);
        $this->assertSame('https://marianskasala.eu', $hall->website);
        $this->assertStringContainsString('Kostola Najsvätejšej Trojice', $hall->body);

        $this->assertNull(Venue::query()->find($draft->id));
        $this->assertDatabaseHas('duplicate_decisions', [
            'entity' => DuplicateDecision::ENTITY_VENUE, 'candidate_id' => $shrine->id, 'decision' => DuplicateDecision::SAME,
        ]);

        $aula->refresh();
        $this->assertSame('Aula Univerzity Komenského (Bratislava)', $aula->name);
        $this->assertSame('Šafárikovo námestie 6', $aula->street);
        $this->assertSame('814 99', $aula->postcode);
        $photo = File::query()->where('fileable_type', $aula->getMorphClass())->where('fileable_id', $aula->id)->first();
        $this->assertSame('CC0', $photo?->meta['license']);
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
