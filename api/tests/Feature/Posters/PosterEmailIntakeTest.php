<?php

namespace Tests\Feature\Posters;

use App\Jobs\ProcessPosterEmail;
use App\Models\PosterDraft;
use App\Notifications\PosterEmailReply;
use App\Services\Account\AccountClient;
use App\Services\Account\AccountMessages;
use App\Services\OpenAI\Detector;
use App\Services\Posters\PosterEmailIntake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Podujatie poslané e-mailom na kontaktnú adresu (PosterEmailIntake).
 * OpenAI aj Account sú zamockované — overujeme zapojenie, nie extrakciu.
 */
class PosterEmailIntakeTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = 'Dobrý deň, prosíme o zverejnenie: Mesto Skúšobné pozýva na letný koncert 21. 8. 2026 o 18:00.';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('services.poster_email.enabled', true);
        config()->set('services.poster_email.inbox', 'kontakt@portal.test');

        $account = Mockery::mock(AccountClient::class);
        $account->shouldReceive('messagingEnabled')->andReturn(true);
        $this->app->instance(AccountClient::class, $account);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function an_invitation_by_email_creates_a_draft_and_asks_for_what_is_missing(): void
    {
        $this->detector($this->detection(venue: null));

        app(PosterEmailIntake::class)->unassigned($this->conversation(), $this->message(self::TEXT));

        $draft = PosterDraft::sole();
        $this->assertSame(PosterDraft::CHANNEL_EMAIL, $draft->channel);
        $this->assertSame('conv-1', $draft->account_conversation_id);
        $this->assertSame('jana@farnost.test', $draft->email);
        $this->assertSame(1, $draft->email_rounds);
        $this->assertSame(['venue'], $draft->analysis['missing_required']);
        $this->assertNotNull($draft->email_token);

        Notification::assertSentTo(new AnonymousNotifiable, PosterEmailReply::class, function (PosterEmailReply $reply, array $channels, AnonymousNotifiable $to) use ($draft) {
            $mail = $reply->toMail($to);
            $lines = implode("\n", $mail->introLines);

            return $to->routes['mail'] === 'jana@farnost.test'
                && $reply->accountConversation() === 'conv-1'
                && $reply->accountRef($to)['type'] === PosterEmailIntake::ACCOUNT_REF
                && $reply->accountRef($to)['id'] === $draft->id
                && $mail->subject === 'Re: Pozvánka na koncert'
                && str_contains($lines, 'Letný koncert')
                && str_contains($lines, 'miesto konania')
                && str_contains((string) $mail->actionUrl, '/nahrat-plagat/'.$draft->id.'?token=');
        });
    }

    #[Test]
    public function ordinary_mail_without_a_date_is_left_to_the_operator_without_ai(): void
    {
        $detector = Mockery::mock(Detector::class);
        $detector->shouldNotReceive('detectFromPoster');
        $this->app->instance(Detector::class, $detector);

        app(PosterEmailIntake::class)->unassigned($this->conversation(), $this->message('Dobrý deň, ako sa dá zmeniť heslo k účtu na vašom portáli? Ďakujem.'));

        $this->assertSame(0, PosterDraft::count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function a_correction_of_an_existing_event_is_left_to_the_operator(): void
    {
        $detector = Mockery::mock(Detector::class);
        $detector->shouldNotReceive('detectFromPoster');
        $this->app->instance(Detector::class, $detector);

        app(PosterEmailIntake::class)->unassigned($this->conversation(), $this->message(
            'Prosíme o opravu: /akcie/11284/konferencia-v-trnave — koniec je 7. 11. 2026 o 13:15.'
        ));

        $this->assertSame(0, PosterDraft::count());
    }

    #[Test]
    public function mail_that_ai_does_not_read_as_an_event_creates_nothing(): void
    {
        $this->detector($this->detection(title: null));

        app(PosterEmailIntake::class)->unassigned($this->conversation(), $this->message(self::TEXT));

        $this->assertSame(0, PosterDraft::count());
        Notification::assertNothingSent();
    }

    #[Test]
    public function a_repeated_webhook_does_not_create_a_second_draft(): void
    {
        $this->detector($this->detection());
        $intake = app(PosterEmailIntake::class);

        $intake->unassigned($this->conversation(), $this->message(self::TEXT));
        $intake->unassigned($this->conversation(), $this->message(self::TEXT));

        $this->assertSame(1, PosterDraft::count());
    }

    #[Test]
    public function mail_to_another_mailbox_is_ignored(): void
    {
        $this->detector($this->detection());

        app(PosterEmailIntake::class)->unassigned($this->conversation(['mailbox' => 'faktury@portal.test']), $this->message(self::TEXT));

        $this->assertSame(0, PosterDraft::count());
    }

    #[Test]
    public function the_reply_fills_in_what_was_missing_and_rounds_are_capped(): void
    {
        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromPoster')->once()->andReturn($this->detection(venue: null));
        $detector->shouldReceive('detectFromPoster')
            ->withArgs(fn (string $text) => str_contains($text, 'Kultúrny dom Žilina'))
            ->andReturn($this->detection());
        $this->app->instance(Detector::class, $detector);
        config()->set('services.poster_email.max_rounds', 2);

        $intake = app(PosterEmailIntake::class);
        $intake->unassigned($this->conversation(), $this->message(self::TEXT));
        $draft = PosterDraft::sole();

        $intake->received($draft, $this->message('Bude to v Kultúrny dom Žilina.', id: 'msg-2'));

        $draft->refresh();
        $this->assertSame(2, $draft->email_rounds);
        $this->assertSame([], $draft->analysis['missing_required']);
        $this->assertStringContainsString('Kultúrny dom Žilina', $draft->extracted_text);
        Notification::assertSentTimes(PosterEmailReply::class, 2);

        // Ďalšie kolo už neodpovedá — prevezme to človek v inboxe Accountu.
        $intake->received($draft->fresh(), $this->message('A ešte niečo.', id: 'msg-3'));
        $this->assertSame(2, $draft->fresh()->email_rounds);
        Notification::assertSentTimes(PosterEmailReply::class, 2);
    }

    #[Test]
    public function the_same_reply_twice_is_processed_once(): void
    {
        $this->detector($this->detection());
        $intake = app(PosterEmailIntake::class);
        $intake->unassigned($this->conversation(), $this->message(self::TEXT));

        $intake->received(PosterDraft::sole(), $this->message('Doplnenie.', id: 'msg-2'));
        $intake->received(PosterDraft::sole(), $this->message('Doplnenie.', id: 'msg-2'));

        $this->assertSame(2, PosterDraft::sole()->email_rounds);
    }

    #[Test]
    public function too_many_mails_from_one_sender_are_left_to_the_operator(): void
    {
        $this->detector($this->detection());
        config()->set('services.poster_email.per_sender_daily', 1);
        $intake = app(PosterEmailIntake::class);

        $intake->unassigned($this->conversation(['id' => 'conv-1']), $this->message(self::TEXT));
        $intake->unassigned($this->conversation(['id' => 'conv-2']), $this->message(self::TEXT));

        $this->assertSame(1, PosterDraft::count());
    }

    #[Test]
    public function account_webhook_hands_the_mail_to_the_queue(): void
    {
        Bus::fake();
        $messages = app(AccountMessages::class);

        $messages->handle('message.unassigned', $this->conversation(), $this->message(self::TEXT));
        Bus::assertDispatched(ProcessPosterEmail::class, fn (ProcessPosterEmail $job) => $job->kind === 'unassigned');

        $messages->handle('message.received', $this->conversation(['ref' => ['type' => PosterEmailIntake::ACCOUNT_REF, 'id' => 'draft-1']]), $this->message('Doplnenie.'));
        Bus::assertDispatched(ProcessPosterEmail::class, fn (ProcessPosterEmail $job) => $job->kind === 'received' && $job->draftId === 'draft-1');
    }

    #[Test]
    public function the_inbox_is_shown_only_when_intake_is_enabled(): void
    {
        $this->getJson('/api/poster/inbox')->assertOk()->assertJsonPath('email', 'kontakt@portal.test');

        config()->set('services.poster_email.enabled', false);
        $this->getJson('/api/poster/inbox')->assertOk()->assertJsonPath('email', null);
    }

    private function detector(array $detection): void
    {
        $detector = Mockery::mock(Detector::class);
        $detector->shouldReceive('detectFromPoster')->andReturn($detection);
        $this->app->instance(Detector::class, $detector);
    }

    /** @return array<string, mixed> */
    private function detection(?string $title = 'Letný koncert', ?string $venue = 'Kultúrny dom'): array
    {
        return [
            'success' => true,
            'corrected_text' => 'Pozývame vás na letný koncert.',
            'event_payload' => [
                'title' => $title,
                'start_at' => '2026-08-21 18:00:00',
                'end_at' => null,
                'organizer' => ['name' => 'Mesto Skúšobné', 'street_and_number' => null, 'city' => 'Žilina'],
                'venue' => ['name' => $venue, 'street_and_number' => null, 'city' => $venue !== null ? 'Žilina' : null],
                'email' => null,
                'phone' => null,
                'persons' => [],
            ],
            'organizer_canal' => ['name' => 'Mesto Skúšobné', 'existing' => null],
            'venue_detect' => null,
            'persons' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function conversation(array $override = []): array
    {
        return $override + [
            'id' => 'conv-1',
            'ref' => null,
            'contact_email' => 'jana@farnost.test',
            'mailbox' => 'kontakt@portal.test',
            'subject' => 'Pozvánka na koncert',
        ];
    }

    /** @return array<string, mixed> */
    private function message(string $text, string $id = 'msg-1'): array
    {
        return [
            'id' => $id,
            'direction' => 'in',
            'from' => 'Jana@Farnost.test',
            'subject' => 'Pozvánka na koncert',
            'text' => $text,
            'reply_text' => $text,
            'attachments' => [],
        ];
    }
}
