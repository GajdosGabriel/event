<?php

namespace Tests\Feature\Seo;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Zodpovedané otázky publika ako `FAQPage`.
 *
 * Toto je dôvod, prečo sa Q&A oplatí vystaviť na verejnom detaile a nenechať ju
 * len za QR kódom v sále: „je tam parkovanie?" je presne to, čo ľudia píšu do
 * vyhľadávača. Bez prerenderu by crawler nevidel nič — SPA sa renderuje až
 * v prehliadači.
 */
class FaqPrerenderTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->event = Event::factory()->future()->create([
            'name' => 'Koncert v katedrále',
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subMonth(),
            'user_id' => $user->id,
        ]);
    }

    private function prerender()
    {
        return $this->get('/api/prerender?path='.urlencode(PublicUrl::eventPath($this->event)));
    }

    #[Test]
    public function answered_question_reaches_the_html_and_the_structured_data(): void
    {
        $board = $this->event->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Je pri budove parkovanie?',
            'author_hash' => 'a',
            'status' => QuestionStatus::Published,
            'answer_body' => 'Áno, vo dvore je desať miest.',
            'answered_at' => now(),
        ]);

        $response = $this->prerender()->assertOk();

        // V tele stránky — crawler musí vidieť text, nielen schému.
        $response->assertSee('Je pri budove parkovanie?', false);
        $response->assertSee('Áno, vo dvore je desať miest.', false);

        // A v JSON-LD, lebo len tak to Google zobrazí rozbaliteľne vo výsledku.
        $response->assertSee('FAQPage', false);
        $response->assertSee('acceptedAnswer', false);
    }

    #[Test]
    public function unanswered_question_stays_out(): void
    {
        $board = $this->event->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Bude sa dať platiť kartou?',
            'author_hash' => 'b',
            'status' => QuestionStatus::Published,
        ]);

        $response = $this->prerender()->assertOk();

        // `Question` v schéme vyžaduje `acceptedAnswer`, takže otvorená otázka
        // by bola neplatný záznam — a pre návštevníka z vyhľadávača bezcenná.
        $response->assertDontSee('Bude sa dať platiť kartou?', false);
        $response->assertDontSee('FAQPage', false);
    }

    #[Test]
    public function question_awaiting_moderation_stays_out(): void
    {
        $board = $this->event->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Neschválená otázka',
            'author_hash' => 'c',
            'status' => QuestionStatus::Pending,
            'answer_body' => 'Neschválená odpoveď',
            'answered_at' => now(),
        ]);

        $this->prerender()->assertOk()->assertDontSee('Neschválená otázka', false);
    }

    #[Test]
    public function event_without_a_board_renders_no_faq_section(): void
    {
        // Nástenka sa zakladá lenivo — takto vyzerá drvivá väčšina katalógu.
        $this->prerender()->assertOk()->assertDontSee('FAQPage', false);
    }
}
