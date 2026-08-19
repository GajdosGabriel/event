<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use App\Support\SubmissionTicket;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Otázky a odpovede na **verejnom detaile podujatia**.
 *
 * Nástenka bola doteraz dostupná len cez QR premietnutý v sále. Zodpovedané
 * otázky sú pritom presne to, na čo sa ľudia pýtajú ešte doma — a čo googlia.
 */
class EventQuestionsPublicTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
    }

    #[Test]
    public function event_without_a_board_reports_it_instead_of_404(): void
    {
        // Nástenka sa zakladá lenivo, takže drvivá väčšina podujatí ju nemá.
        // „Nemá" je legitímna odpoveď — UI podľa nej sekciu nevykreslí.
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    #[Test]
    public function reading_questions_never_exposes_the_board_token(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $response = $this->getJson("/api/events/{$this->futureEvent->id}/questions")->assertOk();

        // Token je autorizácia a dá sa rotovať. Keby ho verejný detail posielal,
        // rotácia by stránku rozbila a token by sa šíril mimo QR kódu.
        $this->assertStringNotContainsString($board->token, $response->getContent());
        $response->assertJsonMissingPath('token');
    }

    #[Test]
    public function answered_questions_come_first(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $open = $board->questions()->create([
            'body' => 'Bude sa dať platiť kartou?',
            'author_hash' => 'a',
            'status' => QuestionStatus::Published,
            'upvotes_count' => 50,
        ]);

        $answered = $board->questions()->create([
            'body' => 'Je pri budove parkovanie?',
            'author_hash' => 'b',
            'status' => QuestionStatus::Published,
            'answer_body' => 'Áno, vo dvore je desať miest.',
            'answered_at' => now(),
            'upvotes_count' => 1,
        ]);

        // Návštevník prišiel pre odpoveď, nie pre zoznam otvorených otázok —
        // preto zodpovedaná ide hore aj proti oveľa vyššiemu počtu hlasov.
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonPath('phase', 'before')
            ->assertJsonPath('answered_count', 1)
            ->assertJsonPath('questions.0.id', $answered->id)
            ->assertJsonPath('questions.1.id', $open->id);
    }

    #[Test]
    public function phase_follows_the_event_clock(): void
    {
        $this->futureEvent->ensureQuestionBoard();

        $this->futureEvent->forceFill([
            'start_at' => now()->subMinutes(10),
            'end_at' => now()->addHour(),
        ])->save();

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonPath('phase', 'live');

        $this->futureEvent->forceFill([
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(3)->addHour(),
        ])->save();

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonPath('phase', 'after');
    }

    #[Test]
    public function visitor_can_ask_through_the_event_route(): void
    {
        $this->futureEvent->forceFill([
            'start_at' => now()->subMinutes(10),
            'end_at' => now()->addHour(),
        ])->save();

        $this->futureEvent->ensureQuestionBoard();

        $ticket = $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:event:' . $this->futureEvent->id),
        );

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'ticket' => $ticket,
        ])->assertCreated()->assertJsonPath('pending', false);

        $this->assertDatabaseHas('questions', ['body' => 'Je pri budove parkovanie?']);
    }

    #[Test]
    public function a_ticket_issued_for_the_qr_board_does_not_work_here(): void
    {
        $this->futureEvent->forceFill([
            'start_at' => now()->subMinutes(10),
            'end_at' => now()->addHour(),
        ])->save();

        $board = $this->futureEvent->ensureQuestionBoard();

        // Každá cesta k nástenke má vlastný rozsah známky, aby sa nedali
        // zamieňať.
        $ticket = $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:' . $board->token),
        );

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'ticket' => $ticket,
        ])->assertStatus(422);
    }

    #[Test]
    public function draft_event_hides_its_questions(): void
    {
        $this->futureEvent->ensureQuestionBoard();
        $this->futureEvent->update(['status' => ModelStatus::Draft]);

        // Rovnaká viditeľnosť ako verejný detail — cez otázky sa nesmie dať
        // prečítať koncept.
        $this->getJson("/api/events/{$this->futureEvent->id}/questions")->assertNotFound();
    }

    #[Test]
    public function questions_awaiting_moderation_stay_hidden(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Otázka na schválenie',
            'author_hash' => 'c',
            'status' => QuestionStatus::Pending,
        ]);

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonCount(0, 'questions');
    }
}
