<?php

namespace Tests\Feature\Posters;

use App\Models\Event;
use App\Models\PosterDraft;
use App\Models\User;
use App\Notifications\PosterDraftSaved;
use App\Services\OpenAI\Detector;
use App\Services\Posters\PosterAnalysisReport;
use App\Services\Posters\PosterExtraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tok „nahraj plagát" od anonymného nahratia po podujatie.
 *
 * OpenAI je tu vždy zamockované — testy nesmú volať platené API a odpoveď
 * modelu nie je deterministická. Overujeme zapojenie, nie kvalitu extrakcie.
 */
class PosterUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function detection(): array
    {
        return [
            'success' => true,
            'corrected_text' => 'Pozývame vás na letný koncert.',
            'event_payload' => [
                'title' => 'Letný koncert',
                'start_at' => '2026-08-21 18:00:00',
                'end_at' => '2026-08-21 20:00:00',
                'organizer' => ['name' => 'Mesto Skúšobné', 'street_and_number' => null, 'city' => 'Bratislava'],
                'venue' => ['name' => 'Kultúrny dom', 'street_and_number' => null, 'city' => 'Bratislava'],
                'email' => 'info@example.test',
                'phone' => null,
                'persons' => [],
            ],
            'organizer_canal' => ['name' => 'Mesto Skúšobné', 'existing' => null],
            'venue_detect' => null,
            'persons' => [],
        ];
    }

    private function mockDetector(): void
    {
        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromPoster')->andReturn($this->detection());
        $this->app->instance(Detector::class, $detector);
    }

    #[Test]
    public function guest_can_analyze_a_text_poster_without_an_account(): void
    {
        $this->mockDetector();

        $response = $this->postJson('/api/poster/analyze', [
            'text' => 'Mesto Skúšobné pozýva na letný koncert 21. augusta 2026 o 18:00 v Kultúrnom dome v Bratislave.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('draft.analysis.can_save', true)
            ->assertJsonPath('draft.suggestion.title', 'Letný koncert');

        $this->assertNotNull($response->json('draft.token'));
        $this->assertSame(1, PosterDraft::query()->count());
    }

    #[Test]
    public function analysis_reports_which_fields_are_missing(): void
    {
        $detection = $this->detection();
        $detection['event_payload']['venue'] = null;
        $detection['event_payload']['start_at'] = null;

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromPoster')->andReturn($detection);
        $this->app->instance(Detector::class, $detector);

        $response = $this->postJson('/api/poster/analyze', [
            'text' => 'Pozvánka bez termínu a miesta, len s názvom a organizátorom, aby prešla validáciou dĺžky.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('draft.analysis.can_save', false);

        $this->assertEqualsCanonicalizing(
            ['start_at', 'venue'],
            $response->json('draft.analysis.missing_required'),
        );
    }

    #[Test]
    public function description_falls_back_to_the_document_text_when_the_copywriter_returns_nothing(): void
    {
        // Copywriter je nespoľahlivý (dlhý vstup ho usekne na limite tokenov) a
        // zlyhá potichu. Kým report čítal len jeho výstup, hlásil „nenašli sme
        // popis" aj pri dokumente plnom textu — ktorý sa pri uložení aj tak
        // stal telom podujatia. Report musí ukazovať to, čo naozaj uložíme.
        $detection = $this->detection();
        $detection['corrected_text'] = null;

        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromPoster')->andReturn($detection);
        $this->app->instance(Detector::class, $detector);

        $text = 'Púť na Staré Hory sa koná od 5. do 7. augusta 2026. '
            . 'Začíname svätou omšou v Chrenovci, nocľah v Turčianskych Tepliciach.';

        $response = $this->postJson('/api/poster/analyze', ['text' => $text]);

        $response->assertStatus(201);

        $description = collect($response->json('draft.analysis.fields'))
            ->firstWhere('key', 'description');

        $this->assertNotSame('missing', $description['status']);
        $this->assertStringContainsString('Staré Hory', (string) $description['value']);

        // Formulár musí byť predvyplnený tým istým textom, inak by ho človek
        // prepisoval ručne napriek tomu, že ho už máme.
        $this->assertStringContainsString('Staré Hory', (string) $response->json('draft.description'));
    }

    #[Test]
    public function analysis_rejects_unsupported_file_types(): void
    {
        // Zámerne `post()`, nie `postJson()` — súbor musí ísť ako multipart.
        $response = $this->post(
            '/api/poster/analyze',
            ['file' => UploadedFile::fake()->create('plagat.exe', 10, 'application/octet-stream')],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422)->assertJsonValidationErrors('file');
    }

    #[Test]
    public function analysis_requires_a_file_or_text(): void
    {
        $this->postJson('/api/poster/analyze', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    #[Test]
    public function draft_is_not_readable_without_the_token(): void
    {
        $draft = $this->makeDraft();

        $this->getJson("/api/poster/drafts/{$draft->id}")->assertStatus(403);
        $this->getJson("/api/poster/drafts/{$draft->id}?token=nespravny")->assertStatus(404);
    }

    #[Test]
    public function remembering_a_draft_emails_a_link_back(): void
    {
        Notification::fake();

        $draft = $this->makeDraft();

        $this->postJson("/api/poster/drafts/{$draft->id}/remember", [
            'token' => 'tajny-token',
            'email' => 'organizator@example.test',
        ])->assertOk();

        $this->assertSame('organizator@example.test', $draft->fresh()->email);
        Notification::assertSentOnDemand(PosterDraftSaved::class);
    }

    #[Test]
    public function claiming_a_draft_creates_event_canal_and_venue(): void
    {
        Storage::fake('public');

        $draft = $this->makeDraft();

        // `unverified()` je tu podstatné: overenému účtu založí osobný kanál
        // UserObserver a vetva, ktorú tento test overuje (kanál podľa
        // organizátora z plagátu), by sa nikdy nespustila.
        $user = User::factory()->unverified()->create(['canal_id' => null]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/poster/drafts/{$draft->id}/claim", [
            'token' => 'tajny-token',
            'overrides' => ['title' => 'Letný koncert v parku'],
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $event = Event::query()->findOrFail($response->json('event_id'));

        $this->assertSame('Letný koncert v parku', $event->name);
        $this->assertSame((int) $user->id, (int) $event->user_id);
        $this->assertNotNull($event->venue_id);

        // Kanál nesie meno organizátora z plagátu a patrí tomu, kto plagát
        // nahral — bez vlastníctva by vlastné podujatie nesmel ani upraviť.
        $this->assertSame('Mesto Skúšobné', $event->canal->name);
        $this->assertTrue($user->fresh()->canInCanal((int) $event->canal_id, 'event.update'));
        $this->assertSame((int) $event->canal_id, (int) $user->fresh()->canal_id);
    }

    #[Test]
    public function claiming_reuses_the_canal_the_user_already_has(): void
    {
        Storage::fake('public');

        $draft = $this->makeDraft();

        // Overený účet už osobný kanál má (UserObserver). Claim mu nesmie
        // založiť druhý — inak by mal každý organizátor po prvom plagáte dva.
        $user = User::factory()->create();
        $existingCanalId = (int) $user->canals()->firstOrFail()->id;

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token']);

        $response->assertStatus(201);
        $this->assertSame($existingCanalId, (int) $response->json('canal_id'));
        $this->assertSame(1, $user->fresh()->canals()->count());
    }

    #[Test]
    public function a_scripted_description_is_stripped_before_it_reaches_the_event_body(): void
    {
        // `body` sa na verejnom detaile renderuje cez v-html, takže popis
        // z formulára je vstup od neprihláseného návštevníka priamo do DOM.
        Storage::fake('public');

        $draft = $this->makeDraft();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/poster/drafts/{$draft->id}/claim", [
            'token' => 'tajny-token',
            'overrides' => [
                'description' => '<p>Púť na Staré Hory</p><script>alert(1)</script><img src=x onerror=alert(1)>',
            ],
        ]);

        $response->assertStatus(201);

        $body = (string) Event::query()->findOrFail($response->json('event_id'))->body;

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringContainsString('Staré Hory', $body);
    }

    #[Test]
    public function a_plain_text_description_keeps_its_line_breaks(): void
    {
        // Harmonogram púte je riadkovaný text. Bez prevodu na odstavce by sa
        // v HTML zlial do jedného bloku — nové riadky sú tam len medzery.
        Storage::fake('public');

        $draft = $this->makeDraft();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/poster/drafts/{$draft->id}/claim", [
            'token' => 'tajny-token',
            'overrides' => ['description' => "STREDA\n6:30 zraz pri fare\n\nŠTVRTOK\nTrasa: Čremošné"],
        ]);

        $response->assertStatus(201);

        $body = (string) Event::query()->findOrFail($response->json('event_id'))->body;

        $this->assertStringContainsString('<p>', $body);
        $this->assertStringContainsString('<br>', $body);
    }

    #[Test]
    public function a_complete_poster_is_published_straight_away(): void
    {
        Storage::fake('public');

        $draft = $this->makeDraft();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token']);
        $response->assertStatus(201);

        $event = Event::query()->findOrFail($response->json('event_id'));

        $this->assertSame('published', $event->status->value);
        $this->assertNotNull($event->published_at);

        // Verejný zoznam pri podujatí ukazuje meno kanála, ale kanál sám je
        // po založení koncept — publikované podujatie by inak odkazovalo na
        // profil, ktorý sa nedá otvoriť.
        $this->assertSame('published', $event->canal->status->value);
    }

    #[Test]
    public function a_poster_without_a_date_stays_a_draft(): void
    {
        // Bez termínu by na portáli visel záznam, ktorý sa nedá zaradiť do
        // kalendára ani nájsť podľa dátumu — taký ostáva konceptom.
        Storage::fake('public');

        $detection = $this->detection();
        $detection['event_payload']['start_at'] = null;
        $detection['event_payload']['end_at'] = null;

        $draft = $this->makeDraft($detection);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token']);
        $response->assertStatus(201);

        $event = Event::query()->findOrFail($response->json('event_id'));

        $this->assertSame('draft', $event->status->value);
        $this->assertNull($event->published_at);
    }

    #[Test]
    public function a_poster_without_a_recognised_title_stays_a_draft(): void
    {
        // Náhradný názov „Nové podujatie" nesmie ísť von. Sprievodca pri ňom
        // hlási, že sa podujatie uložiť nedá — publikovať ho by si odporovalo.
        Storage::fake('public');

        $detection = $this->detection();
        $detection['event_payload']['title'] = null;

        $draft = $this->makeDraft($detection);
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token']);
        $response->assertStatus(201);

        $this->assertSame('draft', Event::query()->findOrFail($response->json('event_id'))->status->value);
    }

    #[Test]
    public function claiming_requires_authentication(): void
    {
        $draft = $this->makeDraft();

        $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token'])
            ->assertStatus(401);
    }

    #[Test]
    public function claiming_twice_does_not_create_a_second_event(): void
    {
        Storage::fake('public');

        $draft = $this->makeDraft();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $first = $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token']);
        $first->assertStatus(201);

        $second = $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token']);
        $second->assertOk()->assertJsonPath('already_claimed', true);

        $this->assertSame($first->json('event_id'), $second->json('event_id'));
        $this->assertSame(1, Event::query()->count());
    }

    #[Test]
    public function expired_draft_cannot_be_claimed(): void
    {
        $draft = $this->makeDraft();
        $draft->forceFill(['expires_at' => now()->subDay()])->save();

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->postJson("/api/poster/drafts/{$draft->id}/claim", ['token' => 'tajny-token'])
            ->assertStatus(410);
    }

    /** @param array<string, mixed>|null $detection */
    private function makeDraft(?array $detection = null): PosterDraft
    {
        $detection ??= $this->detection();

        $draft = new PosterDraft([
            'source_kind' => 'text',
            'extracted_text' => 'Letný koncert 21. augusta 2026 o 18:00.',
            'detection' => $detection,
            'analysis' => (new PosterAnalysisReport())->build(
                $detection,
                new PosterExtraction(text: 'Letný koncert', kind: 'text'),
            ),
            'expires_at' => now()->addDays(7),
        ]);

        $draft->token = PosterDraft::hashToken('tajny-token');
        $draft->save();

        return $draft;
    }
}
