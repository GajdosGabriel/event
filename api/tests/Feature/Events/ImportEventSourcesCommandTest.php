<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;

use App\Models\Canal;
use App\Models\Event;
use App\Enums\FileType;
use App\Models\User;
use App\Models\Venue;
use App\Services\Imports\VyveskaRssService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportEventSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_ecav_event_creates_system_owned_canal_and_updates_without_duplicates(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.ecav.sk/aktuality/pozvanky';
        $detailUrl = 'https://www.ecav.sk/aktuality/pozvanky/test-import-event';
        $imageUrl = 'https://www.ecav.sk/media/test-image.jpg';
        $detailBody = 'Modlitebné spoločenstvo ECAV pozýva na výročnú konferenciu v termíne 13. – 15. marca 2026. Uzávierka prihlášok: 8. marec 2026.';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $imageUrl, &$detailBody) {
            if ($request->url() === $listingUrl) {
                return Http::response($this->listingHtml($detailUrl), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $detailUrl) {
                return Http::response($this->detailHtml($detailBody, $imageUrl), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $imageUrl) {
                return Http::response('fake-image-binary', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.ecav.sk/aktuality/pozvanky -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $canal = Canal::query()->where('website', 'https://www.ecav.sk')->first();
        $this->assertNotNull($canal);
        $this->assertSame('Modlitebné spoločenstvo ECAV', $canal->name);

        $this->assertDatabaseHas('canal_user', [
            'canal_id' => $canal->id,
            'user_id' => $superAdmin->id,
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
        ]);

        $venue = Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $canal->id))
            ->where('slug', 'cele-slovensko')
            ->first();
        $this->assertNotNull($venue);

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($canal->id, $event->canal_id);
        $this->assertSame($venue->id, $event->venue_id);
        $this->assertSame($superAdmin->id, $event->user_id);
        $this->assertSame('2026-03-12 23:00:00', $event->start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-15 22:59:59', $event->end_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-08 22:59:59', $event->registration_deadline_at?->format('Y-m-d H:i:s'));
        $this->assertSame('external_source', $event->meta['import']['source'] ?? null);
        $this->assertSame('https://www.ecav.sk', $event->meta['import']['source_origin'] ?? null);
        $this->assertCount(1, $event->files);

        $detailBody = 'Modlitebné spoločenstvo ECAV pozýva na výročnú konferenciu v termíne 13. – 15. marca 2026. Uzávierka prihlášok: 8. marec 2026. Aktualizovaný popis programu.';

        // A complete event is skipped on re-import, so overwriting it takes --force.
        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.ecav.sk/aktuality/pozvanky -> imported: 0, updated: 0, skipped: 1, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 0, updated: 0, skipped: 1, errors: 0')
            ->assertSuccessful();

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1, '--force' => true])
            ->expectsOutput('Source https://www.ecav.sk/aktuality/pozvanky -> imported: 0, updated: 1, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 0, updated: 1, skipped: 0, errors: 0')
            ->assertSuccessful();

        $this->assertSame(1, Event::query()->where('orginal_source', $detailUrl)->count());
        $this->assertSame(1, Canal::query()->where('website', 'https://www.ecav.sk')->count());
        $this->assertSame(1, Venue::query()
            ->whereHas('canals', fn ($query) => $query->where('canals.id', $canal->id))
            ->where('slug', 'cele-slovensko')
            ->count());

        $event->refresh();
        $this->assertStringContainsString('Aktualizovaný popis programu.', (string) $event->body);
        $this->assertStringContainsString('<h2>Odkazy</h2>', (string) $event->body);
        $this->assertStringContainsString('<li><a href="https://www.modlitby.sk/registracia">Prihláška</a></li>', (string) $event->body);
        $this->assertCount(1, $event->files);
    }

    #[Test]
    public function it_imports_ecav_event_attachment_as_file(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.ecav.sk/aktuality/pozvanky';
        $detailUrl = 'https://www.ecav.sk/aktuality/pozvanky/viii-skolska-konferencia-hodnoty-vo-vzdelavani';
        $imageUrl = 'https://www.ecav.sk/rails/active_storage/blobs/proxy/example/skolska-konferencia-FB.jpg';
        $attachmentUrl = 'https://www.ecav.sk/rails/active_storage/blobs/example/8_%C5%A0KOLSK%C3%81_KONFERENCIA_ECAV_POPRAD_INFO_1.pdf';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $imageUrl, $attachmentUrl) {
            if ($request->url() === $listingUrl) {
                return Http::response($this->listingHtml($detailUrl), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $detailUrl) {
                return Http::response($this->ecavDetailHtmlWithAttachment($imageUrl, $attachmentUrl), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $imageUrl) {
                return Http::response('fake-image-binary', 200, ['Content-Type' => 'image/jpeg']);
            }

            if ($request->url() === $attachmentUrl) {
                return Http::response('%PDF-1.4 fake pdf binary', 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.ecav.sk/aktuality/pozvanky -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($superAdmin->id, $event->user_id);
        $this->assertCount(2, $event->files);

        $imageFile = $event->files->firstWhere('type', FileType::IMAGE);
        $this->assertNotNull($imageFile);
        $this->assertSame($event->name, $imageFile->name);
        $this->assertSame($imageUrl, $imageFile->meta['source_url'] ?? null);

        $attachmentFile = $event->files->firstWhere('type', FileType::FILE);
        $this->assertNotNull($attachmentFile);
        $this->assertSame($event->name, $attachmentFile->name);
        $this->assertSame($attachmentUrl, $attachmentFile->meta['source_url'] ?? null);
        $this->assertSame('8___KOLSK___KONFERENCIA_ECAV_POPRAD_INFO_1.pdf', $attachmentFile->original_name);
        $this->assertStringContainsString('<h2>Odkazy</h2>', (string) $event->body);
        $this->assertStringContainsString('<li><a href="https://www.edumiscentrum.sk/skolska-konferencia-2023/">edumiscentrum.sk</a></li>', (string) $event->body);
    }

    #[Test]
    public function it_prefers_ecav_blob_image_and_does_not_import_representation_as_duplicate_image(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.ecav.sk/aktuality/pozvanky';
        $detailUrl = 'https://www.ecav.sk/aktuality/pozvanky/skola-cirkevnych-hudobnikov-2025-2028';
        $representationUrl = 'https://www.ecav.sk/rails/active_storage/representations/proxy/blob-token/variant-token/skola.png';
        $blobUrl = 'https://www.ecav.sk/rails/active_storage/blobs/proxy/blob-token/skola.png';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $representationUrl, $blobUrl) {
            if ($request->url() === $listingUrl) {
                return Http::response($this->listingHtml($detailUrl), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $detailUrl) {
                return Http::response($this->ecavDetailHtmlWithRepresentationAndBlob($representationUrl, $blobUrl), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $blobUrl) {
                return Http::response('fake-full-image-binary', 200, ['Content-Type' => 'image/png']);
            }

            if ($request->url() === $representationUrl) {
                return Http::response('fake-small-image-binary', 200, ['Content-Type' => 'image/webp']);
            }

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.ecav.sk/aktuality/pozvanky -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($superAdmin->id, $event->user_id);
        $this->assertCount(1, $event->files);

        $imageFile = $event->files->firstWhere('type', FileType::IMAGE);
        $this->assertNotNull($imageFile);
        $this->assertSame($blobUrl, $imageFile->meta['source_url'] ?? null);
        $this->assertSame([$blobUrl], $event->meta['import']['image_urls'] ?? []);
        $this->assertSame([], $event->meta['import']['attachments'] ?? []);
    }

    #[Test]
    public function it_imports_tkkbs_event_from_search_results(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.tkkbs.sk/search.php?rstext=pozvanka&rskde=tsl';
        $detailUrl = 'https://www.tkkbs.sk/view.php?cisloclanku=20260408017';
        $imageUrl = 'https://www.tkkbs.sk/galeria/images/1766911178/1775635886.jpg';
        $logoUrl = 'https://www.tkkbs.sk/image/tkkbs/tkkbs_logo.gif';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $imageUrl, $logoUrl) {
            if ($request->url() === $listingUrl) {
                return Http::response($this->tkkbsListingHtml(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $detailUrl) {
                return Http::response($this->tkkbsDetailHtml($imageUrl, true), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === $imageUrl) {
                return Http::response('fake-image-binary', 200, ['Content-Type' => 'image/jpeg']);
            }

            if ($request->url() === $logoUrl) {
                return Http::response('fake-logo-binary', 200, ['Content-Type' => 'image/gif']);
            }

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.tkkbs.sk/search.php?rstext=pozvanka&rskde=tsl -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $canal = Canal::query()->where('website', 'https://www.tkkbs.sk')->first();
        $this->assertNotNull($canal);
        // The "(TK KBS)" dateline names the news agency, not the organizer, so with AI
        // detection off the canal falls back to the source host.
        $this->assertSame('tkkbs.sk', $canal->name);

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($canal->id, $event->canal_id);
        $this->assertSame($superAdmin->id, $event->user_id);
        $this->assertStringContainsString('Komunita rehole premonštrátov v Košiciach pozýva', (string) $event->body);
        $this->assertSame(
            '2026-04-08 08:00:00',
            $event->meta['import']['published_at_source'] !== null
                ? \Carbon\Carbon::parse($event->meta['import']['published_at_source'])->format('Y-m-d H:i:s')
                : null
        );
        $this->assertCount(1, $event->files);
        $this->assertSame([$imageUrl], $event->meta['import']['image_urls'] ?? []);

        $event->files()->create([
            'name' => $event->name,
            'original_name' => 'tkkbs_logo.gif',
            'extension' => 'gif',
            'size' => 123,
            'mime_type' => 'image/gif',
            'disk' => 'public',
            'path' => 'event/' . $event->id . '/image/legacy-tkkbs-logo.gif',
            'type' => FileType::IMAGE->value,
            'is_primary' => false,
            'meta' => [
                'article_url' => $detailUrl,
                'source_url' => $logoUrl,
            ],
        ]);

        $this->assertCount(2, $event->fresh()->files);

        // The event is complete by now, so pruning the stale logo takes --force.
        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1, '--force' => true])
            ->expectsOutput('Source https://www.tkkbs.sk/search.php?rstext=pozvanka&rskde=tsl -> imported: 0, updated: 1, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 0, updated: 1, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event->refresh();
        $this->assertCount(1, $event->files);
        $this->assertSame([$imageUrl], $event->meta['import']['image_urls'] ?? []);
        $this->assertSame(
            [$imageUrl],
            $event->files->pluck('meta.source_url')->filter()->values()->all()
        );
    }

    #[Test]
    public function it_imports_tkkbs_event_with_windows_1250_diacritics_correctly(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.tkkbs.sk/search.php?rstext=pozvanka&rskde=tsl';
        $detailUrl = 'https://www.tkkbs.sk/view.php?cisloclanku=20260408099';
        $imageUrl = 'https://www.tkkbs.sk/galeria/images/1766911178/1775635999.jpg';
        $title = 'Muži opäť spoja sily na pešej púti: Zo Senice zamieria k bazilike v Šaštíne';
        $body = 'Senica 8. apríla 2026 09:30 (TK KBS) Muži opäť spoja sily na pešej púti: Zo Senice zamieria k bazilike v Šaštíne.';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $imageUrl, $title, $body) {
            if ($request->url() === $listingUrl) {
                return Http::response(
                    $this->encodeWindows1250Html($this->tkkbsListingHtmlWithTitle($detailUrl, $title)),
                    200,
                    ['Content-Type' => 'text/html']
                );
            }

            if ($request->url() === $detailUrl) {
                return Http::response(
                    $this->encodeWindows1250Html($this->tkkbsDetailHtmlWithContent($imageUrl, $title, $body)),
                    200,
                    ['Content-Type' => 'text/html']
                );
            }

            if ($request->url() === $imageUrl) {
                return Http::response('fake-image-binary', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.tkkbs.sk/search.php?rstext=pozvanka&rskde=tsl -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($title, $event->name);
        $this->assertStringContainsString('Muži opäť spoja sily na pešej púti', (string) $event->body);
        $this->assertStringContainsString('bazilike v Šaštíne', (string) $event->body);
        $this->assertSame($superAdmin->id, $event->user_id);
        $this->assertSame(
            '2026-04-08 07:30:00',
            $event->meta['import']['published_at_source'] !== null
                ? \Carbon\Carbon::parse($event->meta['import']['published_at_source'])->format('Y-m-d H:i:s')
                : null
        );
    }

    #[Test]
    public function it_imports_vyveska_event_from_latest_listing(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.vyveska.sk/zoznam-podujati/najnovsie.html';
        $detailUrl = 'https://www.vyveska.sk/evanjelium-v-osadach-su-romovia-buducnostou-cirkvi.html';
        $imageUrl = 'https://www.vyveska.sk/images/cache/details/events/28552-evanjelium-v-osadach-su-romovia-buducnostou-cirkvi.jpeg';
        $attachmentUrl = 'https://www.vyveska.sk/subor.html?file=%2Fevents%2F28551-vecer-chval-a-adoracia-s-modlitbami-za-uzdravenie-a-oslobodenie-s-pomazanim-olejom-sv-charbela.pdf';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $imageUrl, $attachmentUrl) {
            if ($request->url() === $listingUrl) {
                return Http::response($this->vyveskaListingHtml($detailUrl), 200);
            }

            if ($request->url() === $detailUrl) {
                return Http::response($this->vyveskaDetailHtml($imageUrl, $attachmentUrl), 200);
            }

            if ($request->url() === $imageUrl) {
                return Http::response('fake-image-binary', 200, ['Content-Type' => 'image/jpeg']);
            }

			if ($request->url() === $attachmentUrl) {
				return Http::response('%PDF-1.4 fake pdf binary', 200, ['Content-Type' => 'application/pdf']);
			}

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.vyveska.sk/zoznam-podujati/najnovsie.html -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $canal = Canal::query()->where('website', 'https://www.vyveska.sk')->first();
        $this->assertNotNull($canal);
        $this->assertSame('vyveska.sk', $canal->name);

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($canal->id, $event->canal_id);
        $this->assertSame($superAdmin->id, $event->user_id);
        // Body is the .vv-prose description only; the info box (date/venue) and
        // the trailing "Ďalšie podujatia" related-event cards are chrome.
        $this->assertStringContainsString('rómskych komunitách', (string) $event->body);
        $this->assertStringNotContainsString('Ďalšie podujatia', (string) $event->body);
        $this->assertStringNotContainsString('Iné podujatie', (string) $event->body);
        $this->assertStringContainsString('<h2>Odkazy</h2>', (string) $event->body);
        $this->assertStringContainsString('<li><a href="https://www.inviton.eu/e-21047/vy-ste-svetlo-sveta-banska-bystrica">INVITON</a></li>', (string) $event->body);
        $this->assertSame('2026-04-10 15:00:00', $event->start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-10 16:30:00', $event->end_at?->format('Y-m-d H:i:s'));
        $this->assertCount(2, $event->files);

        $imageFile = $event->files->firstWhere('type', FileType::IMAGE);
        $this->assertNotNull($imageFile);
        $this->assertSame($imageUrl, $imageFile->meta['source_url'] ?? null);

        $attachmentFile = $event->files->firstWhere('type', FileType::FILE);
        $this->assertNotNull($attachmentFile);
        $this->assertSame($attachmentUrl, $attachmentFile->meta['source_url'] ?? null);
        $this->assertSame('vecer-chval-a-adoracia-s-modlitbami-za-uzdravenie-a-oslobodenie-s-pomazanim-olejom-sv-charbela.pdf', $attachmentFile->original_name);
    }

    #[Test]
    public function it_ignores_vyveska_static_pages_and_imports_only_event_links_from_listing(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.vyveska.sk/zoznam-podujati/najnovsie.html';
        $detailUrl = 'https://www.vyveska.sk/vecer-chval-a-adoracia-s-pomazanim-olejom-sv-charbela/';
        $imageUrl = 'https://www.vyveska.sk/images/cache/details/events/28551-vecer-chval.jpeg';
        $attachmentUrl = 'https://www.vyveska.sk/subor.html?file=%2Fevents%2F28551-program.pdf';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $imageUrl, $attachmentUrl) {
            if ($request->url() === $listingUrl) {
                return Http::response($this->vyveskaListingHtmlWithStaticPages($detailUrl), 200);
            }

            if (in_array($request->url(), [
                'https://www.vyveska.sk/login.html',
                'https://www.vyveska.sk/o-vyveske.html',
                'https://www.vyveska.sk/chcem-pomoct.html',
                'https://www.vyveska.sk/odkazy.html',
            ], true)) {
                return Http::response($this->vyveskaStaticPageHtml(), 200);
            }

            if ($request->url() === $detailUrl) {
                return Http::response($this->vyveskaDetailHtml($imageUrl, $attachmentUrl), 200);
            }

            if ($request->url() === $imageUrl) {
                return Http::response('fake-image-binary', 200, ['Content-Type' => 'image/jpeg']);
            }

            if ($request->url() === $attachmentUrl) {
                return Http::response('%PDF-1.4 fake pdf binary', 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.vyveska.sk/zoznam-podujati/najnovsie.html -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($superAdmin->id, $event->user_id);
        $this->assertSame($detailUrl, $event->orginal_source);

        $this->assertDatabaseMissing('events', [
            'orginal_source' => 'https://www.vyveska.sk/login.html',
        ]);
        $this->assertDatabaseMissing('events', [
            'orginal_source' => 'https://www.vyveska.sk/o-vyveske.html',
        ]);
        $this->assertDatabaseMissing('events', [
            'orginal_source' => 'https://www.vyveska.sk/chcem-pomoct.html',
        ]);
        $this->assertDatabaseMissing('events', [
            'orginal_source' => 'https://www.vyveska.sk/odkazy.html',
        ]);
    }

    #[Test]
    public function it_uses_vyveska_rss_dates_when_detail_page_date_is_missing(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $listingUrl = 'https://www.vyveska.sk/zoznam-podujati/najnovsie.html';
        $detailUrl = 'https://www.vyveska.sk/vecer-chval-a-adoracia-s-pomazanim-olejom-sv-charbela.html';
        $rssUrl = 'https://www.vyveska.sk/rss.xml';
        $imageUrl = 'https://www.vyveska.sk/images/cache/details/events/28551-vecer-chval.jpeg';
        $attachmentUrl = 'https://www.vyveska.sk/subor.html?file=%2Fevents%2F28551-program.pdf';

        Http::fake(function ($request) use ($listingUrl, $detailUrl, $rssUrl, $imageUrl, $attachmentUrl) {
            if ($request->url() === $listingUrl) {
                return Http::response($this->vyveskaListingHtml($detailUrl), 200);
            }

            if ($request->url() === $detailUrl) {
                return Http::response($this->vyveskaDetailHtmlWithoutDate($imageUrl, $attachmentUrl), 200);
            }

            if ($request->url() === $rssUrl) {
                return Http::response($this->vyveskaRssXml($detailUrl), 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
            }

            if ($request->url() === $imageUrl) {
                return Http::response('fake-image-binary', 200, ['Content-Type' => 'image/jpeg']);
            }

            if ($request->url() === $attachmentUrl) {
                return Http::response('%PDF-1.4 fake pdf binary', 200, ['Content-Type' => 'application/pdf']);
            }

            return Http::response('', 404);
        });

        $this->artisan('app:import-event-sources', ['--url' => [$listingUrl], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source https://www.vyveska.sk/zoznam-podujati/najnovsie.html -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->expectsOutput('Event import summary -> processed: 1, imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event = Event::query()->where('orginal_source', $detailUrl)->first();
        $this->assertNotNull($event);
        $this->assertSame($superAdmin->id, $event->user_id);
        $this->assertSame('2026-04-14 15:45:00', $event->start_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-14 17:45:00', $event->end_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-07 14:38:06', $event->meta['import']['published_at_source'] !== null ? \Carbon\Carbon::parse($event->meta['import']['published_at_source'])->format('Y-m-d H:i:s') : null);
    }

    #[Test]
    public function it_parses_vyveska_rss_item_by_canonical_detail_url(): void
    {
        Http::fake([
            'https://www.vyveska.sk/rss.xml' => Http::response(
                $this->vyveskaRssXml('https://www.vyveska.sk/28551/vecer-chval-a-adoracia-s-modlitbami-za-uzdravenie-a-oslobodenie-s-pomazanim-olejom-sv-charbela.html'),
                200,
                ['Content-Type' => 'application/rss+xml; charset=UTF-8']
            ),
        ]);

        $item = app(VyveskaRssService::class)->findByUrl('https://www.vyveska.sk/28551/vecer-chval-a-adoracia-s-modlitbami-za-uzdravenie-a-oslobodenie-s-pomazanim-olejom-sv-charbela.html');

        $this->assertNotNull($item);
        $this->assertSame('2026-04-14 15:45:00', $item['start_at']?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-14 17:45:00', $item['end_at']?->format('Y-m-d H:i:s'));
    }

    private function listingHtml(string $detailUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<body>
    <main>
        <h1>Pozvánky</h1>
        <a href="{$detailUrl}">Test import event</a>
    </main>
</body>
</html>
HTML;
    }

    private function detailHtml(string $body, string $imageUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<body>
    <main id="content">
        <h1>35. konferencia Modlitebného spoločenstva</h1>
        <div>13.02.26</div>
        <p>{$body}</p>
        <a href="https://www.modlitby.sk/registracia">Prihláška</a>
        <img src="{$imageUrl}" alt="cover">
    </main>
</body>
</html>
HTML;
    }

    private function ecavDetailHtmlWithAttachment(string $imageUrl, string $attachmentUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<body>
    <main id="content">
        <h1>VIII. školská konferencia: Hodnoty vo vzdelávaní</h1>
        <div>11.09.23</div>
        <a href="{$imageUrl}"><img src="{$imageUrl}" alt="VIII. školská konferencia: Hodnoty vo vzdelávaní"></a>
        <p>Školský výbor ECAV, EMC ECAV a Asociácia evanjelických škôl Slovenska pozývajú na VIII. školskú konferenciu. Podujatie sa koná v Hoteli Satel v Poprade, v termíne 5.-6.10.2023.</p>
        <p>Viac informácií a prihlasovanie: <a href="https://www.edumiscentrum.sk/skolska-konferencia-2023/">https://www.edumiscentrum.sk/skolska-konferencia-2023/</a></p>
        <p>PROGRAM: <a class="fr-file" href="{$attachmentUrl}" target="_blank">8_ŠKOLSKÁ_KONFERENCIA_ECAV_POPRAD_INFO_1.pdf</a></p>
    </main>
</body>
</html>
HTML;
    }

    private function ecavDetailHtmlWithRepresentationAndBlob(string $representationUrl, string $blobUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<body>
    <main id="content">
        <h1>ŠKOLA CIRKEVNÝCH HUDOBNÍKOV 2025 – 2028</h1>
        <div>26.09.24</div>
        <a href="{$blobUrl}"><img src="{$representationUrl}" alt="ŠKOLA CIRKEVNÝCH HUDOBNÍKOV 2025 – 2028"></a>
        <p>Výbor cirkevnej hudby a hymnológie a EMC ECAV otvárajú školu cirkevných hudobníkov.</p>
    </main>
</body>
</html>
HTML;
    }

    private function tkkbsListingHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="sk">
<body>
    <a href="view.php?cisloclanku=20260408017">V Košiciach prijme kňazskú vysviacku premonštrát Jakub Ronald Gedeon</a>
    <a href="search.php?rstext=pozvanka&rsautor=nic&rstema=nic&rskde=tsl&rsvelikost2=jr&rskolik=15&rskolikata=2">2</a>
</body>
</html>
HTML;
    }

    private function tkkbsListingHtmlWithTitle(string $detailUrl, string $title): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=windows-1250">
</head>
<body>
    <a href="{$detailUrl}">{$title}</a>
</body>
</html>
HTML;
    }

    private function tkkbsDetailHtml(string $imageUrl, bool $includeLogo = false): string
    {
        $logo = $includeLogo
            ? '<img src="/image/tkkbs/tkkbs_logo.gif" border="0" alt="TK KBS">'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head>
    <title>V Košiciach prijme kňazskú vysviacku premonštrát Jakub Ronald Gedeon</title>
</head>
<body>
    {$logo}
    <div class="imageilu"><span class="clatext"><img src="{$imageUrl}" width="620"></span></div>
    <span class="clatext"><p>Košice 8. apríla 2026 10:00 (TK KBS) Komunita rehole premonštrátov v Košiciach pozýva na slávnosť kňazskej vysviacky svojho spolubrata.</p><p>Slávnosť sa začne o 10:00 v premonštrátskom Kostole Najsvätejšej Trojice v Košiciach.</p></span>
</body>
</html>
HTML;
    }

    private function tkkbsDetailHtmlWithContent(string $imageUrl, string $title, string $body): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head>
    <title>{$title}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=windows-1250">
</head>
<body>
    <div class="imageilu"><span class="clatext"><img src="{$imageUrl}" width="620"></span></div>
    <span class="clatext"><p>{$body}</p></span>
</body>
</html>
HTML;
    }

    private function encodeWindows1250Html(string $html): string
    {
        return iconv('UTF-8', 'Windows-1250//TRANSLIT', $html) ?: $html;
    }

    private function vyveskaListingHtml(string $detailUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head><meta charset="utf-8"></head>
<body>
    <main id="main" class="main">
        <a href="https://www.vyveska.sk/zoznam-podujati/najoblubenejsie/">Najobľúbenejšie</a>
        <article class="relative border-b border-line py-[18px]">
            <div class="flex gap-4">
                <a href="{$detailUrl}" class="shrink-0"><img src="https://www.vyveska.sk/thumb.jpg" alt=""></a>
                <div class="min-w-0 flex-1 pr-8">
                    <span class="text-xs text-muted">10.04.2026 · 17:00</span>
                    <h4 class="mt-1.5 font-extrabold"><a href="{$detailUrl}" class="underline">Evanjelium v osadách: Sú Rómovia budúcnosťou Cirkvi?</a></h4>
                </div>
            </div>
        </article>
    </main>
</body>
</html>
HTML;
    }

    private function vyveskaListingHtmlWithStaticPages(string $detailUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head><meta charset="utf-8"></head>
<body>
    <header>
        <a href="https://www.vyveska.sk/login.html">Prihlásenie do systému</a>
        <a href="https://www.vyveska.sk/o-vyveske.html">Dve 2% pre Vývesku</a>
        <a href="https://www.vyveska.sk/chcem-pomoct.html">Vaša propagácia</a>
        <a href="https://www.vyveska.sk/odkazy.html">Partneri</a>
    </header>
    <main id="main" class="main">
        <article class="relative border-b border-line py-[18px]">
            <div class="flex gap-4">
                <div class="min-w-0 flex-1 pr-8">
                    <h4 class="mt-1.5 font-extrabold"><a href="{$detailUrl}" class="underline">Večer chvál a adorácia s modlitbami za uzdravenie a oslobodenie</a></h4>
                </div>
            </div>
        </article>
    </main>
</body>
</html>
HTML;
    }

    private function vyveskaDetailHtml(string $imageUrl, string $attachmentUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <title>Evanjelium v osadách: Sú Rómovia budúcnosťou Cirkvi? - Výveska.sk</title>
</head>
<body class="wp-singular podujatie-template-default single single-podujat">
    <div id="app">
        <main id="main" class="main">
            <div class="wrap">
                <article>
                    <img src="{$imageUrl}" alt="Evanjelium v osadách" class="h-[220px] w-full rounded-2xl object-cover">
                    <div class="mt-5 flex items-center justify-between">
                        <span class="font-extrabold">Piatok</span>
                        <span class="font-extrabold text-brand">10.04.2026</span>
                    </div>
                    <h1 class="mt-1 font-extrabold">Evanjelium v osadách: Sú Rómovia budúcnosťou Cirkvi?</h1>
                    <div class="mt-4 rounded-xl bg-sky p-4">
                        <p><span class="text-muted">Kedy:</span> <span class="font-extrabold">17:00 - 18:30</span></p>
                        <p class="flex items-center gap-1.5"><span class="text-muted">Kde:</span> <img src="https://www.vyveska.sk/icon-pin.svg" alt=""> <span class="font-extrabold">Banská Bystrica, Horná 21 (Evanjelický spolok, 1.poschodie) · Banskobystrický</span></p>
                        <p><span class="text-muted">Organizátor:</span> <span class="font-extrabold">Evanjelický spolok</span></p>
                        <p><span class="text-muted">Kategórie:</span> <a href="https://www.vyveska.sk/kategoria/duchovne/" class="text-brand">Duchovné</a></p>
                    </div>
                    <div class="vv-prose mt-6">
                        <p>V mnohých rómskych komunitách predovšetkým na východnom Slovensku už roky funguje množstvo kresťanských misií.</p>
                        <p>Po diskusii bude nasledovať koncert rómskej gospelovej kapely F6.</p>
                        <p>Diskusiu budete môcť sledovať aj online. Vstupenky na <a href="https://www.inviton.eu/e-21047/vy-ste-svetlo-sveta-banska-bystrica">INVITON</a>.</p>
                        <p>Príloha: <a href="{$attachmentUrl}">vecer-chval-a-adoracia-s-modlitbami-za-uzdravenie-a-oslobodenie-s-pomazanim-olejom-sv-charbela.pdf</a></p>
                    </div>
                    <h2 class="mt-10 border-b border-line pb-3 font-extrabold">Ďalšie podujatia</h2>
                    <article class="relative border-b border-line py-[18px]">
                        <div class="flex gap-4">
                            <a href="https://www.vyveska.sk/ine-podujatie/" class="shrink-0"><img src="https://www.vyveska.sk/related-thumb.jpg" alt=""></a>
                            <div class="min-w-0 flex-1 pr-8">
                                <h4 class="font-extrabold"><a href="https://www.vyveska.sk/ine-podujatie/" class="underline">Iné podujatie</a></h4>
                            </div>
                        </div>
                    </article>
                </article>
            </div>
        </main>
    </div>
</body>
</html>
HTML;
    }

    private function vyveskaDetailHtmlWithoutDate(string $imageUrl, string $attachmentUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <title>Večer chvál a adorácia s modlitbami za uzdravenie a oslobodenie - Výveska.sk</title>
</head>
<body class="wp-singular podujatie-template-default single single-podujat">
    <div id="app">
        <main id="main" class="main">
            <div class="wrap">
                <article>
                    <img src="{$imageUrl}" alt="Večer chvál" class="h-[220px] w-full rounded-2xl object-cover">
                    <h1 class="mt-1 font-extrabold">Večer chvál a adorácia s modlitbami za uzdravenie a oslobodenie</h1>
                    <div class="mt-4 rounded-xl bg-sky p-4">
                        <p class="flex items-center gap-1.5"><span class="text-muted">Kde:</span> <img src="https://www.vyveska.sk/icon-pin.svg" alt=""> <span class="font-extrabold">Sabinov</span></p>
                    </div>
                    <div class="vv-prose mt-6">
                        <p>Večer chvál s modlitbami za uzdravenie a oslobodenie.</p>
                        <p>Príloha: <a href="{$attachmentUrl}">program.pdf</a></p>
                    </div>
                </article>
            </div>
        </main>
    </div>
</body>
</html>
HTML;
    }

    private function vyveskaRssXml(string $detailUrl): string
    {
        $rssLink = $detailUrl . '?utm_source=vyveska.sk&utm_content=simple&utm_medium=rss';
                $escapedRssLink = htmlspecialchars($rssLink, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Vyveska</title>
    <item>
      <title>Večer chvál a adorácia s modlitbami za uzdravenie a oslobodenie</title>
      <description><![CDATA[ Večer chvál s modlitbami za uzdravenie a oslobodenie. (14.4.2026 - 17:45 - 19:45) ]]></description>
      <pubDate>Tue, 07 Apr 2026 14:38:06 +0200</pubDate>
            <link>{$escapedRssLink}</link>
            <guid>{$escapedRssLink}</guid>
      <category><![CDATA[ Matice Slovenskej, Sabinov (14.4.2026 - 17:45 - 19:45) ]]></category>
    </item>
  </channel>
</rss>
XML;
    }

    private function vyveskaStaticPageHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="sk">
<body>
    <div id="content-body">
        <h1>Statická stránka</h1>
        <p>Toto nie je podujatie.</p>
    </div>
</body>
</html>
HTML;
    }
}

