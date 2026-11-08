<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalIdentityMode;
use App\Enums\FileType;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Services\Canals\CanalLogoFinder;
use App\Services\Canals\CanalWebsitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Logo kanála z jeho webu (CanalLogoFinder, app:canals-find-logos).
 */
class CanalLogoFinderTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> URL → HTML */
    public array $pages = [];

    /** @var array<string, array{body: string, mime: string}> URL → obrázok */
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
            public function __construct(private readonly CanalLogoFinderTest $test) {}

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
    public function json_ld_logo_beats_header_image_and_og_image(): void
    {
        $html = <<<'HTML'
            <html><head>
              <meta property="og:image" content="https://farnost.test/foto.jpg">
              <script type="application/ld+json">{"@graph":[{"@type":"Organization","logo":{"@type":"ImageObject","url":"https://farnost.test/ld-logo.png"}}]}</script>
            </head><body>
              <header><a class="site-logo" href="/"><img src="/img/hlavicka.png"></a></header>
              <footer><div class="partners"><img src="/partner-logo.png" alt="logo partnera"></div></footer>
            </body></html>
            HTML;

        $candidates = app(CanalLogoFinder::class)->candidates('https://farnost.test/', $html);

        $this->assertSame([
            ['url' => 'https://farnost.test/ld-logo.png', 'source' => CanalLogoFinder::SOURCE_JSON_LD],
            ['url' => 'https://farnost.test/img/hlavicka.png', 'source' => CanalLogoFinder::SOURCE_IMG],
            ['url' => 'https://farnost.test/foto.jpg', 'source' => CanalLogoFinder::SOURCE_OG_IMAGE],
        ], $candidates);
    }

    #[Test]
    public function svg_and_tiny_images_are_skipped_for_the_next_candidate(): void
    {
        $canal = $this->canal('https://farnost.test');
        $this->pages['https://farnost.test'] = '<header><img class="logo" src="/logo.svg"><img class="logo" src="/maly-logo.png"></header>'
            .'<link rel="apple-touch-icon" href="/touch.png">';
        $this->images['https://farnost.test/maly-logo.png'] = ['body' => $this->png(32, 16), 'mime' => 'image/png'];
        $this->images['https://farnost.test/touch.png'] = ['body' => $this->png(180, 180), 'mime' => 'image/png'];

        $found = app(CanalLogoFinder::class)->find($canal);

        $this->assertSame(CanalLogoFinder::SOURCE_TOUCH_ICON, $found['source']);
        $this->assertSame('https://farnost.test/touch.png', $found['url']);
    }

    #[Test]
    public function command_stores_the_logo_as_primary_image_on_a_square_canvas(): void
    {
        $canal = $this->canal('https://farnost.test');
        $this->pages['https://farnost.test'] = '<header><img src="/logo.png" alt="Logo farnosti"></header>';
        $this->images['https://farnost.test/logo.png'] = ['body' => $this->png(300, 100), 'mime' => 'image/png'];

        $this->artisan('app:canals-find-logos')->assertSuccessful();

        $file = $canal->images()->sole();
        $this->assertTrue($file->is_primary);
        $this->assertSame(FileType::IMAGE, $file->type);
        $this->assertSame('canal_website_logo', $file->meta['origin']);
        $this->assertSame('https://farnost.test/logo.png', $file->meta['source_url']);

        $size = getimagesizefromstring(Storage::disk($file->disk)->get($file->path));
        $this->assertSame([512, 512], [$size[0], $size[1]]);
        $this->assertTrue($canal->fresh()->has_primary_image);
    }

    #[Test]
    public function canals_with_an_image_shared_website_or_personal_identity_are_left_alone(): void
    {
        $withImage = $this->canal('https://ma-obrazok.test');
        $withImage->files()->create(['type' => FileType::IMAGE->value, 'name' => 'x', 'original_name' => 'x.png', 'size' => 1, 'mime_type' => 'image/png', 'extension' => 'png', 'checksum' => 'x', 'disk' => 'public', 'path' => 'x.png', 'is_primary' => true]);
        $sharedA = $this->canal('https://www.cirkev.test/zbor-a');
        $this->canal('https://cirkev.test/zbor-b');
        $personal = $this->canal('https://osobny.test', CanalIdentityMode::Personal);

        $finder = app(CanalLogoFinder::class);

        $this->assertSame('has_image', $finder->skipReason($withImage));
        $this->assertSame('shared_website', $finder->skipReason($sharedA));
        $this->assertSame('personal', $finder->skipReason($personal));
    }

    #[Test]
    public function a_miss_is_remembered_so_the_next_run_skips_the_canal(): void
    {
        $canal = $this->canal('https://bez-loga.test');
        $this->pages['https://bez-loga.test'] = '<p>Bez obrázkov</p>';

        $this->artisan('app:canals-find-logos')->expectsOutputToContain('nenájdené')->assertSuccessful();
        $this->assertTrue(Cache::has('canal-logos:miss:'.$canal->id));

        $this->pages['https://bez-loga.test'] = '<img src="/logo.png" class="logo">';
        $this->images['https://bez-loga.test/logo.png'] = ['body' => $this->png(200, 200), 'mime' => 'image/png'];

        $this->artisan('app:canals-find-logos')->assertSuccessful();
        $this->assertFalse($canal->images()->exists());
    }

    private function canal(string $website, CanalIdentityMode $identity = CanalIdentityMode::Organization): Canal
    {
        return Canal::factory()->active()->create([
            'registration_source' => RegistrationSource::IMPORT->value,
            'identity_mode' => $identity->value,
            'name' => 'Farnosť '.fake()->unique()->city(),
            'website' => $website,
            'claimed_at' => null,
        ]);
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 60, 120));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
