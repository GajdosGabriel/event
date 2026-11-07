<?php

namespace Tests\Feature\Console;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Models\Municipality;
use App\Models\Venue;
use App\Services\Places\VenueImageFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FindVenueImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Bus::fake();
    }

    #[Test]
    public function it_stores_up_to_four_commons_photos_with_the_first_as_primary(): void
    {
        $this->fakeWikimedia([
            $this->item('Q1', 'Kostol svätého Martina', 0.05, image: 'Kostol_sv_Martina.jpg', category: 'Church of St. Martin (Testovo)'),
        ], categoryFiles: [
            'File:Martin church 1.jpg',
            'File:Martin church map.jpg',
            'File:Martin church plan.png',
            'File:Martin church 2.jpg',
            'File:Martin church 3.jpg',
            'File:Martin church 4.jpg',
            'File:1945 poster.jpg',
        ]);

        $venue = $this->venue('Kostol sv. Martina');

        $this->artisan('app:venues-find-images', ['--sleep' => 0])->assertSuccessful();

        $files = $venue->files()->where('type', FileType::IMAGE->value)->orderBy('id')->get();

        $this->assertCount(4, $files);
        $this->assertTrue($files[0]->is_primary);
        $this->assertSame(1, $files->where('is_primary', true)->count());
        $this->assertSame(
            ['File:Kostol sv Martina.jpg', 'File:Martin church 1.jpg', 'File:Martin church 2.jpg', 'File:Martin church 3.jpg'],
            $files->map(fn ($file) => $file->meta['commons_title'])->all(),
        );
        $this->assertSame('Ján Fotograf', $files[0]->meta['author']);
        $this->assertSame('CC BY-SA 4.0', $files[0]->meta['license']);
        $this->assertSame('Q1', $files[0]->meta['wikidata']);

        // Sťahuje sa s popisným User-Agentom — generický Wikimedia škrtí.
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://upload.wikimedia.org/')
            && str_contains($request->header('User-Agent')[0] ?? '', 'venue photos'));
    }

    #[Test]
    public function a_church_with_another_dedication_is_not_used(): void
    {
        $this->fakeWikimedia([
            $this->item('Q2', 'Kostol svätého Mikuláša', 0.05, image: 'Mikulas.jpg'),
        ]);

        $venue = $this->venue('Kostol sv. Martina');

        $this->assertSame([], app(VenueImageFinder::class)->find($venue));
    }

    #[Test]
    public function a_chapel_does_not_take_the_cathedral_photos(): void
    {
        $this->fakeWikimedia([
            $this->item('Q3', 'Katedrála svätého Jána Krstiteľa', 0.05, image: 'Katedrala.jpg'),
        ]);

        $this->assertSame([], app(VenueImageFinder::class)->find($this->venue('Kaplnka sv. Jána Krstiteľa')));
    }

    #[Test]
    public function inflected_names_and_a_closer_item_win(): void
    {
        $this->fakeWikimedia([
            $this->item('Q10', 'Kostol svätého Františka Assiského', 0.3, image: 'Daleko.jpg'),
            $this->item('Q11', 'Kostol svätého Františka Assiského', 0.02, image: 'Blizko.jpg'),
        ]);

        $images = app(VenueImageFinder::class)->find($this->venue('kostol sv. Františka'));

        $this->assertSame('Q11', $images[0]['wikidata'] ?? null);
    }

    #[Test]
    public function settlements_addresses_and_placeholder_coordinates_are_skipped(): void
    {
        $this->fakeWikimedia([
            $this->item('Q4', 'Testovo', 0.1, image: 'Testovo.jpg', population: true),
        ]);

        $finder = app(VenueImageFinder::class);

        $this->assertSame([], $finder->find($this->venue('Testovo')));
        $this->assertSame([], $finder->find($this->venue('Ružová 4, Trnava')));
        $this->assertSame([], $finder->find($this->venue('Jakubovo námestie 4-5')));

        $nationwide = $this->venue('Kostol sv. Martina');
        $nationwide->forceFill(['latitude' => null, 'longitude' => null])->save();
        $this->assertSame([], $finder->find($nationwide->fresh()));
    }

    #[Test]
    public function venues_with_an_image_are_left_alone_and_misses_are_remembered(): void
    {
        $this->fakeWikimedia([]);

        $withImage = $this->venue('Kostol sv. Martina');
        $withImage->files()->create([
            'name' => 'vlastna.jpg', 'original_name' => 'vlastna.jpg', 'disk' => 'public', 'path' => 'x/vlastna.jpg', 'size' => 1, 'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'checksum' => 'x',
            'type' => FileType::IMAGE->value, 'is_primary' => true,
        ]);
        $miss = $this->venue('Kostol sv. Mikuláša');

        $this->artisan('app:venues-find-images', ['--sleep' => 0])
            ->expectsOutputToContain('#'.$miss->id)
            ->doesntExpectOutputToContain('#'.$withImage->id)
            ->assertSuccessful();

        $this->artisan('app:venues-find-images', ['--sleep' => 0])
            ->doesntExpectOutputToContain('#'.$miss->id)
            ->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(1, $withImage->files()->count());
    }

    #[Test]
    public function dry_run_stores_nothing(): void
    {
        $this->fakeWikimedia([
            $this->item('Q1', 'Kostol svätého Martina', 0.05, image: 'Kostol_sv_Martina.jpg'),
        ]);

        $venue = $this->venue('Kostol sv. Martina');

        $this->artisan('app:venues-find-images', ['--dry-run' => true, '--sleep' => 0])
            ->expectsOutputToContain('Q1')
            ->assertSuccessful();

        $this->assertSame(0, $venue->files()->count());
    }

    private function venue(string $name): Venue
    {
        return Venue::factory()->create([
            'name' => $name,
            'village_id' => (int) Municipality::query()->where('slug', 'trnava')->value('id'),
            'latitude' => 48.5,
            'longitude' => 19.5,
            'coordinates_source' => 'venue',
            'category' => null,
            'status' => ModelStatus::Published->value,
        ]);
    }

    /** @return array<int, array<string, mixed>> SPARQL riadky jedného objektu */
    private function item(string $id, string $label, float $distance, ?string $image = null, ?string $category = null, bool $population = false): array
    {
        $row = [
            'item' => ['value' => 'http://www.wikidata.org/entity/'.$id],
            'label' => ['value' => $label],
            'dist' => ['value' => (string) $distance],
        ];

        if ($image !== null) {
            $row['image'] = ['value' => 'http://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode($image)];
        }

        if ($category !== null) {
            $row['category'] = ['value' => $category];
        }

        if ($population) {
            $row['population'] = ['value' => '1200'];
        }

        return [$row];
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $items
     * @param  array<int, string>  $categoryFiles
     */
    private function fakeWikimedia(array $items, array $categoryFiles = []): void
    {
        Http::fake(function (Request $request) use ($items, $categoryFiles) {
            $url = $request->url();

            if (str_starts_with($url, 'https://query.wikidata.org/')) {
                return Http::response(['results' => ['bindings' => array_merge([], ...$items)]]);
            }

            if (str_starts_with($url, 'https://commons.wikimedia.org/') && ($request['list'] ?? null) === 'categorymembers') {
                return Http::response(['query' => ['categorymembers' => array_map(fn ($title) => ['title' => $title], $categoryFiles)]]);
            }

            if (str_starts_with($url, 'https://commons.wikimedia.org/')) {
                $pages = [];

                foreach (explode('|', (string) $request['titles']) as $i => $title) {
                    $title = str_replace('_', ' ', $title);
                    $file = rawurlencode(str_replace(' ', '_', substr($title, 5)));
                    $pages[(string) -($i + 1)] = [
                        'title' => $title,
                        'imageinfo' => [[
                            'mime' => str_ends_with($title, '.png') ? 'image/png' : 'image/jpeg',
                            'width' => 2000,
                            'thumburl' => 'https://upload.wikimedia.org/thumb/'.$file,
                            'descriptionurl' => 'https://commons.wikimedia.org/wiki/'.$file,
                            'extmetadata' => [
                                'Artist' => ['value' => '<a href="#">Ján Fotograf</a>'],
                                'LicenseShortName' => ['value' => 'CC BY-SA 4.0'],
                                'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0'],
                            ],
                        ]],
                    ];
                }

                return Http::response(['query' => ['pages' => $pages]]);
            }

            if (str_starts_with($url, 'https://upload.wikimedia.org/')) {
                // Iný obsah pre každý súbor, inak by ich checksum zlúčil.
                return Http::response('jpeg-'.md5($url), 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('', 404);
        });
    }
}
