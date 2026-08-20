<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use App\Models\QuestionBoard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class QuestionSubmissionTest extends EventSetupTest
{
    private function openBoard(): QuestionBoard
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        return $this->futureEvent->ensureQuestionBoard();
    }

    /**
     * Známku vydáva GET na nástenku a odosielanie ju musí vrátiť. Vyžaduje si
     * aspoň tri sekundy „vypĺňania", takže test čas posunie.
     */
    private function ticketFor(QuestionBoard $board): string
    {
        $ticket = $this->getJson("/api/q/{$board->token}")->json('ticket');
        $this->travel(4)->seconds();

        return $ticket;
    }

    #[Test]
    public function anyone_can_ask_without_an_account(): void
    {
        $board = $this->openBoard();

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Ako ste riešili nasadenie na produkcii?',
            'author_name' => 'Zuzana',
            'ticket' => $this->ticketFor($board),
        ])
            ->assertStatus(201)
            ->assertJsonPath('pending', false)
            ->assertJsonPath('question.author_name', 'Zuzana');

        $this->assertDatabaseHas('questions', [
            'question_board_id' => $board->id,
            'body' => 'Ako ste riešili nasadenie na produkcii?',
            'status' => QuestionStatus::Published->value,
        ]);

        $this->assertSame(1, (int) $board->refresh()->questions_count);
    }

    #[Test]
    public function moderated_board_holds_the_question_back(): void
    {
        $board = $this->openBoard();
        $board->update(['moderation' => true]);

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Otázka na schválenie',
            'ticket' => $this->ticketFor($board),
        ])
            ->assertStatus(201)
            ->assertJsonPath('pending', true)
            ->assertJsonPath('question', null);

        // Počítadlo drží len zverejnené — inak by verejná stránka prezradila,
        // koľko toho visí v moderácii.
        $this->assertSame(0, (int) $board->refresh()->questions_count);
    }

    #[Test]
    public function closed_board_refuses_new_questions(): void
    {
        $board = $this->openBoard();
        $ticket = $this->ticketFor($board);
        $board->update(['is_open' => false]);

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Už neskoro',
            'ticket' => $ticket,
        ])->assertStatus(422);

        $this->assertDatabaseCount('questions', 0);
    }

    #[Test]
    public function honeypot_field_stops_the_submission(): void
    {
        $board = $this->openBoard();

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Kúpte si moje hodinky',
            'website' => 'https://spam.example',
            'ticket' => $this->ticketFor($board),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertDatabaseCount('questions', 0);
    }

    #[Test]
    public function submission_without_a_ticket_is_rejected(): void
    {
        $board = $this->openBoard();

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Bot, ktorý našiel endpoint',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    #[Test]
    public function submission_faster_than_a_human_is_rejected(): void
    {
        $board = $this->openBoard();

        // Známka bez posunu času — formulár by sa musel vyplniť do troch sekúnd.
        $ticket = $this->getJson("/api/q/{$board->token}")->json('ticket');

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Odoslané okamžite',
            'ticket' => $ticket,
        ])->assertStatus(422);
    }

    #[Test]
    public function ticket_from_another_board_does_not_work(): void
    {
        $board = $this->openBoard();
        $other = $this->pastEvent->ensureQuestionBoard();
        $this->pastEvent->update(['status' => ModelStatus::Published]);

        $foreignTicket = $this->getJson("/api/q/{$other->token}")->json('ticket');
        $this->travel(4)->seconds();

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Známka z inej nástenky',
            'ticket' => $foreignTicket,
        ])->assertStatus(422);
    }

    #[Test]
    public function the_same_question_twice_in_a_row_is_a_double_click(): void
    {
        $board = $this->openBoard();
        $ticket = $this->ticketFor($board);

        $payload = ['body' => 'Dvojklik na tlačidle', 'ticket' => $ticket];

        $this->postJson("/api/q/{$board->token}/questions", $payload)->assertStatus(201);
        $this->postJson("/api/q/{$board->token}/questions", $payload)->assertStatus(422);

        $this->assertDatabaseCount('questions', 1);
    }

    #[Test]
    public function question_body_is_validated(): void
    {
        $board = $this->openBoard();
        $ticket = $this->ticketFor($board);

        $this->postJson("/api/q/{$board->token}/questions", ['body' => 'a', 'ticket' => $ticket])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->postJson("/api/q/{$board->token}/questions", ['body' => str_repeat('x', 501), 'ticket' => $ticket])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    #[Test]
    public function name_is_dropped_when_the_board_does_not_ask_for_it(): void
    {
        $board = $this->openBoard();
        $board->update(['ask_for_name' => false]);

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Anonymná otázka',
            'author_name' => 'Podstrčené meno',
            'ticket' => $this->ticketFor($board),
        ])->assertStatus(201);

        $this->assertDatabaseHas('questions', [
            'body' => 'Anonymná otázka',
            'author_name' => null,
        ]);
    }

    #[Test]
    public function question_body_is_stored_as_plain_text(): void
    {
        $board = $this->openBoard();

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => '<script>alert(1)</script> Naozaj?',
            'ticket' => $this->ticketFor($board),
        ])->assertStatus(201);

        // Text sa neupravuje ani nečistí — front ho vykresľuje interpoláciou,
        // nikdy cez v-html, takže značka je len znakmi.
        $this->assertDatabaseHas('questions', [
            'body' => '<script>alert(1)</script> Naozaj?',
        ]);
    }

    #[Test]
    public function the_qr_board_collects_no_address(): void
    {
        // Nástenka v sále je zámerne bez kontaktu — odpoveď tam zaznie nahlas.
        // Podstrčené pole musí zahodiť server, nie sa spoliehať na formulár.
        $board = $this->openBoard();

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Ako ste riešili nasadenie na produkcii?',
            'notify' => true,
            'author_email' => 'zuzana@example.com',
            'ticket' => $this->ticketFor($board),
        ])->assertStatus(201);

        $this->assertDatabaseHas('questions', [
            'body' => 'Ako ste riešili nasadenie na produkcii?',
            'author_email' => null,
            'user_id' => null,
        ]);
    }
}
