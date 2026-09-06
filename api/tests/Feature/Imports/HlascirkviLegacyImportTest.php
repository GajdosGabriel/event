<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Imports\HlascirkviSourceUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HlascirkviLegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private string $importFile = 'import/hlascirkvi-test.jsonl';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);
        config()->set('services.imports.legacy.hlascirkvi_base_url', 'https://hlascirkvi.sk');

        Role::findOrCreate('super-admin');
        User::factory()->create()->assignRole('super-admin');
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/'.$this->importFile));

        parent::tearDown();
    }

    #[Test]
    public function it_keeps_the_original_created_at_instead_of_the_import_time(): void
    {
        // Archív má zmysel len s vlastnou časovou osou — keby Eloquent zapísal
        // čas behu, všetkých 12 000 podujatí by vzniklo „dnes“.
        $this->writeRows([$this->row(['created_at' => '2019-03-14 08:30:00'])]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $event = Event::query()->firstOrFail();
        $this->assertSame('2019-03-14 08:30:00', $event->created_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function a_finished_event_is_archived_and_a_future_one_published(): void
    {
        $this->writeRows([
            $this->row([
                'legacy_id' => 1,
                'start_at' => '2019-05-01 10:00:00',
                'end_at' => '2019-05-01 12:00:00',
            ]),
            $this->row([
                'legacy_id' => 2,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'start_at' => now()->addMonth()->format('Y-m-d H:i:s'),
                'end_at' => now()->addMonth()->addHours(2)->format('Y-m-d H:i:s'),
            ]),
        ]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $this->assertSame(ModelStatus::Archived, Event::query()->where('name', 'Podujatie 1')->firstOrFail()->status);
        $this->assertSame(ModelStatus::Published, Event::query()->where('name', 'Podujatie 2')->firstOrFail()->status);
    }

    #[Test]
    public function a_scraped_year_far_from_the_record_date_is_replaced_by_it(): void
    {
        // Starý scraper občas prečítal rok zo znenia článku: podujatie z roku
        // 2022 tak má start_at v roku 1452 a koniec až v roku 8330, ktorý je
        // nad stropom MySQL TIMESTAMP-u a insert by na ňom padol.
        $this->writeRows([$this->row([
            'created_at' => '2022-03-08 09:00:00',
            'start_at' => '1452-03-10 18:00:00',
            'end_at' => '8330-07-02 00:00:00',
        ])]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $event = Event::query()->firstOrFail();
        $this->assertSame('2022-03-08 09:00:00', $event->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2022-03-08 11:00:00', $event->end_at->format('Y-m-d H:i:s'));
        $this->assertTrue($event->meta['import']['date_unreliable']);
    }

    #[Test]
    public function a_missing_start_date_falls_back_to_the_record_date(): void
    {
        $this->writeRows([$this->row([
            'created_at' => '2020-06-01 07:00:00',
            'start_at' => null,
            'end_at' => null,
        ])]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $event = Event::query()->firstOrFail();
        $this->assertSame('2020-06-01 07:00:00', $event->start_at->format('Y-m-d H:i:s'));
        $this->assertTrue($event->meta['import']['date_unreliable']);
    }

    #[Test]
    public function running_the_import_twice_does_not_duplicate_events(): void
    {
        $this->writeRows([$this->row()]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])->assertSuccessful();
        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])->assertSuccessful();

        $this->assertSame(1, Event::query()->count());
    }

    #[Test]
    public function a_dry_run_leaves_the_database_untouched(): void
    {
        $this->writeRows([$this->row()]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Event::query()->count());
    }

    #[Test]
    public function an_event_without_a_source_url_gets_the_old_site_address_as_its_key(): void
    {
        // Bez tohto kľúča by sa staršie podujatia (scraper vtedy zdrojovú URL
        // nezapisoval) pri každom behu založili znovu.
        $url = HlascirkviSourceUrl::for([
            'legacy_id' => 879,
            'slug' => 'krizova-cesta',
            'title' => 'Krížová cesta',
            'orginal_source' => null,
        ]);

        $this->assertSame('https://hlascirkvi.sk/akcie/879/krizova-cesta', $url);
    }

    #[Test]
    public function an_existing_source_url_is_used_verbatim(): void
    {
        $url = HlascirkviSourceUrl::for([
            'legacy_id' => 5,
            'slug' => 'nieco',
            'title' => 'Niečo',
            'orginal_source' => 'https://www.tkkbs.sk/view.php?cisloclanku=20260820014',
        ]);

        $this->assertSame('https://www.tkkbs.sk/view.php?cisloclanku=20260820014', $url);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeRows(array $rows): void
    {
        $path = storage_path('app/'.$this->importFile);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode("\n", array_map(
            static fn (array $row) => json_encode($row, JSON_UNESCAPED_UNICODE),
            $rows
        ))."\n");
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        $legacyId = $overrides['legacy_id'] ?? 1;
        $nationwide = Municipality::nationwideId();

        return array_merge([
            'legacy_id' => $legacyId,
            'title' => "Podujatie {$legacyId}",
            'slug' => "podujatie-{$legacyId}",
            'body' => '<p>Popis podujatia.</p>',
            'start_at' => '2019-05-01 10:00:00',
            'end_at' => '2019-05-01 12:00:00',
            'published' => '2019-04-01 10:00:00',
            'created_at' => '2019-04-01 10:00:00',
            'updated_at' => '2019-04-01 10:00:00',
            'village_id' => $nationwide,
            'village_name' => 'Celé Slovensko',
            'street' => null,
            'clientwww' => null,
            'online_link' => null,
            'orginal_source' => "https://www.tkkbs.sk/view.php?cisloclanku={$legacyId}",
            'registration' => 'no',
            'entry_fee' => 'no',
            'org_id' => 101,
            'org_title' => 'TKKBS',
            'org_email' => null,
            'org_www' => null,
            'org_village_id' => $nationwide,
            'image_path' => null,
        ], $overrides);
    }
}
