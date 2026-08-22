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

    private function makeFile(string $type, int $id, string $name): File
    {
        return File::create([
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
        ]);
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
}
