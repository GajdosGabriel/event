<?php

namespace Tests\Feature\Files;

use App\Enums\FileType;
use App\Models\Event;
use App\Models\File;
use Tests\TestSupport\EventSetupTest;

/**
 * Výpis „Moje súbory" v dashboarde. Rozsah je odvodený od kanálov používateľa,
 * nie od policy per riadok, takže sa musí testovať práve on — cudzí súbor sa
 * v ňom nesmie objaviť ani cez stránkovanie, ani cez filter typu.
 */
class DashboardFileIndexTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user->givePermissionTo(['file.view']);
    }

    private function makeFile(string $type, int $id, string $name, array $attributes = []): File
    {
        return File::create(array_merge([
            'fileable_type' => $type,
            'fileable_id'   => $id,
            'name'          => $name,
            'original_name' => $name,
            'extension'     => 'jpg',
            'size'          => 1024,
            'mime_type'     => 'image/jpeg',
            'disk'          => 'public',
            'path'          => 'files/' . $name,
            'type'          => FileType::IMAGE->value,
        ], $attributes));
    }

    public function test_lists_only_files_from_users_own_records(): void
    {
        $own = $this->makeFile(Event::class, $this->futureEvent->id, 'moj.jpg');
        $foreign = $this->makeFile(Event::class, $this->cudziEvent->id, 'cudzi.jpg');

        $response = $this->getJson('/api/dashboard/files');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_includes_canal_files_and_the_owning_records_name(): void
    {
        $file = $this->makeFile(\App\Models\Canal::class, $this->canalPrimary->id, 'logo.jpg');

        $response = $this->getJson('/api/dashboard/files');

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $file->id);

        $this->assertNotNull($row);
        $this->assertSame($this->canalPrimary->name, $row['fileable_name']);
    }

    public function test_type_filter_narrows_the_listing(): void
    {
        $eventFile = $this->makeFile(Event::class, $this->futureEvent->id, 'plagat.jpg');
        $canalFile = $this->makeFile(\App\Models\Canal::class, $this->canalPrimary->id, 'logo.jpg');

        $response = $this->getJson('/api/dashboard/files?fileable_type=canal');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($canalFile->id, $ids);
        $this->assertNotContains($eventFile->id, $ids);
    }

    /** Prílohy jedného záznamu ťahá tá istá cesta — `fileable_id` sa nesmie rozbiť. */
    public function test_single_record_listing_still_works(): void
    {
        $file = $this->makeFile(Event::class, $this->futureEvent->id, 'priloha.jpg');

        $response = $this->getJson('/api/dashboard/files?fileable_type=event&fileable_id=' . $this->futureEvent->id);

        $response->assertOk();

        $this->assertContains($file->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_fileable_id_without_type_is_rejected(): void
    {
        $this->getJson('/api/dashboard/files?fileable_id=' . $this->futureEvent->id)
            ->assertStatus(422);
    }

    /**
     * Druh sa neukladá v stĺpci — skladá sa z MIME typu a prípony. Filter musí
     * použiť tie isté pravidlá ako odznak vo výpise, inak by o tom istom súbore
     * tvrdili každý niečo iné.
     */
    public function test_kind_filter_separates_images_from_documents(): void
    {
        $image = $this->makeFile(Event::class, $this->futureEvent->id, 'plagat.jpg');
        $pdf = $this->makeFile(Event::class, $this->futureEvent->id, 'program.pdf', [
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'type'      => FileType::FILE->value,
        ]);

        $ids = fn (string $kind) => collect($this->getJson('/api/dashboard/files?kind=' . $kind)->json('data'))
            ->pluck('id')->all();

        $this->assertContains($image->id, $ids('image'));
        $this->assertNotContains($pdf->id, $ids('image'));

        $this->assertContains($pdf->id, $ids('pdf'));
        $this->assertNotContains($image->id, $ids('pdf'));
    }

    public function test_unknown_kind_is_rejected(): void
    {
        $this->getJson('/api/dashboard/files?kind=nezmysel')->assertStatus(422);
    }

    /** Veľkosť je otázka, ktorú iné výpisy nemajú: „čo mi zaberá miesto". */
    public function test_sort_by_size_puts_the_largest_file_first(): void
    {
        $small = $this->makeFile(Event::class, $this->futureEvent->id, 'maly.jpg', ['size' => 10]);
        $large = $this->makeFile(Event::class, $this->futureEvent->id, 'velky.jpg', ['size' => 9_000_000]);

        $ids = collect($this->getJson('/api/dashboard/files?sort=largest')->json('data'))->pluck('id')->all();

        $this->assertSame($large->id, $ids[0]);
        $this->assertLessThan(
            array_search($small->id, $ids, true),
            array_search($large->id, $ids, true),
        );
    }

    /** Kôš má tri polohy; „len zmazané" nesmie prepustiť živý súbor. */
    public function test_trash_states_narrow_the_listing(): void
    {
        $live = $this->makeFile(Event::class, $this->futureEvent->id, 'zivy.jpg');
        $trashed = $this->makeFile(Event::class, $this->futureEvent->id, 'kos.jpg');
        $trashed->delete();

        $ids = fn (string $query) => collect($this->getJson('/api/dashboard/files?' . $query)->json('data'))
            ->pluck('id')->all();

        $default = $ids('');
        $this->assertContains($live->id, $default);
        $this->assertNotContains($trashed->id, $default);

        $withTrashed = $ids('with_trashed=1');
        $this->assertContains($live->id, $withTrashed);
        $this->assertContains($trashed->id, $withTrashed);

        $onlyDeleted = $ids('deleted=1');
        $this->assertContains($trashed->id, $onlyDeleted);
        $this->assertNotContains($live->id, $onlyDeleted);
    }

    /** Súbor nemá termín, takže rozsah dátumov sa viaže na dátum nahratia. */
    public function test_date_range_filters_by_upload_date(): void
    {
        $old = $this->makeFile(Event::class, $this->futureEvent->id, 'stary.jpg');
        $old->forceFill(['created_at' => now()->subMonth()])->saveQuietly();

        $fresh = $this->makeFile(Event::class, $this->futureEvent->id, 'novy.jpg');

        $ids = collect(
            $this->getJson('/api/dashboard/files?date_from=' . now()->subDay()->toDateString())->json('data')
        )->pluck('id')->all();

        $this->assertContains($fresh->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }
}
