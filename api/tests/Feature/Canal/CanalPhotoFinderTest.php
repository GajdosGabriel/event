<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalIdentityMode;
use App\Enums\FileType;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Services\Canals\CanalPhotoFinder;
use App\Services\Canals\CanalWebsitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Typické fotky kanála z jeho webu do galérie (CanalPhotoFinder, app:canals-find-photos).
 */
class CanalPhotoFinderTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    public array $pages = [];

    /** @var array<string, array{body: string, mime: string}> */
    public array $images = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
        Queue::fake();
        Cache::flush();

        $test = $this;
        $this->app->instance(CanalWebsitePages::class, new class($test) extends CanalWebsitePages
        {
            public function __construct(private readonly CanalPhotoFinderTest $test) {}

            public function fetchPage(string $url): ?array
            {
                $body = $this->test->pages[rtrim($url, '/')] ?? null;

                return $body === null ? null : ['url' => rtrim($url, '/').'/', 'body' => $body];
            }

            public function fetchImage(string $url, int $maxBytes): ?array
            {
                $image = $this->test->images[$url] ?? null;

                return $image === null ? null : ['url' => $url] + $image;
            }
        });
    }

    #[Test]
    public function share_image_and_slider_come_before_content_and_chrome_is_ignored(): void
    {
        $html = <<<'HTML'
            <html><head><meta property="og:image" content="/zdielanie.jpg"></head><body>
              <header><img class="logo" src="/logo.png"><div class="hero-slider"><img src="/slide-1.jpg"></div></header>
              <nav><img src="/menu-foto.jpg"></nav>
              <main>
                <img src="/clanok.jpg" srcset="/clanok-300.jpg 300w, /clanok-1200.jpg 1200w">
                <img src="/ikona.jpg" width="32" height="32">
                <img src="/pozvanka-na-pust.jpg">
              </main>
              <footer><img src="/sponzor.jpg"></footer>
            </body></html>
            HTML;

        $candidates = app(CanalPhotoFinder::class)->candidates('https://farnost.test/', $html);

        $this->assertSame([
            ['url' => 'https://farnost.test/zdielanie.jpg', 'source' => 'og_image'],
            ['url' => 'https://farnost.test/slide-1.jpg', 'source' => 'hero'],
            ['url' => 'https://farnost.test/clanok-1200.jpg', 'source' => 'content'],
        ], $candidates);
    }

    #[Test]
    public function command_adds_photos_to_the_gallery_and_keeps_the_logo_primary(): void
    {
        $canal = $this->canal();
        $logo = $canal->files()->create([
            'type' => FileType::IMAGE->value, 'name' => 'logo', 'original_name' => 'logo.png', 'size' => 1,
            'mime_type' => 'image/png', 'extension' => 'png', 'checksum' => 'logo', 'disk' => 'public',
            'path' => 'logo.png', 'is_primary' => true, 'meta' => ['origin' => 'canal_website_logo'],
        ]);

        $this->pages['https://farnost.test'] = '<div class="slider"><img src="/a.jpg"><img src="/b.jpg"><img src="/uzky.jpg"><img src="/a-kopia.jpg"></div>';
        $this->images['https://farnost.test/a.jpg'] = ['body' => $this->jpeg(800, 600, 10), 'mime' => 'image/jpeg'];
        $this->images['https://farnost.test/b.jpg'] = ['body' => $this->jpeg(800, 600, 200), 'mime' => 'image/jpeg'];
        $this->images['https://farnost.test/uzky.jpg'] = ['body' => $this->jpeg(1600, 300, 90), 'mime' => 'image/jpeg'];
        $this->images['https://farnost.test/a-kopia.jpg'] = $this->images['https://farnost.test/a.jpg'];

        $this->artisan('app:canals-find-photos', ['--sleep' => 0])->assertSuccessful();

        $photos = $canal->images()->where('meta->origin', CanalPhotoFinder::ORIGIN)->get();
        $this->assertCount(2, $photos);
        $this->assertFalse($photos->contains('is_primary', true));
        $this->assertTrue($logo->fresh()->is_primary);

        // Druhý beh nič nepridá — kanál už fotky z webu má.
        $this->artisan('app:canals-find-photos', ['--sleep' => 0])->assertSuccessful();
        $this->assertSame(3, $canal->images()->count());
    }

    #[Test]
    public function canal_without_any_image_gets_the_first_photo_as_primary(): void
    {
        $canal = $this->canal();
        $this->pages['https://farnost.test'] = '<meta property="og:image" content="https://farnost.test/a.jpg">';
        $this->images['https://farnost.test/a.jpg'] = ['body' => $this->jpeg(800, 600, 10), 'mime' => 'image/jpeg'];

        $this->artisan('app:canals-find-photos', ['--sleep' => 0])->assertSuccessful();

        $this->assertTrue($canal->images()->sole()->is_primary);
    }

    #[Test]
    public function canal_with_its_own_image_is_left_alone(): void
    {
        $canal = $this->canal();
        $canal->files()->create([
            'type' => FileType::IMAGE->value, 'name' => 'vlastna', 'original_name' => 'v.jpg', 'size' => 1,
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'checksum' => 'v', 'disk' => 'public', 'path' => 'v.jpg', 'is_primary' => true,
        ]);
        $this->pages['https://farnost.test'] = '<meta property="og:image" content="https://farnost.test/a.jpg">';
        $this->images['https://farnost.test/a.jpg'] = ['body' => $this->jpeg(800, 600, 10), 'mime' => 'image/jpeg'];

        $this->artisan('app:canals-find-photos', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame(1, $canal->images()->count());
    }

    #[Test]
    public function public_profile_puts_the_logo_next_to_the_name_and_a_photo_into_the_hero(): void
    {
        $canal = $this->canal();
        $canal->update(['status' => \App\Enums\ModelStatus::Published->value, 'published_at' => now()]);
        $file = fn (string $name, bool $primary, ?string $origin) => $canal->files()->create([
            'type' => FileType::IMAGE->value, 'name' => $name, 'original_name' => $name.'.png', 'size' => 1,
            'mime_type' => 'image/png', 'extension' => 'png', 'checksum' => $name, 'disk' => 'public',
            'path' => 'canal/'.$name.'.png', 'is_primary' => $primary, 'meta' => $origin ? ['origin' => $origin] : null,
        ]);
        $file('logo', true, 'canal_website_logo');
        $file('foto', false, CanalPhotoFinder::ORIGIN);

        $response = $this->getJson('/api/canals/'.$canal->id)->assertOk();
        $this->assertStringContainsString('canal/logo.png', (string) $response->json('logo_image'));
        $this->assertStringContainsString('canal/foto.png', (string) $response->json('hero_image'));
        $this->assertSame(1, $response->json('gallery_count'));

        $this->getJson('/api/canals/'.$canal->id.'/files')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.name', 'foto');
    }

    private function canal(): Canal
    {
        return Canal::factory()->active()->create([
            'registration_source' => RegistrationSource::IMPORT->value,
            'identity_mode' => CanalIdentityMode::Organization->value,
            'name' => 'Farnosť '.fake()->unique()->city(),
            'website' => 'https://farnost.test',
            'claimed_at' => null,
        ]);
    }

    private function jpeg(int $width, int $height, int $shade): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, $shade, 120, 255 - $shade));
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }
}
