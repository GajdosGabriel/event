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

    #[Test]
    public function a_freshly_enabled_board_takes_questions_right_away(): void
    {
        // Podujatie je o mesiac a nástenka práve vznikla. Kto ju zapol, ju chce
        // mať zapnutú — čakať na nejaký termín by zabilo predakčné otázky
        // organizátorovi, teda presne to, načo je sekcia na detaile.
        $this->futureEvent->ensureQuestionBoard();

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonPath('phase', 'before')
            ->assertJsonPath('open', true);

        $ticket = $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:event:' . $this->futureEvent->id),
        );

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'ticket' => $ticket,
        ])->assertCreated();
    }

    #[Test]
    public function organizers_switch_closes_the_detail_too(): void
    {
        // `is_open` je jediný vypínač a musí zabrať aj tu, nielen na plátne.
        $board = $this->futureEvent->ensureQuestionBoard();
        $board->update(['is_open' => false]);

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonPath('open', false);

        $ticket = $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:event:' . $this->futureEvent->id),
        );

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'ticket' => $ticket,
        ])->assertStatus(422)->assertJsonPath('message', __('questions.errors.closed'));
    }

    #[Test]
    public function a_visitor_can_ask_for_the_answer_by_e_mail(): void
    {
        $this->futureEvent->ensureQuestionBoard();

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'notify' => true,
            'author_email' => 'Zuzana@Example.COM',
            'ticket' => $this->ticket(),
        ])->assertCreated()->assertJsonPath('notify', true);

        // Adresa sa ukladá malými písmenami — rovnako ako pri odberoch.
        $this->assertDatabaseHas('questions', [
            'body' => 'Je pri budove parkovanie?',
            'author_email' => 'zuzana@example.com',
            'user_id' => null,
        ]);
    }

    #[Test]
    public function asking_for_a_reply_without_an_address_is_refused(): void
    {
        $this->futureEvent->ensureQuestionBoard();

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'notify' => true,
            'ticket' => $this->ticket(),
        ])->assertStatus(422)->assertJsonValidationErrors('author_email');
    }

    #[Test]
    public function without_asking_for_it_no_address_is_stored(): void
    {
        $this->futureEvent->ensureQuestionBoard();

        // Adresa v tele požiadavky bez zaškrtnutého `notify` je nesúhlas —
        // ukladať ju by bolo presne to, čo sľubujeme, že nerobíme.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'author_email' => 'zuzana@example.com',
            'ticket' => $this->ticket(),
        ])->assertCreated()->assertJsonPath('notify', false);

        $this->assertDatabaseHas('questions', [
            'body' => 'Je pri budove parkovanie?',
            'author_email' => null,
        ]);
    }

    #[Test]
    public function a_signed_in_visitor_fills_nothing_in(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();
        $ticket = $this->ticket();

        $this->actingAs($this->user, 'sanctum');

        // Ani meno, ani adresa — obe vie server z účtu. Klient by adresu ani
        // nemal odkiaľ vziať, UserResource ju do SPA neposiela.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'notify' => true,
            'ticket' => $ticket,
        ])->assertCreated()->assertJsonPath('notify', true);

        $question = $board->questions()->firstOrFail();

        $this->assertSame($this->user->email, $question->author_email);
        $this->assertSame((int) $this->user->id, (int) $question->user_id);
        $this->assertSame($this->user->displayName(), $question->author_name);
    }

    #[Test]
    public function the_address_never_leaves_the_server(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Je pri budove parkovanie?',
            'author_name' => 'Zuzana',
            'author_email' => 'zuzana@example.com',
            'author_hash' => str_repeat('e', 64),
            'status' => QuestionStatus::Published,
        ]);

        // Rovnaká poistka ako pri tokene nástenky: adresa nemá čo opustiť server
        // ani v jednom poli odpovede.
        $response = $this->getJson("/api/events/{$this->futureEvent->id}/questions")->assertOk();

        $this->assertStringNotContainsString('zuzana@example.com', $response->getContent());
    }

    /** Známka si žiada aspoň tri sekundy „vypĺňania", preto posun času. */
    private function ticket(): string
    {
        return $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:event:' . $this->futureEvent->id),
        );
    }
}
