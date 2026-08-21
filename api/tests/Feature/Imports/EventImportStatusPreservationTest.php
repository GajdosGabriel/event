<?php

namespace Tests\Feature\Imports;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Import beží nad zoznamom, v ktorom prevažujú už skončené podujatia. Kým
 * podmienka „kompletnosti" vyžadovala status = published, archivované
 * podujatie ju nikdy nesplnilo a každý nočný beh ho znovu stiahol, prehnal AI
 * a vrátil medzi publikované — kým ho app:events-archive-finished o desať
 * minút znova nezhodil dole.
 */
class EventImportStatusPreservationTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_URL = 'https://www.ecav.sk/aktuality/pozvanky';
    private const DETAIL_URL = 'https://www.ecav.sk/aktuality/pozvanky/test-import-event';

    /**
     * Http::fake() sa pri opakovanom volaní nenahrádza, len pridá ďalší
     * callback — a odpovedá ten prvý, ktorý sedí. Obsah článku medzi behmi
     * importu preto meníme cez túto vlastnosť, nie novou registráciou.
     */
    private string $articleBody = '';

    private bool $sourceFaked = false;

    /**
     * ImportedVenueManager zakladá miesta ako koncept — pri ich vzniku ešte
     * nevie, či bude článok kompletný. Keď z neho vyjde publikované podujatie,
     * musí ísť von aj profil miesta a kanála.
     */
    #[Test]
    public function it_publishes_the_venue_and_canal_of_a_published_imported_event(): void
    {
        $event = $this->importOnce();

        $this->assertSame(ModelStatus::Published, $event->venue?->status);
        $this->assertSame(ModelStatus::Published, $event->canal?->status);
    }

    #[Test]
    public function it_skips_an_archived_event_instead_of_republishing_it(): void
    {
        $event = $this->importOnce();

        $this->artisan('app:events-archive-finished')->assertSuccessful();

        $this->assertSame(
            ModelStatus::Archived,
            $event->fresh()->status,
            'Podujatie so skončeným termínom má archivovať plánovaný príkaz.'
        );

        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source ' . self::LISTING_URL . ' -> imported: 0, updated: 0, skipped: 1, errors: 0')
            ->assertSuccessful();

        $this->assertSame(
            ModelStatus::Archived,
            $event->fresh()->status,
            'Import nesmie archivované podujatie vrátiť medzi publikované.'
        );
    }

    #[Test]
    public function it_keeps_an_unpublished_event_in_draft(): void
    {
        $event = $this->importOnce();

        // Zrušenie publikovania z dashboardu: status ide dole aj s published_at.
        $event->update(['status' => ModelStatus::Draft->value, 'published_at' => null]);

        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source ' . self::LISTING_URL . ' -> imported: 0, updated: 0, skipped: 1, errors: 0')
            ->assertSuccessful();

        $event->refresh();
        $this->assertSame(ModelStatus::Draft, $event->status);
        $this->assertNull($event->published_at, 'Ručne stiahnuté podujatie sa nesmie samo vrátiť na web.');
    }

    #[Test]
    public function forced_reimport_refreshes_content_but_leaves_the_status_alone(): void
    {
        $event = $this->importOnce();
        $event->update(['status' => ModelStatus::Archived->value]);
        $publishedAt = $event->fresh()->published_at;

        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL], '--pages' => 1, '--limit' => 1, '--force' => true])
            ->expectsOutput('Source ' . self::LISTING_URL . ' -> imported: 0, updated: 1, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event->refresh();
        $this->assertSame(ModelStatus::Archived, $event->status);
        $this->assertSame(
            $publishedAt?->format('Y-m-d H:i:s'),
            $event->published_at?->format('Y-m-d H:i:s'),
            'published_at je čas prvého publikovania, nie čas posledného behu importu.'
        );
    }

    #[Test]
    public function it_publishes_a_draft_once_the_missing_date_appears_in_the_article(): void
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->create()->assignRole('super-admin');

        // Prvý beh: článok bez termínu -> koncept, ktorý sa dá znovu spracovať.
        $body = 'Modlitebné spoločenstvo ECAV pozýva na výročnú konferenciu. Termín upresníme.';
        $this->fakeSource($body);

        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source ' . self::LISTING_URL . ' -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event = Event::query()->where('orginal_source', self::DETAIL_URL)->firstOrFail();
        $this->assertSame(ModelStatus::Draft, $event->status);
        $this->assertNull($event->start_at);

        // Druhý beh: do článku pribudol termín -> koncept sa má publikovať.
        $this->fakeSource('Modlitebné spoločenstvo ECAV pozýva na výročnú konferenciu v termíne 13. – 15. marca 2026.');

        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source ' . self::LISTING_URL . ' -> imported: 0, updated: 1, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event->refresh();
        $this->assertSame(ModelStatus::Published, $event->status);
        $this->assertNotNull($event->start_at);
        $this->assertNotNull($event->published_at);
    }

    private function importOnce(): Event
    {
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->create()->assignRole('super-admin');

        $this->fakeSource('Modlitebné spoločenstvo ECAV pozýva na výročnú konferenciu v termíne 13. – 15. marca 2026.');

        $this->artisan('app:import-event-sources', ['--url' => [self::LISTING_URL], '--pages' => 1, '--limit' => 1])
            ->expectsOutput('Source ' . self::LISTING_URL . ' -> imported: 1, updated: 0, skipped: 0, errors: 0')
            ->assertSuccessful();

        $event = Event::query()->where('orginal_source', self::DETAIL_URL)->firstOrFail();

        $this->assertSame(ModelStatus::Published, $event->status);
        $this->assertTrue($event->end_at?->isPast(), 'Fixture má termín v minulosti, aby ho archivácia zachytila.');

        return $event;
    }

    private function fakeSource(string $body): void
    {
        $this->articleBody = $body;

        if ($this->sourceFaked) {
            return;
        }

        $this->sourceFaked = true;

        Http::fake(function ($request) {
            $body = $this->articleBody;

            if ($request->url() === self::LISTING_URL) {
                return Http::response($this->listingHtml(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            if ($request->url() === self::DETAIL_URL) {
                return Http::response($this->detailHtml($body), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }

            return Http::response('', 404);
        });
    }

    private function listingHtml(): string
    {
        $detailUrl = self::DETAIL_URL;

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

    private function detailHtml(string $body): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="sk">
<body>
    <main id="content">
        <h1>35. konferencia Modlitebného spoločenstva</h1>
        <p>{$body}</p>
    </main>
</body>
</html>
HTML;
    }
}
