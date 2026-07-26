<?php

namespace Tests\Feature\Imports;

use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Services\Imports\EventImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class EventImportDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_matches_an_already_imported_article_even_under_a_different_canal(): void
    {
        // Regresia: hľadanie podľa zdrojovej URL bolo zúžené na canal_id.
        // Stačilo teda, aby AI pri ďalšom nočnom behu určila organizátora inak
        // (alebo aby medzitým vznikol duplicitný kanál), a ten istý článok sa
        // naimportoval druhýkrát ako nový event.
        $sourceUrl = 'https://www.ecav.sk/aktuality/pozvanky/teologicka-konferencia-2025';

        $original = Event::factory()->create([
            'canal_id' => Canal::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'orginal_source' => $sourceUrl,
        ]);

        $otherCanal = Canal::factory()->create();

        $found = $this->findExisting($otherCanal->id, [
            'source_url' => $sourceUrl,
            'title' => 'Teologická konferencia 2025',
            'start_at' => $original->start_at,
        ]);

        $this->assertNotNull($found, 'Rovnaká zdrojová URL sa musí nájsť naprieč kanálmi.');
        $this->assertSame($original->id, $found->id);
    }

    #[Test]
    public function it_returns_the_oldest_match_when_duplicates_already_exist(): void
    {
        $sourceUrl = 'https://www.ecav.sk/aktuality/pozvanky/30-vyrocie-sez-ecav';

        $oldest = Event::factory()->create([
            'canal_id' => Canal::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'orginal_source' => $sourceUrl,
        ]);
        Event::factory()->create([
            'canal_id' => Canal::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'orginal_source' => $sourceUrl,
        ]);

        $found = $this->findExisting($oldest->canal_id, ['source_url' => $sourceUrl]);

        $this->assertSame($oldest->id, $found?->id, 'Import má konvergovať na prvý založený event.');
    }

    #[Test]
    public function it_does_not_match_a_different_article(): void
    {
        Event::factory()->create([
            'canal_id' => Canal::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'orginal_source' => 'https://www.vyveska.sk/sexualita-v-uceni-cirkvi.html',
        ]);

        $found = $this->findExisting(1, [
            'source_url' => 'https://www.vyveska.sk/sexualita-v-uceni-cirkvi-2.html',
        ]);

        $this->assertNull($found, 'Séria s vlastnou URL na článok nie je duplicita.');
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function findExisting(int $canalId, array $detail): ?Event
    {
        $method = new ReflectionMethod(EventImportService::class, 'findExistingEvent');

        return $method->invoke(app(EventImportService::class), $canalId, $detail);
    }
}
