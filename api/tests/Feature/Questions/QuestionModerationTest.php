<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\QuestionBoard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class QuestionModerationTest extends EventSetupTest
{
    private function moderatedBoard(): QuestionBoard
    {
        $this->futureEvent->update([
            'status' => ModelStatus::Published,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        $board = $this->futureEvent->ensureQuestionBoard();
        $board->update(['moderation' => true]);

        return $board;
    }

    private function pendingQuestion(QuestionBoard $board): Question
    {
        return $board->questions()->create([
            'body' => 'Otázka čakajúca na schválenie',
            'author_hash' => str_repeat('a', 64),
            'status' => QuestionStatus::Pending,
        ]);
    }

    #[Test]
    public function slots_list_the_event_and_its_workshops(): void
    {
        $this->futureEvent->ticketTypes()->create(['name' => 'Bežná vstupenka', 'kind' => 'ticket', 'is_active' => true]);
        $this->futureEvent->ticketTypes()->create(['name' => 'Workshop o AI', 'kind' => 'workshop', 'is_active' => true]);

        $response = $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/question-boards")
            ->assertOk();

        // Bežný typ lístka sa medzi slotmi neobjaví — pýtať sa dá na program,
        // nie na cenovú hladinu.
        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.target_type', 'event')
            ->assertJsonPath('data.0.board', null)
            ->assertJsonPath('data.1.target_type', 'workshop')
            ->assertJsonPath('data.1.title', 'Workshop o AI');
    }

    #[Test]
    public function organizer_turns_the_board_on_and_gets_a_link(): void
    {
        $response = $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/question-boards", [
            'target_type' => 'event',
            'target_id' => $this->futureEvent->id,
        ])->assertStatus(201);

        $this->assertMatchesRegularExpression('/^[2-9A-Z]{5}-[2-9A-Z]{5}$/', $response->json('code'));
        $this->assertStringContainsString('/q/', $response->json('public_url'));
        $this->assertStringEndsWith('/stena', $response->json('wall_url'));
    }

    #[Test]
    public function a_regular_ticket_type_cannot_have_a_board(): void
    {
        $type = $this->futureEvent->ticketTypes()->create(['name' => 'Štandard', 'kind' => 'ticket', 'is_active' => true]);

        $this->postJson("/api/dashboard/events/{$this->futureEvent->id}/question-boards", [
            'target_type' => 'workshop',
            'target_id' => $type->id,
        ])->assertStatus(422);
    }

    #[Test]
    public function approving_a_question_publishes_it_and_bumps_the_counter(): void
    {
        $board = $this->moderatedBoard();
        $question = $this->pendingQuestion($board);

        $this->patchJson("/api/dashboard/questions/{$question->id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('status', 'published');

        $this->assertSame(1, (int) $board->refresh()->questions_count);

        // A späť do skrytého — počítadlo sa musí vrátiť.
        $this->patchJson("/api/dashboard/questions/{$question->id}", ['status' => 'hidden'])->assertOk();
        $this->assertSame(0, (int) $board->refresh()->questions_count);
    }

    #[Test]
    public function only_one_question_is_highlighted_at_a_time(): void
    {
        $board = $this->moderatedBoard();
        $first = $this->pendingQuestion($board);
        $second = $board->questions()->create([
            'body' => 'Druhá otázka',
            'author_hash' => str_repeat('b', 64),
            'status' => QuestionStatus::Published,
        ]);

        $this->patchJson("/api/dashboard/questions/{$first->id}", ['highlighted' => true])->assertOk();
        $this->patchJson("/api/dashboard/questions/{$second->id}", ['highlighted' => true])->assertOk();

        $this->assertNull($first->refresh()->highlighted_at);
        $this->assertNotNull($second->refresh()->highlighted_at);
    }

    #[Test]
    public function writing_an_answer_marks_the_question_answered(): void
    {
        $board = $this->moderatedBoard();
        $question = $this->pendingQuestion($board);

        $this->patchJson("/api/dashboard/questions/{$question->id}", [
            'answer_body' => 'Áno, záznam zverejníme do týždňa.',
        ])->assertOk();

        $question->refresh();
        $this->assertSame('Áno, záznam zverejníme do týždňa.', $question->answer_body);
        $this->assertNotNull($question->answered_at);
    }

    #[Test]
    public function moderation_list_shows_pending_and_hidden_too(): void
    {
        $board = $this->moderatedBoard();
        $this->pendingQuestion($board);
        $board->questions()->create([
            'body' => 'Skrytá',
            'author_hash' => str_repeat('c', 64),
            'status' => QuestionStatus::Hidden,
        ]);

        $this->getJson("/api/dashboard/question-boards/{$board->id}/questions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('counts.hidden', 1);
    }

    #[Test]
    public function rotating_the_token_kills_the_old_link(): void
    {
        $board = $this->moderatedBoard();
        $oldToken = $board->token;

        $this->postJson("/api/dashboard/question-boards/{$board->id}/rotate-token")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/q/{$oldToken}")->assertNotFound();
        $this->getJson('/api/q/' . $board->refresh()->token)->assertOk();
    }

    #[Test]
    public function a_foreign_organizer_cannot_moderate(): void
    {
        $board = $this->moderatedBoard();
        $question = $this->pendingQuestion($board);

        // cudziEvent patrí inému kanálu — jeho nástenka nesmie byť dosiahnuteľná
        // z tohto účtu, a naopak.
        $foreignBoard = $this->cudziEvent->ensureQuestionBoard();

        $this->getJson("/api/dashboard/question-boards/{$foreignBoard->id}/questions")->assertForbidden();
        $this->putJson("/api/dashboard/question-boards/{$foreignBoard->id}", ['is_open' => false])->assertForbidden();

        // Vlastnú nástenku moderovať smie.
        $this->patchJson("/api/dashboard/questions/{$question->id}", ['status' => 'published'])->assertOk();
    }

    #[Test]
    public function settings_can_be_changed(): void
    {
        $board = $this->moderatedBoard();

        $this->putJson("/api/dashboard/question-boards/{$board->id}", [
            'show_questions' => false,
            'allow_upvotes' => false,
            'intro' => 'Na čo sa chcete opýtať prednášajúceho?',
            'closes_at' => null,
        ])
            ->assertOk()
            ->assertJsonPath('show_questions', false)
            ->assertJsonPath('allow_upvotes', false)
            ->assertJsonPath('intro', 'Na čo sa chcete opýtať prednášajúceho?')
            ->assertJsonPath('closes_at', null);
    }
}
