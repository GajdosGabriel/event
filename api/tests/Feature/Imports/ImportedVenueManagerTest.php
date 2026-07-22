<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Imports\ImportedVenueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportedVenueManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reuses_an_existing_venue_instead_of_creating_a_duplicate(): void
    {
        // Regresia: importované miesta majú category = NULL a filter
        // `category != 'fallback'` ich v SQL odfiltroval (NULL != 'x' je NULL),
        // takže každý beh importu zakladal nový duplikát.
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        $municipality = Municipality::query()->first();

        $existing = Venue::factory()->create([
            'name' => 'Šarišský hrad',
            'category' => null,
            'village_id' => $municipality?->id,
        ]);

        $before = Venue::query()->count();

        $first = app(ImportedVenueManager::class)->resolveOrDetect($canal, 'Šarišský hrad', 'Veľký Šariš');
        $second = app(ImportedVenueManager::class)->resolveOrDetect($canal, 'Šarišský hrad', 'Veľký Šariš');

        $this->assertSame($existing->id, $first->id);
        $this->assertSame($existing->id, $second->id);
        $this->assertSame($before, Venue::query()->count());
    }

    #[Test]
    public function it_does_not_reuse_the_shared_fallback_venue_as_a_named_match(): void
    {
        config()->set('services.imports.detect_canal_with_ai', false);
        config()->set('services.imports.describe_with_ai', false);

        $canal = Canal::factory()->create();
        $fallback = app(ImportedVenueManager::class)->resolveFallbackVenue();

        $resolved = app(ImportedVenueManager::class)->resolveOrDetect($canal, $fallback->name, null);

        $this->assertNotSame($fallback->id, $resolved->id, 'Zberné miesto sa nesmie priradiť podľa názvu.');
    }
}
