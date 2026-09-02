<?php

namespace Tests\Feature\Content;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\ContentReview;
use App\Models\User;
use App\Notifications\ContentReviewNotice;
use App\Services\Content\ContentReviewService;
use App\Services\OpenAI\ChatGPT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kontrola obsahu po zverejnení (App\Services\Content).
 *
 * Kanál tu zastupuje všetky typy — kód je pre miesto aj podujatie ten istý
 * (HasContentReview + ContentReviewService), líši sa len model.
 *
 * ChatGPT je podvrhnutý: skutočný chodí do OpenAI, takže by z testu urobil
 * platený a nedeterministický beh.
 */
class ContentReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email_verified_at' => now()]);

        config([
            'content_review.enabled' => true,
            'content_review.delay_minutes' => 15,
            'content_review.min_body_chars' => 120,
        ]);
    }

    /** Text dosť dlhý na to, aby prešiel hranicou `min_body_chars`. */
    private function longBody(string $suffix = ''): string
    {
        return '<p>Farnosť Belá je rímskokatolícka farnosť v okrese Žilina. Stará sa o duchovný '
            .'život v obci a okolitých osadách, spravuje farský kostol a organizuje podujatia '
            .'pre rodiny, mládež aj seniorov.'.$suffix.'</p>';
    }

    private function canal(string $status = ModelStatus::Published->value, ?string $body = null): Canal
    {
        $canal = Canal::factory()->create([
            'status' => $status,
            'municipality_id' => 1,
            'registration_source' => RegistrationSource::SELF,
            'body' => $body ?? $this->longBody(),
        ]);

        $canal->users()->attach($this->owner->id, [
            'is_owner' => true,
            'status' => ModelStatus::Published->value,
        ]);

        return $canal->fresh();
    }

    /**
     * Nahradí ChatGPT takým, ktorý vždy vráti daný posudok.
     *
     * @param  array<int, array{severity: string, mode: string, message: string, quote: string}>  $issues
     */
    private function fakeChatGpt(array $issues, int $score = 70): void
    {
        $fake = new class($issues, $score) extends ChatGPT
        {
            public int $calls = 0;

            /** @param array<int, array<string, string>> $issues */
            public function __construct(private array $issues, private int $score)
            {
                parent::__construct();
            }

            public function extractContentReview(string $kind, string $name, string $body, array $context = []): array
            {
                $this->calls++;

                return ['score' => $this->score, 'summary' => 'Zhrnutie posudku.', 'issues' => $this->issues];
            }
        };

        app()->instance(ChatGPT::class, $fake);
    }

    private function service(): ContentReviewService
    {
        return app(ContentReviewService::class);
    }

    #[Test]
    public function publishing_schedules_a_review_with_a_delay(): void
    {
        $canal = $this->canal();

        $review = $canal->contentReview()->first();

        $this->assertNotNull($review);
        $this->assertNotNull($review->due_at);
        // Odklad je slušnosť voči tomu, kto po zverejnení ešte dolaďuje preklepy.
        $this->assertTrue($review->due_at->greaterThan(now()->addMinutes(10)));
    }

    #[Test]
    public function a_draft_is_never_scheduled(): void
    {
        $canal = $this->canal(ModelStatus::Draft->value);

        $this->assertNull($canal->contentReview()->first());
    }

    #[Test]
    public function a_short_text_is_never_scheduled(): void
    {
        $canal = $this->canal(ModelStatus::Published->value, '<p>Krátko.</p>');

        $this->assertNull($canal->contentReview()->first());
    }

    #[Test]
    public function unpublishing_stops_a_pending_review(): void
    {
        $canal = $this->canal();

        $canal->update(['status' => ModelStatus::Draft->value]);

        // Riadok ostáva — nesie pamäť na to, že sme sa už raz ozvali.
        $this->assertNotNull($canal->contentReview()->first());
        $this->assertNull($canal->contentReview()->first()->due_at);
    }

    #[Test]
    public function saving_an_unrelated_field_does_not_reschedule(): void
    {
        $this->fakeChatGpt([]);

        $canal = $this->canal();
        $this->service()->run($canal->contentReview()->first());

        $canal->refresh()->update(['phone' => '+421900000000']);

        // Text sa nezmenil, takže sa ani znovu neposudzuje — inak by každé
        // uloženie kvôli inému poľu stálo jedno volanie OpenAI.
        $this->assertNull($canal->contentReview()->first()->due_at);
    }

    #[Test]
    public function changing_the_text_discards_the_old_verdict(): void
    {
        $this->fakeChatGpt([
            ['severity' => 'warning', 'mode' => 'grammar', 'message' => 'Chýba čiarka.', 'quote' => 'a to'],
        ]);

        $canal = $this->canal();
        $this->service()->run($canal->contentReview()->first());

        $this->assertNotNull($canal->contentReview()->first()->reviewed_at);

        $canal->refresh()->update(['body' => $this->longBody(' Doplnená veta o histórii.')]);

        $review = $canal->contentReview()->first();

        // Výhrady k predošlej verzii by po prepise mátali a skóre by klamalo.
        $this->assertNull($review->reviewed_at);
        $this->assertNull($review->issues);
        $this->assertNotNull($review->due_at);
    }

    #[Test]
    public function a_warning_notifies_the_owner(): void
    {
        Notification::fake();

        $this->fakeChatGpt([
            ['severity' => 'warning', 'mode' => 'grammar', 'message' => 'Chýba čiarka.', 'quote' => 'a to'],
        ]);

        $canal = $this->canal();
        $this->service()->run($canal->contentReview()->first());

        Notification::assertSentTo($this->owner, ContentReviewNotice::class);
        $this->assertNotNull($canal->contentReview()->first()->notified_at);
    }

    #[Test]
    public function a_mere_suggestion_does_not_notify(): void
    {
        Notification::fake();

        $this->fakeChatGpt([
            ['severity' => 'notice', 'mode' => 'expand', 'message' => 'Dalo by sa rozviesť.', 'quote' => ''],
        ]);

        $canal = $this->canal();
        $this->service()->run($canal->contentReview()->first());

        // E-mail za samotné „dalo by sa" je otravovanie — posudok sa uloží,
        // ale majiteľa nebudí.
        Notification::assertNothingSent();
        $this->assertNotNull($canal->contentReview()->first()->reviewed_at);
    }

    #[Test]
    public function a_clean_text_notifies_nobody(): void
    {
        Notification::fake();

        $this->fakeChatGpt([], 100);

        $canal = $this->canal();
        $this->service()->run($canal->contentReview()->first());

        Notification::assertNothingSent();
        $this->assertSame(100, $canal->contentReview()->first()->score);
    }

    #[Test]
    public function the_cooldown_prevents_a_second_email_about_the_same_record(): void
    {
        Notification::fake();

        $this->fakeChatGpt([
            ['severity' => 'warning', 'mode' => 'style', 'message' => 'Jeden dlhý blok.', 'quote' => ''],
        ]);

        $canal = $this->canal();
        $this->service()->run($canal->contentReview()->first());

        $canal->refresh()->update(['body' => $this->longBody(' Ďalšia veta o kostole.')]);
        $this->service()->run($canal->contentReview()->first());

        Notification::assertSentToTimes($this->owner, ContentReviewNotice::class, 1);
    }

    #[Test]
    public function the_email_links_to_the_form_with_the_modes_preselected(): void
    {
        $review = new ContentReview([
            'issues' => [
                ['severity' => 'warning', 'mode' => 'grammar', 'message' => 'Chýba čiarka.', 'quote' => ''],
                ['severity' => 'notice', 'mode' => 'expand', 'message' => 'Krátke.', 'quote' => ''],
            ],
        ]);

        // Poradie je z PromptContentReview::MODES, nie z poradia výskytu —
        // dve kontroly toho istého textu majú dať ten istý odkaz.
        $this->assertSame(['grammar', 'expand'], $review->suggestedModes());
    }

    #[Test]
    public function a_record_without_an_owner_is_reviewed_but_nobody_is_told(): void
    {
        Notification::fake();

        $this->fakeChatGpt([
            ['severity' => 'warning', 'mode' => 'grammar', 'message' => 'Chýba čiarka.', 'quote' => ''],
        ]);

        // Importovaný záznam z cudzieho zdroja — nemá komu sa ozvať.
        $canal = Canal::factory()->create([
            'status' => ModelStatus::Published->value,
            'municipality_id' => 1,
            'body' => $this->longBody(),
        ]);

        $this->service()->run($canal->contentReview()->first());

        Notification::assertNothingSent();
        // Posudok ostáva zapísaný — vidí ho admin.
        $this->assertNotNull($canal->contentReview()->first()->reviewed_at);
    }

    #[Test]
    public function disabling_the_feature_stops_scheduling(): void
    {
        config(['content_review.enabled' => false]);

        $canal = $this->canal();

        $this->assertNull($canal->contentReview()->first());
    }
}
