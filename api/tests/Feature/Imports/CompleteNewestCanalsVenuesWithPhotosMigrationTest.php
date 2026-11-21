<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\File;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteNewestCanalsVenuesWithPhotosMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_26_100000_complete_newest_canals_venues_with_photos.php';

    #[Test]
    public function it_completes_canals_and_venues_merges_duplicates_and_attaches_photos(): void
    {
        Storage::fake('public');
        Storage::fake(config('filesystems.default'));

        $this->municipality(3829, 'velky-krtis', 'Veľký Krtíš');
        $this->municipality(2018, 'mala-calomija', 'Malá Čalomija');
        $this->municipality(3329, 'sucany', 'Sučany');
        $this->municipality(4133, 'zlate', 'Zlaté');
        $this->municipality(1565, 'kosice', 'Košice');

        $nos = Canal::factory()->create([
            'id' => 1980,
            'name' => 'NOS ECAV',
            'body' => '<p>NOS ECAV — organizátor podujatí.</p>',
            'website' => null,
            'phone' => null,
            'street' => null,
            'municipality_id' => 3829,
            'coordinates_source' => 'municipality',
            'latitude' => 48.7411522,
            'longitude' => 19.4528646,
            'registration_source' => RegistrationSource::IMPORT->value,
            'claimed_at' => null,
        ]);
        $sucany = Canal::factory()->create([
            'id' => 1979,
            'name' => 'CZ ECAV Sučany',
            'street' => null,
            'municipality_id' => 3329,
            'claimed_at' => now(),
        ]);

        $church = Venue::factory()->create([
            'id' => 1111,
            'name' => 'ECAV kostol',
            'status' => ModelStatus::Published,
            'village_id' => 3329,
            'street' => '94',
            'postcode' => '086 21',
            'body' => 'ECAV kostol v Sučanoch.',
            'coordinates_source' => 'municipality',
        ]);
        $munich = Venue::factory()->create([
            'id' => 1103,
            'name' => 'Mníchovská katedrála',
            'status' => ModelStatus::Published,
            'village_id' => 4133,
            'street' => null,
            'postcode' => '086 01',
            'coordinates_source' => null,
        ]);
        $cathedral = Venue::factory()->create([
            'id' => 109,
            'name' => 'Dóm svätej Alžbety',
            'status' => ModelStatus::Published,
            'village_id' => 1565,
        ]);
        $fragment = Venue::factory()->create([
            'id' => 1106,
            'name' => 'Katedrála',
            'status' => ModelStatus::Published,
            'village_id' => 1565,
        ]);

        (require database_path(self::MIGRATION))->up();

        $nos->refresh();
        $this->assertSame(2018, (int) $nos->municipality_id);
        $this->assertSame('Malá Čalomija 78', $nos->street);
        $this->assertSame(3, substr_count($nos->body, '<p>'));
        $this->assertContains('nos@ecav.sk', $nos->emails()->pluck('email')->all());

        $this->assertNull($sucany->fresh()->street);
        $this->assertFalse($this->hasPhoto($sucany), 'prevzatý kanál fotku nedostane');

        $church->refresh();
        $this->assertSame('Evanjelický kostol (Sučany)', $church->name);
        $this->assertSame('Námestie SNP', $church->street);
        $this->assertSame('038 52', $church->postcode);
        $this->assertSame('venue', $church->coordinates_source);
        $this->assertTrue($this->hasPhoto($church));

        $munich->refresh();
        $this->assertSame('Mníchov', DB::table('municipalities')->where('id', $munich->village_id)->value('fullname'));
        $this->assertSame('Nemecko', $munich->country);
        $this->assertSame('80331', $munich->postcode);

        $this->assertNull(Venue::query()->find($fragment->id));
        $this->assertNotNull($cathedral->fresh());

        $photo = File::query()->where('fileable_id', 1111)->where('fileable_type', $church->getMorphClass())->first();
        $this->assertSame('CC BY-SA 3.0', $photo->meta['license']);
        $this->assertSame('wikimedia_commons', $photo->meta['origin']);
    }

    private function hasPhoto(Canal|Venue $model): bool
    {
        return File::query()->where('fileable_type', $model->getMorphClass())->where('fileable_id', $model->getKey())->exists();
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
