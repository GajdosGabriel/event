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
    public function only_the_year_is_rewritten_and_the_day_and_time_survive(): void
    {
        // Starý scraper prečítal rok zo znenia článku: podujatie z roku 2022 má
        // start_at v roku 1452. Deň, mesiac aj hodina sú pritom správne, takže
        // sa prepisuje výlučne rok.
        $this->writeRows([$this->row([
            'created_at' => '2022-03-08 09:00:00',
            'start_at' => '1452-03-10 18:00:00',
            'end_at' => '1452-03-10 20:00:00',
        ])]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $event = Event::query()->firstOrFail();
        $this->assertSame('2022-03-10 18:00:00', $event->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2022-03-10 20:00:00', $event->end_at->format('Y-m-d H:i:s'));
        $this->assertTrue($event->meta['import']['date_year_corrected']);
        $this->assertFalse($event->meta['import']['end_at_estimated']);
    }

    #[Test]
    public function a_multi_day_event_keeps_its_span_when_the_year_is_corrected(): void
    {
        // Koniec dostane rovnaký posun ako začiatok — inak by šesťdňová akcia
        // po oprave roka skončila po dvoch hodinách.
        $this->writeRows([$this->row([
            'created_at' => '2022-02-08 09:00:00',
            'start_at' => '2014-02-14 19:00:00',
            'end_at' => '2014-02-20 21:00:00',
        ])]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $event = Event::query()->firstOrFail();
        $this->assertSame('2022-02-14 19:00:00', $event->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2022-02-20 21:00:00', $event->end_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function a_december_invitation_to_a_january_event_moves_to_the_next_year(): void
    {
        // Rok vzniku by podujatie posunul jedenásť mesiacov do minulosti,
        // takže patrí až do nasledujúceho roka.
        $this->writeRows([$this->row([
            'created_at' => '2021-12-20 09:00:00',
            'start_at' => '1999-01-15 18:00:00',
            'end_at' => '1999-01-15 20:00:00',
        ])]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $this->assertSame(
            '2022-01-15 18:00:00',
            Event::query()->firstOrFail()->start_at->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function an_event_without_any_start_date_is_not_imported(): void
    {
        // Deň ani mesiac neexistujú, takže sa nedá nič opraviť — vo výpise by
        // podujatie viselo na dni vzniku článku.
        $this->writeRows([$this->row([
            'created_at' => '2020-06-01 07:00:00',
            'start_at' => null,
            'end_at' => null,
        ])]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])
            ->assertSuccessful();

        $this->assertSame(0, Event::query()->count());
    }

    #[Test]
    public function running_the_import_twice_does_not_duplicate_events(): void
    {
        $this->writeRows([$this->row()]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile])->assertSuccessful();

        // Kurzor by druhý beh poslal rovno na koniec, takže by o duplicitách
        // nič nepovedal — tu sa má overiť deduplikácia, nie preskočenie dávky.
        $this->artisan('app:hlascirkvi-import', [
            '--file' => $this->importFile,
            '--reset-cursor' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Event::query()->count());
    }

    #[Test]
    public function batches_pick_up_where_the_previous_run_stopped(): void
    {
        // Na hostingu bez shellu spúšťa import webcron, ktorý má na jednu
        // požiadavku sekundy. Dávky preto musia nadväzovať, nie zakaždým
        // prechádzať archív od začiatku.
        $this->writeRows([
            $this->row(['legacy_id' => 1]),
            $this->row(['legacy_id' => 2]),
            $this->row(['legacy_id' => 3]),
        ]);

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile, '--limit' => 2])
            ->assertSuccessful();
        $this->assertSame(2, Event::query()->count());

        $this->artisan('app:hlascirkvi-import', ['--file' => $this->importFile, '--limit' => 2])
            ->assertSuccessful();

        $this->assertSame(3, Event::query()->count());
        $this->assertSame(
            [1, 2, 3],
            Event::query()->orderBy('id')->get()
                ->map(fn (Event $e) => $e->meta['import']['legacy_event_id'])
                ->all()
        );
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
