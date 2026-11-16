<?php

namespace Tests\Feature\Imports;

use App\Enums\FileType;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Services\Imports\ImportPosterReader;
use App\Services\OpenAI\ChatGPT;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kalendár podujatí ECAV (ecav.sk/public/events) — zoznam so stránkovaním
 * a detail s tabuľkou údajov od organizátora.
 */
class EcavCalendarImportTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_URL = 'https://www.ecav.sk/public/events';

    private const PAGE_2_URL = 'https://www.ecav.sk/public/events?page=2';

    private const BLOB = 'https://www.ecav.sk/rails/active_storage/blobs/proxy/eyJfcmFpbHMiOnsiZGF0YSI6MjYzNzc1fX0--aaa/plagat%20(1).jpg';

    private const BODY_COPY = 'https://ecav.sk/rails/active_storage/blobs/proxy/eyJfcmFpbHMiOnsiZGF0YSI6MjYzNzc0fX0--bbb/plagat.jpg';

    private const REPRESENTATION = 'https://www.ecav.sk/rails/active_storage/representations/proxy/eyJfcmFpbHMiOnsiZGF0YSI6MjYzNzc1fX0--aaa/variant/plagat%20(1).jpg';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePublicDns();
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->create()->assignRole('super-admin');
    }

    #[Test]
    public function it_imports_all_pages_of_the_ecav_event_calendar(): void
    {
        $requested = [];

        Http::fake(function ($request) use (&$requested) {
            $url = $request->url();
            $requested[] = $url;

            return match ($url) {
                self::LISTING_URL => Http::response($this->listingHtml(['/na-slovicko-o-m-r-stefanikovi'], '?page=2'), 200, ['Content-Type' => 'text/html; charset=utf-8']),
                self::PAGE_2_URL => Http::response($this->listingHtml(['/dobrovolnicka-pastoracna-sluzba'], null), 200, ['Content-Type' => 'text/html; charset=utf-8']),
                'https://www.ecav.sk/na-slovicko-o-m-r-stefanikovi' => Http::response($this->detailHtml(
                    'Na slovíčko o M. R. Štefánikovi',
                    ['Od' => '19.11.2026 - 18:00', 'Do' => '19.11.2026 - 19:00', 'Mesto' => 'Kežmarok', 'Ulica' => 'Ev. lýceum', 'Organizátor' => 'CZ ECAV Kežmarok'],
                    '',
                    withImages: true,
                ), 200, ['Content-Type' => 'text/html; charset=utf-8']),
                'https://www.ecav.sk/dobrovolnicka-pastoracna-sluzba' => Http::response($this->detailHtml(
                    'Dobrovoľnícka pastoračná služba',
                    ['Od' => '01.10.2026 - 00:00', 'Do' => '31.05.2027 - 00:00', 'Mesto' => 'Martin', 'Ulica' => '', 'Organizátor' => 'EMC ECAV'],
                    'https://www.edumiscentrum.sk/dps',
                    withImages: false,
                ), 200, ['Content-Type' => 'text/html; charset=utf-8']),
                self::BLOB, self::BODY_COPY, self::REPRESENTATION => Http::response($this->fakeImage('jpeg', $url), 200, ['Content-Type' => 'image/jpeg']),
                default => Http::response('', 404),
            };
        });

        // Bez --pages: kalendár sa prejde celý aj pri globálnom max_pages = 1.
        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL]])
            ->expectsOutput('Source '.self::LISTING_URL.' -> imported: 2, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $this->assertContains(self::PAGE_2_URL, $requested);

        $talk = Event::query()->where('orginal_source', 'https://www.ecav.sk/na-slovicko-o-m-r-stefanikovi')->firstOrFail();
        $this->assertSame('Na slovíčko o M. R. Štefánikovi', $talk->name);
        // 18:00 a 19:00 bratislavského času (CET) v UTC.
        $this->assertSame('2026-11-19 17:00:00', $talk->start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-19 18:00:00', $talk->end_at?->format('Y-m-d H:i:s'));
        $this->assertSame('CZ ECAV Kežmarok', Canal::query()->findOrFail($talk->canal_id)->name);
        $this->assertStringContainsString('Srdečne pozývame', (string) $talk->body);
        $this->assertStringNotContainsString('Seniorát', (string) $talk->body);
        $this->assertSame('https://www.ecav.sk', $talk->meta['import']['source_origin'] ?? null);

        // Hlavný obrázok (og:image) raz; náhľad aj kópia toho istého plagátu v texte nie.
        $this->assertSame([self::BLOB], $talk->meta['import']['image_urls'] ?? null);
        $this->assertCount(1, $talk->files->where('type', FileType::IMAGE));

        $course = Event::query()->where('orginal_source', 'https://www.ecav.sk/dobrovolnicka-pastoracna-sluzba')->firstOrFail();
        // „00:00" = bez času: začiatok je deň, koniec celý posledný deň.
        $this->assertSame('2026-09-30 22:00:00', $course->start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2027-05-31 21:59:59', $course->end_at?->format('Y-m-d H:i:s'));
        $this->assertSame('EMC ECAV', Canal::query()->findOrFail($course->canal_id)->name);
        $this->assertSame('https://www.edumiscentrum.sk/dps', $course->website);
        $this->assertSame([], $course->meta['import']['image_urls'] ?? null);
    }

    #[Test]
    public function it_reads_the_poster_of_a_short_article_once(): void
    {
        config()->set('services.imports.read_posters', true);

        $chatGpt = \Mockery::mock(ChatGPT::class);
        $chatGpt->shouldReceive('extractDataFromPoster')
            ->once()
            ->withArgs(fn (string $text, array $images) => $text === '' && str_starts_with($images[0] ?? '', 'data:image/jpeg;base64,'))
            ->andReturn(['poster_text' => "Prednáša Mgr. Ján Novák\nVstup voľný"]);
        $this->app->instance(ChatGPT::class, $chatGpt);

        Http::fake(function ($request) {
            return match ($request->url()) {
                self::LISTING_URL => Http::response($this->listingHtml(['/na-slovicko-o-m-r-stefanikovi'], null), 200, ['Content-Type' => 'text/html; charset=utf-8']),
                'https://www.ecav.sk/na-slovicko-o-m-r-stefanikovi' => Http::response($this->detailHtml(
                    'Na slovíčko o M. R. Štefánikovi',
                    ['Od' => '19.11.2026 - 18:00', 'Do' => '19.11.2026 - 19:00', 'Mesto' => 'Kežmarok', 'Ulica' => 'Ev. lýceum', 'Organizátor' => 'CZ ECAV Kežmarok'],
                    '',
                    withImages: true,
                ), 200, ['Content-Type' => 'text/html; charset=utf-8']),
                self::BLOB, self::BODY_COPY, self::REPRESENTATION => Http::response($this->fakeImage('jpeg', $request->url()), 200, ['Content-Type' => 'application/octet-stream']),
                default => Http::response('', 404),
            };
        });

        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL]])->assertSuccessful();

        $event = Event::query()->where('orginal_source', 'https://www.ecav.sk/na-slovicko-o-m-r-stefanikovi')->firstOrFail();
        $this->assertStringContainsString('<p>Prednáša Mgr. Ján Novák<br> Vstup voľný</p>', (string) $event->body);
        $this->assertSame(self::BLOB, $event->meta['import']['poster_image_url'] ?? null);
        $this->assertStringContainsString('Vstup voľný', (string) ($event->meta['raw_text'] ?? '').(string) ($event->meta['import']['poster_text'] ?? ''));

        // Opakovaný beh prevezme prepis z meta — mock povolí len jedno volanie.
        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL], '--force' => true])->assertSuccessful();
        $this->assertStringContainsString('Prednáša Mgr. Ján Novák', (string) $event->fresh()->body);
    }

    #[Test]
    public function it_does_not_read_the_poster_of_a_long_article(): void
    {
        config()->set('services.imports.read_posters', true);

        $reader = new ImportPosterReader;
        $this->assertTrue($reader->shouldRead('Srdečne pozývame.'));
        $this->assertFalse($reader->shouldRead(str_repeat('Program podujatia. ', 40)));

        config()->set('services.imports.read_posters', false);
        $this->assertFalse($reader->shouldRead('Srdečne pozývame.'));
    }

    /**
     * @param  array<int, string>  $detailPaths
     */
    private function listingHtml(array $detailPaths, ?string $nextPage): string
    {
        $cards = '';
        foreach ($detailPaths as $path) {
            $cards .= <<<HTML
<div class="card mb-1 event-card" style="border-left-color: green">
<div class="row no-gutters">
<div class="col-auto"><a href="{$path}"><img src="https://www.ecav.sk/rails/active_storage/representations/proxy/x/y/thumb.jpg"></a></div>
<div class="col"><div class="card-block"><div class="card-header p-2">
<h5 class="d-flex flex-nowrap mb-0">
<a class="text-truncate" href="{$path}">18:00 | Podujatie</a>
<div class="ml-auto"><a target="_blank" href="https://www.example.org/organizator">https://www.example.org/organizator</a></div>
</h5>
</div></div></div>
</div>
</div>
HTML;
        }

        $pager = $nextPage !== null
            ? '<a class="btn btn-outline-secondary float-right" href="'.$nextPage.'">Novšie</a>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html><head><title>Zoznam podujatí</title></head>
<body>
<header><a href="/aktuality">Aktuality</a><a href="/kontaktne-udaje">Kontakt</a></header>
<section id="content"><div class="container">
<h4 class="mb-1 mt-4 border-bottom">Štvrtok 19.11.2026</h4>
{$cards}
{$pager}
</div></section>
</body></html>
HTML;
    }

    /**
     * @param  array<string, string>  $facts
     */
    private function detailHtml(string $title, array $facts, string $website, bool $withImages): string
    {
        $ogImage = $withImages ? '<meta content="'.self::BLOB.'" property="og:image">' : '';
        $entryImage = $withImages
            ? '<div class="entry-image"><a data-lightbox="image" href="'.parse_url(self::BLOB, PHP_URL_PATH).'"><img class="image-fade" src="'.self::REPRESENTATION.'"></a></div>'
            : '';
        $bodyImage = $withImages
            ? '<div class="fr-view"><br><img src="'.self::BODY_COPY.'" class="fr-fic fr-dib"></div>'
            : '<div class="fr-view"><p>Kurz pre laických pracovníkov.</p></div>';

        $rows = '';
        foreach ($facts as $label => $value) {
            $rows .= "<tr><th>{$label}</th><td>{$value}</td></tr>\n";
        }

        return <<<HTML
<!DOCTYPE html>
<html><head><title>{$title}</title>{$ogImage}</head>
<body>
<header><a href="/aktuality">Aktuality</a></header>
<section id="content"><div class="content-wrap"><div class="container clearfix">
<div class="single-post nobottommargin">
<div class="entry-title"><h1>{$title}</h1></div>
{$entryImage}
<div class="entry clearfix"><ul class="entry-meta clearfix">
<li><i class="icon-calendar3"></i> 19.11.26</li>
<li><i class="icon-user"></i> {$facts['Organizátor']}</li>
</ul></div>
<div class="entry-content notopmargin"><p>
<p class="lead">Srdečne pozývame.</p>
{$bodyImage}
</p></div>
</div>
<div class="table-responsive py-3"><table class="table">
{$rows}<tr><th>Webstránka</th><td><a target="_blank" href="{$website}">{$website}</a></td></tr>
<tr><th>Seniorát</th><td>Tatranský seniorát ECAV na Slovensku</td></tr>
<tr><th>Kategória</th><td>vzdelávanie</td></tr>
</table></div>
</div></div></section>
</body></html>
HTML;
    }
}
