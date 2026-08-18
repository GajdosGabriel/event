<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class QuestionBoardPublicTest extends EventSetupTest
{
    #[Test]
    public function board_is_readable_by_its_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
        $board = $this->futureEvent->ensureQuestionBoard();

        $this->getJson("/api/q/{$board->token}")
            ->assertOk()
            ->assertJsonPath('title', $this->futureEvent->name)
            ->assertJsonPath('event_name', null)
            // Predvolené okno je „dve hodiny pred začiatkom" — podujatie o mesiac
            // teda nástenku otvorenú ešte nemá, hoci `is_open` je zapnuté.
            ->assertJsonPath('open', false);
    }

    #[Test]
    public function board_is_open_during_the_event(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        $board = $this->futureEvent->ensureQuestionBoard();

        $this->getJson("/api/q/{$board->token}")
            ->assertOk()
            ->assertJsonPath('open', true);
    }

    #[Test]
    public function closed_board_reports_itself_closed(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        $board = $this->futureEvent->ensureQuestionBoard();
        $board->update(['is_open' => false]);

        // Zavretá nástenka sa dá stále prečítať — odkaz z plátna nesmie skončiť
        // 404-kou, len sa už nedá pýtať.
        $this->getJson("/api/q/{$board->token}")
            ->assertOk()
            ->assertJsonPath('open', false);
    }

    #[Test]
    public function token_is_case_insensitive_and_ignores_the_display_dash(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
        $board = $this->futureEvent->ensureQuestionBoard();

        $typed = strtolower(substr($board->token, 0, 5) . '-' . substr($board->token, 5));

        $this->getJson("/api/q/{$typed}")->assertOk();
    }

    #[Test]
    public function unknown_token_is_not_found(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/q/ZZZZZZZZZZ')->assertNotFound();
        $this->getJson('/api/q/short')->assertNotFound();
    }

    #[Test]
    public function draft_event_has_no_public_board(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Draft]);
        $board = $this->futureEvent->ensureQuestionBoard();

        $this->getJson("/api/q/{$board->token}")->assertNotFound();
    }

    #[Test]
    public function questions_are_hidden_when_the_board_does_not_show_them(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
        $board = $this->futureEvent->ensureQuestionBoard();
        $board->update(['show_questions' => false]);
        $board->questions()->create([
            'body' => 'Kedy bude prestávka?',
            'author_hash' => str_repeat('a', 64),
            'status' => QuestionStatus::Published,
        ]);

        $this->getJson("/api/q/{$board->token}")
            ->assertOk()
            ->assertJsonPath('show_questions', false)
            ->assertJsonCount(0, 'questions');
    }

    #[Test]
    public function pending_questions_stay_out_of_the_public_list(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
        $board = $this->futureEvent->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Zverejnená otázka',
            'author_hash' => str_repeat('a', 64),
            'status' => QuestionStatus::Published,
        ]);
        $board->questions()->create([
            'body' => 'Čaká na schválenie',
            'author_hash' => str_repeat('b', 64),
            'status' => QuestionStatus::Pending,
        ]);
        $board->questions()->create([
            'body' => 'Skrytá otázka',
            'author_hash' => str_repeat('c', 64),
            'status' => QuestionStatus::Hidden,
        ]);

        $this->getJson("/api/q/{$board->token}")
            ->assertOk()
            ->assertJsonCount(1, 'questions')
            ->assertJsonPath('questions.0.body', 'Zverejnená otázka');
    }

    #[Test]
    public function stream_reports_no_change_for_an_unchanged_board(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
        $board = $this->futureEvent->ensureQuestionBoard();

        $state = $this->getJson("/api/q/{$board->token}")->json('v');

        $this->getJson("/api/q/{$board->token}/stream?v={$state}")
            ->assertOk()
            ->assertJsonPath('changed', false);

        $board->questions()->create([
            'body' => 'Nová otázka',
            'author_hash' => str_repeat('d', 64),
            'status' => QuestionStatus::Published,
        ]);

        $this->getJson("/api/q/{$board->token}/stream?v={$state}")
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonCount(1, 'questions');
    }

    #[Test]
    public function workshop_board_carries_the_event_name_above_the_workshop(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
        $workshop = $this->futureEvent->ticketTypes()->create([
            'name' => 'Ako na AI v malej firme',
            'kind' => 'workshop',
            'is_active' => true,
        ]);

        $board = $workshop->ensureQuestionBoard();

        $this->getJson("/api/q/{$board->token}")
            ->assertOk()
            ->assertJsonPath('title', 'Ako na AI v malej firme')
            ->assertJsonPath('event_name', $this->futureEvent->name);
    }
}
