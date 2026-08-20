<?php

namespace Tests\Feature\Questions;

use App\Enums\ModelStatus;
use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\QuestionBoard;
use App\Notifications\QuestionAnswered;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Odpoveď e-mailom tomu, kto si ju pri otázke vypýtal.
 *
 * Je to jednorazová správa, nie odber: adresa sa hneď po odoslaní maže
 * a `answer_notified_at` zaručí, že prepísaná odpoveď už druhý e-mail nepošle.
 */
class QuestionAnswerNotificationTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->futureEvent->update(['status' => ModelStatus::Published]);
    }

    private function questionWithEmail(?string $email = 'zuzana@example.com'): Question
    {
        return $this->futureEvent->ensureQuestionBoard()->questions()->create([
            'body' => 'Je pri budove parkovanie?',
            'author_email' => $email,
            'author_hash' => str_repeat('a', 64),
            'status' => QuestionStatus::Published,
        ]);
    }

    private function answer(Question $question, string $text): void
    {
        $this->patchJson("/api/dashboard/questions/{$question->id}", ['answer_body' => $text])
            ->assertOk();
    }

    #[Test]
    public function answering_sends_the_reply_and_forgets_the_address(): void
    {
        Notification::fake();

        $question = $this->questionWithEmail();

        $this->answer($question, 'Áno, priamo pred budovou je parkovisko.');

        Notification::assertSentOnDemand(
            QuestionAnswered::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'zuzana@example.com',
        );

        $question->refresh();

        // Adresa splnila svoj jediný účel — v tabuľke otázok nemá čo ďalej robiť.
        $this->assertNull($question->author_email);
        $this->assertNotNull($question->answer_notified_at);
    }

    #[Test]
    public function a_reworded_answer_does_not_send_a_second_e_mail(): void
    {
        Notification::fake();

        $question = $this->questionWithEmail();

        $this->answer($question, 'Áno, priamo pred budovou.');
        $this->answer($question, 'Áno, priamo pred budovou je veľké parkovisko.');

        Notification::assertSentOnDemandTimes(QuestionAnswered::class, 1);
    }

    #[Test]
    public function a_question_without_an_address_gets_no_e_mail(): void
    {
        Notification::fake();

        $question = $this->questionWithEmail(null);

        $this->answer($question, 'Áno, priamo pred budovou je parkovisko.');

        Notification::assertNothingSent();
        $this->assertNull($question->refresh()->answer_notified_at);
    }

    #[Test]
    public function marking_answered_without_a_text_sends_nothing(): void
    {
        Notification::fake();

        $question = $this->questionWithEmail();

        // „Zodpovedané" bez odpovede je poznámka pre organizátora na stene —
        // nie je čo poslať, a adresa musí ostať pre skutočnú odpoveď.
        $this->patchJson("/api/dashboard/questions/{$question->id}", ['answered' => true])
            ->assertOk();

        Notification::assertNothingSent();
        $this->assertSame('zuzana@example.com', $question->refresh()->author_email);
    }

    #[Test]
    public function the_answer_e_mail_is_never_sent_to_a_stranger(): void
    {
        Notification::fake();

        // Nástenka workshopu vzniká cez QR v sále, kde sa adresa nezbiera —
        // otázka bez adresy nesmie skončiť e-mailom nikde inde.
        $board = $this->futureEvent->ensureQuestionBoard();
        $question = $board->questions()->create([
            'body' => 'Ako ste riešili nasadenie?',
            'author_name' => 'Zuzana',
            'author_hash' => str_repeat('b', 64),
            'status' => QuestionStatus::Published,
        ]);

        $this->answer($question, 'Postupne, po častiach.');

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_foreign_organizer_cannot_answer_and_trigger_the_e_mail(): void
    {
        Notification::fake();

        $question = $this->questionWithEmail();

        $this->app['auth']->forgetGuards();

        $this->patchJson("/api/dashboard/questions/{$question->id}", [
            'answer_body' => 'Podstrčená odpoveď.',
        ])->assertUnauthorized();

        Notification::assertNothingSent();
        $this->assertSame('zuzana@example.com', $question->refresh()->author_email);
    }

    /** Poistka, že sa nástenka v teste naozaj založila tam, kde čakáme. */
    #[Test]
    public function the_board_belongs_to_the_event(): void
    {
        $board = $this->futureEvent->ensureQuestionBoard();

        $this->assertInstanceOf(QuestionBoard::class, $board);
        $this->assertSame($this->futureEvent->id, $board->event()?->id);
    }
}
