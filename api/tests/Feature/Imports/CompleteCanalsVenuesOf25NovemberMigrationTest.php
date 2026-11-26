<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\File;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteCanalsVenuesOf25NovemberMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_12_01_100000_complete_canals_venues_of_25_november.php';

    #[Test]
    public function it_completes_records_merges_duplicate_and_moves_bari_mass(): void
    {
        Storage::fake('public');
        Storage::fake(config('filesystems.default'));

        $this->municipality(1892, 'liptovsky-mikulas', 'Liptovský Mikuláš');
        $this->municipality(2018, 'mala-calomija', 'Malá Čalomija');
        $this->municipality(3306, 'stredne-plachtince', 'Stredné Plachtince');
        $this->municipality(3596, 'trnava', 'Trnava');
        $this->municipality(1565, 'kosice', 'Košice');
        $this->municipality(542, 'detva', 'Detva');

        $parish = Canal::factory()->create([
            'id' => 1987, 'name' => 'Farnosť Liptovský Mikuláš', 'municipality_id' => 1892, 'claimed_at' => null,
            'identity_mode' => 'organization', 'organization_id' => null, 'status' => ModelStatus::Published->value,
            'body' => '<p>Jeden odsek.</p>',
        ]);
        $seniorate = Canal::factory()->create([
            'id' => 1990, 'name' => 'Novohradský seniorát ECAV', 'municipality_id' => 3306, 'claimed_at' => null,
            'identity_mode' => 'organization', 'organization_id' => null, 'status' => ModelStatus::Published->value,
            'street' => 'Malá Čalomija 78', 'latitude' => 48.7411522, 'longitude' => 19.4528646, 'coordinates_source' => 'municipality',
        ]);
        // Až po kanáloch s pevným ID: osobný kanál používateľa berie ID z autoinkrementu,
        // ktoré v plnej sade testov môže práve trafiť 1987.
        $user = User::factory()->create();

        $trnava = Venue::factory()->create(['id' => 823, 'name' => 'Bazilika svätého Mikuláša (Trnava)', 'village_id' => 3596, 'status' => ModelStatus::Published]);
        $bariMass = Event::factory()->create([
            'user_id' => $user->id, 'id' => 11469, 'canal_id' => $parish->id, 'venue_id' => $trnava->id,
            'name' => 'Liptovský Mikuláš a Bari spojí puto, omšu bude sláviť aj apoštolský nuncius',
        ]);

        $church = Venue::factory()->create(['id' => 1083, 'name' => 'Evanjelický kostol (Košice)', 'village_id' => 1565, 'status' => ModelStatus::Published]);
        $duplicate = Venue::factory()->create(['id' => 1121, 'name' => 'Evanjelickom chráme a. v. na Mlynskej ulici 23', 'village_id' => 1565, 'status' => ModelStatus::Published]);
        $concert = Event::factory()->create(['user_id' => $user->id, 'venue_id' => $duplicate->id]);

        $detva = Venue::factory()->create([
            'id' => 1123, 'name' => 'Kostol sv.Františka z Assisi', 'village_id' => 542, 'status' => ModelStatus::Published,
            'street' => 'Kostol', 'postcode' => '96212', 'country' => 'Slovakia', 'body' => null,
        ]);

        $advent = Event::factory()->create([
            'user_id' => $user->id, 'id' => 11475, 'canal_id' => $seniorate->id, 'name' => 'Adventná radosť v NOS 2026',
            'body' => '<p>Príďte sa potešiť a povzbudiť v adventnom očakávaní.</p>', 'email' => null, 'phone' => null,
        ]);
        $hybe = Event::factory()->create([
            'user_id' => $user->id, 'id' => 11461,
            'body' => '<ul><li><strong>Mesto:</strong> Hybe</li>'."\n\n".'<li>Ulica</li>'."\n\n".'<li>Webstránka</li>'."\n\n".'</ul>',
        ]);

        (require database_path(self::MIGRATION))->up();

        $parish->refresh();
        $this->assertSame(3, substr_count($parish->body, '<p>'));
        $this->assertStringContainsString('Stanislav Culka', $parish->body);
        $this->assertTrue(File::query()->where('fileable_type', $parish->getMorphClass())->where('fileable_id', $parish->id)->exists());

        $seniorate->refresh();
        $this->assertSame(2018, (int) $seniorate->municipality_id);
        $this->assertEqualsWithDelta(48.0842623, (float) $seniorate->latitude, 0.0001);

        $bari = Venue::query()->where('name', 'Bazilika svätého Mikuláša (Bari)')->firstOrFail();
        $this->assertSame('Taliansko', $bari->country);
        $this->assertSame($bari->id, (int) $bariMass->fresh()->venue_id);
        $this->assertSame('CC0', File::query()->where('fileable_type', $bari->getMorphClass())->where('fileable_id', $bari->id)->first()?->meta['license']);

        $this->assertNull(Venue::query()->find($duplicate->id));
        $this->assertSame($church->id, (int) $concert->fresh()->venue_id);

        $detva->refresh();
        $this->assertSame('Kostol sv. Františka z Assisi (Detva)', $detva->name);
        $this->assertSame('Partizánska 49/73', $detva->street);
        $this->assertSame('962 12', $detva->postcode);
        $this->assertSame('Slovensko', $detva->country);

        $advent->refresh();
        $this->assertSame('nos@ecav.sk', $advent->email);
        $this->assertSame(3, substr_count($advent->body, '<p>'));

        $this->assertStringNotContainsString('Ulica', $hybe->fresh()->body);
        $this->assertStringContainsString('Hybe', $hybe->fresh()->body);

        // Druhé spustenie nič nezdvojí.
        (require database_path(self::MIGRATION))->up();
        $this->assertSame(1, Venue::query()->where('name', 'Bazilika svätého Mikuláša (Bari)')->count());
        $this->assertSame(1, DB::table('municipalities')->where('slug', 'bari')->count());
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
