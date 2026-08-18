<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\QuestionBoard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class QuestionVoteTest extends EventSetupTest
{
    private const VOTER = 'b8f1c2d3e4a5b6c7d8e9f0a1';

    private QuestionBoard $board;

    private function board(): QuestionBoard
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        return $this->board = $this->futureEvent->ensureQuestionBoard();
    }

    private function question(QuestionStatus $status = QuestionStatus::Published): Question
    {
        return $this->board->questions()->create([
            'body' => 'Bude záznam z prednášky?',
            'author_hash' => str_repeat('a', 64),
            'status' => $status,
        ]);
    }

    #[Test]
    public function a_visitor_can_vote_once(): void
    {
        $board = $this->board();
        $question = $this->question();

        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER])
            ->assertOk()
            ->assertJsonPath('upvotes_count', 1);

        // Druhý klik z toho istého prehliadača počítadlo nezvýši — bráni tomu
        // unikátny index, nie len kontrola v kóde.
        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER])
            ->assertOk()
            ->assertJsonPath('upvotes_count', 1);

        $this->assertDatabaseCount('question_votes', 1);
    }

    #[Test]
    public function a_vote_can_be_taken_back(): void
    {
        $board = $this->board();
        $question = $this->question();

        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER]);

        $this->deleteJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER])
            ->assertOk()
            ->assertJsonPath('upvotes_count', 0);

        // Odobranie hlasu, ktorý neexistuje, nesmie ísť pod nulu.
        $this->deleteJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER])
            ->assertOk()
            ->assertJsonPath('upvotes_count', 0);
    }

    #[Test]
    public function two_visitors_count_separately(): void
    {
        $board = $this->board();
        $question = $this->question();

        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER]);
        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => 'iny-prehliadac-token-1234']);

        $this->assertSame(2, (int) $question->refresh()->upvotes_count);
    }

    #[Test]
    public function votes_are_refused_when_the_board_turned_them_off(): void
    {
        $board = $this->board();
        $board->update(['allow_upvotes' => false]);
        $question = $this->question();

        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER])
            ->assertStatus(422);
    }

    #[Test]
    public function unpublished_questions_cannot_be_voted_on(): void
    {
        $board = $this->board();
        $question = $this->question(QuestionStatus::Pending);

        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", ['voter_token' => self::VOTER])
            ->assertStatus(422);
    }

    #[Test]
    public function a_question_from_another_board_is_not_found(): void
    {
        $board = $this->board();
        $foreignBoard = $this->pastEvent->ensureQuestionBoard();
        $foreign = $foreignBoard->questions()->create([
            'body' => 'Cudzia otázka',
            'author_hash' => str_repeat('f', 64),
            'status' => QuestionStatus::Published,
        ]);

        // Id samo o sebe nie je autorizácia — otázka sa vždy hľadá v nástenke
        // z tokenu.
        $this->postJson("/api/q/{$board->token}/questions/{$foreign->id}/vote", ['voter_token' => self::VOTER])
            ->assertNotFound();
    }

    #[Test]
    public function most_voted_question_comes_first(): void
    {
        $board = $this->board();

        $quiet = $this->question();
        $loud = $board->questions()->create([
            'body' => 'Otázka, ktorú chce celá sála',
            'author_hash' => str_repeat('b', 64),
            'status' => QuestionStatus::Published,
        ]);

        $this->postJson("/api/q/{$board->token}/questions/{$loud->id}/vote", ['voter_token' => self::VOTER]);

        $this->getJson("/api/q/{$board->token}")
            ->assertOk()
            ->assertJsonPath('questions.0.id', $loud->id)
            ->assertJsonPath('questions.1.id', $quiet->id);
    }
}
