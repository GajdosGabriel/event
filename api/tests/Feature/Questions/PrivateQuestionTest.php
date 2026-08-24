<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use App\Enums\QuestionVisibility;
use App\Notifications\QuestionReceived;
use App\Support\SubmissionTicket;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Súkromná otázka a podnet z publika.
 *
 * Sú to dve mená pre jednu vec: vstup, ktorý sa nikde nezverejní a ktorého
 * jediná cesta späť k pisateľovi je e-mail. Pred podujatím je to otázka, ktorá
 * sa na plátno nehodí („som na vozíku, dostanem sa dnu?"), počas neho podnet
 * pre organizátora („v sále je zima").
 *
 * Testy strážia hlavne to, čo je pri tejto funkcii sľub: **že to nikto iný
 * neuvidí** a že sa pisateľ odpoveď má ako dozvedieť.
 */
class PrivateQuestionTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['status' => ModelStatus::Published]);

        Notification::fake();
    }

    #[Test]
    public function a_private_question_is_nowhere_in_the_public_list(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'author_hash' => str_repeat('a', 64),
            // Zámerne „zverejnená": stav rieši moderovanie, viditeľnosť sľub
            // daný pisateľovi. Keby stačil stav, otázka by na stránke visela.
            'status' => QuestionStatus::Published,
            'visibility' => QuestionVisibility::Private,
        ]);

        $this->getJson("/api/events/{$this->futureEvent->id}/questions")
            ->assertOk()
            ->assertJsonCount(0, 'questions');
    }

    #[Test]
    public function a_private_question_does_not_move_the_public_counter(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'visibility' => 'private',
            'ticket' => $this->ticket(),
        ])->assertCreated();

        // `questions_count` sa ukazuje verejne — nesmie prezradiť ani to,
        // koľko súkromných vecí organizátorovi prišlo.
        $this->assertSame(0, (int) $board->refresh()->questions_count);
    }

    #[Test]
    public function a_guest_must_leave_an_address(): void
    {
        $this->futureEvent->ensureQuestionBoard();

        // Bez adresy by odpoveď nemala kam prísť: vo verejnom zozname nebude
        // ani ona, ani otázka.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'visibility' => 'private',
            'ticket' => $this->ticket(),
        ])->assertStatus(422)->assertJsonValidationErrors('author_email');
    }

    #[Test]
    public function the_notification_is_implied_not_asked_for(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        // `notify` sa neposiela vôbec — pri súkromnej otázke nie je čo
        // zaškrtávať a server si to odvodí sám.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'visibility' => 'private',
            'author_email' => 'Zuzana@Example.com',
            'ticket' => $this->ticket(),
        ])->assertCreated()
            ->assertJsonPath('notify', true)
            ->assertJsonPath('visibility', 'private')
            // Do zoznamu sa nemá čo dopisovať ani odosielateľovi.
            ->assertJsonPath('question', null);

        $question = $board->questions()->firstOrFail();

        $this->assertSame('zuzana@example.com', $question->author_email);
        $this->assertTrue($question->isPrivate());
    }

    #[Test]
    public function a_signed_in_visitor_fills_nothing_in(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();
        $ticket = $this->ticket();

        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'visibility' => 'private',
            'ticket' => $ticket,
        ])->assertCreated()->assertJsonPath('notify', true);

        $question = $board->questions()->firstOrFail();

        $this->assertSame($this->user->email, $question->author_email);
        $this->assertSame((int) $this->user->id, (int) $question->user_id);
    }

    #[Test]
    public function feedback_during_the_event_needs_an_account(): void
    {
        $this->futureEvent->ensureQuestionBoard();
        $this->makeEventLive();

        // Podnet je prevádzková informácia, podľa ktorej niekto niečo urobí.
        // Anonymné „v sále je zima" z druhého konca internetu ňou nie je.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'V sále je veľká zima.',
            'visibility' => 'private',
            'author_email' => 'zuzana@example.com',
            'ticket' => $this->ticket(),
        ])->assertStatus(422);

        $ticket = $this->ticket();
        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'V sále je veľká zima.',
            'visibility' => 'private',
            'ticket' => $ticket,
        ])->assertCreated();
    }

    #[Test]
    public function a_public_question_during_the_event_stays_open_to_everyone(): void
    {
        $this->futureEvent->ensureQuestionBoard();
        $this->makeEventLive();

        // Pravidlo o účte platí len na súkromný vstup. Otázka pre
        // prednášajúceho zostáva bez prihlásenia — to je celý zmysel QR kódu.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Bude prezentácia k dispozícii?',
            'ticket' => $this->ticket(),
        ])->assertCreated();
    }

    #[Test]
    public function a_board_can_refuse_private_questions(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();
        $board->update(['allow_private' => false]);

        // Súkromná otázka je záväzok odpovedať e-mailom. Kto ho dať nechce,
        // ho nesmie ani nechtiac sľúbiť.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'visibility' => 'private',
            'author_email' => 'zuzana@example.com',
            'ticket' => $this->ticket(),
        ])->assertStatus(422);

        $this->assertSame(0, $board->questions()->count());
    }

    #[Test]
    public function the_qr_board_never_takes_a_private_question(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $ticket = $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:' . $board->token),
        );

        // Nástenka z QR je zámerne bez kontaktu — odpoveď tam zaznie nahlas.
        // Súkromná otázka by v nej bola správa do prázdna, tak radšej 422 než
        // ticho zverejniť niečo, čo malo byť súkromné.
        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'visibility' => 'private',
            'ticket' => $ticket,
        ])->assertStatus(422);
    }

    #[Test]
    public function every_question_reaches_the_organizer(): void
    {
        $this->futureEvent->ensureQuestionBoard();
        $this->makeEventLive();

        $this->actingAs($this->user, 'sanctum');

        // Pravidlo je jedno a bez výnimiek: otázka, o ktorej sa organizátor
        // nedozvie, je otázka bez odpovede. Aj keď hovoria to isté.
        foreach (['V sále je veľká zima.', 'A stále je zima.'] as $body) {
            $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
                'body' => $body,
                'visibility' => 'private',
                'ticket' => $this->ticket(),
            ])->assertCreated();
        }

        Notification::assertSentTimes(QuestionReceived::class, 2);
    }

    #[Test]
    public function a_public_question_reaches_the_organizer_too(): void
    {
        $this->futureEvent->ensureQuestionBoard();

        // Verejná otázka síce visí na stránke, ale nezodpovedaná tam visí
        // rovnako dlho, kým sa organizátor sám nepozrie do dashboardu.
        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Je pri budove parkovanie?',
            'ticket' => $this->ticket(),
        ])->assertCreated();

        Notification::assertSentTimes(QuestionReceived::class, 1);
    }

    #[Test]
    public function a_question_from_the_qr_board_reaches_the_organizer(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $ticket = $this->travelTo(
            now()->subSeconds(10),
            fn () => SubmissionTicket::issue('question:' . $board->token),
        );

        $this->postJson("/api/q/{$board->token}/questions", [
            'body' => 'Bude prezentácia k dispozícii?',
            'ticket' => $ticket,
        ])->assertCreated();

        Notification::assertSentTimes(QuestionReceived::class, 1);
    }

    #[Test]
    public function an_imported_event_alerts_its_organizer_too(): void
    {
        $this->futureEvent->ensureQuestionBoard();

        // Importované podujatie nemá príjemcu **verejných správ**: za cudzieho
        // organizátora nevie odpovedať ten, kto ho len prevzal zo zdroja.
        // Nástenka je iný prípad — niekto ju na tomto podujatí ručne zapol.
        // Bez tohto rozlíšenia by otázka z importovaného podujatia (a tých je
        // v katalógu väčšina) neoznámila nikdy nikomu nič.
        $this->futureEvent->forceFill(['orginal_source' => 'https://www.vyveska.sk/nieco/'])->save();

        $this->assertNull($this->futureEvent->messageRecipient());

        $this->postJson("/api/events/{$this->futureEvent->id}/questions", [
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'visibility' => 'private',
            'author_email' => 'zuzana@example.com',
            'ticket' => $this->ticket(),
        ])->assertCreated();

        Notification::assertSentTimes(QuestionReceived::class, 1);
    }

    #[Test]
    public function a_private_question_cannot_be_voted_on(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $question = $board->questions()->create([
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'author_hash' => str_repeat('b', 64),
            'status' => QuestionStatus::Published,
            'visibility' => QuestionVisibility::Private,
        ]);

        // Id sa von nedostane, ale pozná ho ten, kto otázku poslal.
        $this->postJson("/api/q/{$board->token}/questions/{$question->id}/vote", [
            'voter_token' => str_repeat('t', 32),
        ])->assertStatus(422);

        $this->assertSame(0, (int) $question->refresh()->upvotes_count);
    }

    #[Test]
    public function the_organizer_cannot_put_it_on_the_wall(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $question = $board->questions()->create([
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'author_hash' => str_repeat('c', 64),
            'status' => QuestionStatus::Published,
            'visibility' => QuestionVisibility::Private,
        ]);

        $this->actingAs($this->user, 'sanctum');

        // Zvýraznenie je „práve na toto odpovedáme" na plátne. Pisateľ písal
        // s tým, že ho nikto iný neuvidí.
        $this->patchJson("/api/dashboard/questions/{$question->id}", ['highlighted' => true])
            ->assertStatus(422);
    }

    #[Test]
    public function the_dashboard_lists_them_apart(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $board->questions()->create([
            'body' => 'Je pri budove parkovanie?',
            'author_hash' => str_repeat('d', 64),
            'status' => QuestionStatus::Published,
        ]);

        $private = $board->questions()->create([
            'body' => 'Som na vozíku, dostanem sa dnu?',
            'author_hash' => str_repeat('e', 64),
            'status' => QuestionStatus::Published,
            'visibility' => QuestionVisibility::Private,
            'author_email' => 'zuzana@example.com',
        ]);

        $this->actingAs($this->user, 'sanctum');

        $this->getJson("/api/dashboard/question-boards/{$board->id}/questions?visibility=private")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $private->id)
            ->assertJsonPath('data.0.visibility', 'private')
            ->assertJsonPath('data.0.notifies_author', true)
            ->assertJsonPath('counts.private_open', 1);
    }

    /** Podujatie práve prebieha — vtedy je súkromný vstup podnetom. */
    private function makeEventLive(): void
    {
        $this->futureEvent->forceFill([
            'start_at' => now()->subMinutes(10),
            'end_at' => now()->addHour(),
        ])->save();
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
