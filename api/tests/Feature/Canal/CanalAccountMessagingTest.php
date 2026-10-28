<?php

namespace Tests\Feature\Canal;

use App\Enums\CanalEmailStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\CanalContactNotice;
use App\Notifications\Channels\AccountMailChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * E-maily kanálom cez Account (ACCOUNT_MESSAGING) a návrat odpovedí
 * a nedoručených správ webhookom.
 */
class CanalAccountMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config()->set('account.url', 'http://account.test');
        config()->set('account.token', 'acc_test');
        config()->set('account.webhook_secret', 'whsec_test');
        config()->set('account.messaging', true);
    }

    #[Test]
    public function mail_to_canal_contact_goes_through_account_with_canal_ref(): void
    {
        Http::fake(['account.test/api/v1/messages' => Http::response(['data' => [
            'conversation' => ['id' => 'c-1'], 'message' => ['id' => 'm-1'],
        ]], 202)]);

        $canal = $this->canal('info@divadlo.test');

        Notification::route('mail', 'nova@divadlo.test')
            ->notify(new CanalContactNotice($canal, CanalContactNotice::VERIFY, 'nova@divadlo.test', 'https://event.test/overit'));

        Http::assertSent(function (Request $request) use ($canal) {
            return $request->url() === 'http://account.test/api/v1/messages'
                && $request['to'] === 'nova@divadlo.test'
                && $request['ref']['type'] === 'canal'
                && $request['ref']['id'] === (string) $canal->id
                && str_contains($request['ref']['label'], $canal->name)
                && $request['kind'] === 'canal.contact_verify'
                && str_contains($request['html'], 'https://event.test/overit');
        });

        Mail::assertNothingOutgoing();
        $this->assertTrue(SystemLog::where('event', 'mail.sent')->where('recipient', 'nova@divadlo.test')->exists());
    }

    #[Test]
    public function without_messaging_flag_mail_goes_the_old_way(): void
    {
        config()->set('account.messaging', false);

        $notification = new CanalContactNotice($this->canal('info@divadlo.test'), CanalContactNotice::CHANGED, 'nova@divadlo.test');

        $this->assertSame(['mail'], $notification->via(Notification::route('mail', 'info@divadlo.test')));
    }

    #[Test]
    public function users_still_get_local_mail(): void
    {
        $notification = new CanalContactNotice($this->canal('info@divadlo.test'), CanalContactNotice::CHANGED, 'nova@divadlo.test');

        $this->assertSame(['mail'], $notification->via(User::factory()->create()));
        $this->assertSame([AccountMailChannel::class], $notification->via(Notification::route('mail', 'info@divadlo.test')));
    }

    #[Test]
    public function suppressed_address_is_logged_and_not_retried(): void
    {
        Http::fake(['account.test/*' => Http::response(['message' => 'Blokovaná', 'reason' => 'suppressed'], 422)]);

        Notification::route('mail', 'zla@divadlo.test')
            ->notify(new CanalContactNotice($this->canal('info@divadlo.test'), CanalContactNotice::CHANGED, 'zla@divadlo.test'));

        $this->assertTrue(SystemLog::where('event', 'mail.rejected')->where('recipient', 'zla@divadlo.test')->exists());
    }

    #[Test]
    public function reply_from_contact_confirms_canal_email(): void
    {
        Http::fake();
        $canal = $this->canal('info@divadlo.test');

        $this->webhook('message.received', $canal, 'info@divadlo.test', [
            'from' => 'Info@Divadlo.test',
            'reply_text' => 'Áno, adresa je naša.',
        ])->assertOk();

        $row = $canal->emails()->sole();
        $this->assertSame(CanalEmailStatus::Verified, $row->status);
        $this->assertNotNull($canal->fresh()->email_verified_at);
    }

    #[Test]
    public function reply_from_other_address_does_not_confirm(): void
    {
        Http::fake();
        $canal = $this->canal('info@divadlo.test');

        $this->webhook('message.received', $canal, 'info@divadlo.test', ['from' => 'kolega@divadlo.test'])->assertOk();

        $this->assertNull($canal->fresh()->email_verified_at);
        $this->assertTrue(SystemLog::where('event', 'canals.email_replied')->exists());
    }

    #[Test]
    public function bounce_marks_canal_email_undeliverable(): void
    {
        Http::fake();
        $canal = $this->canal('zla@divadlo.test');

        $this->webhook('message.bounced', $canal, 'zla@divadlo.test', [
            'to' => 'zla@divadlo.test',
            'bounce_type' => 'hard',
            'error' => '5.1.1 user unknown',
        ])->assertOk();

        $row = $canal->emails()->sole();
        $this->assertSame(CanalEmailStatus::Undeliverable, $row->status);
        $this->assertSame('5.1.1 user unknown', $row->bounce_reason);
    }

    #[Test]
    public function message_for_deleted_canal_is_acknowledged(): void
    {
        Http::fake();
        $canal = $this->canal('info@divadlo.test');
        $canal->forceDelete();

        $this->webhook('message.received', $canal, 'info@divadlo.test', ['from' => 'info@divadlo.test'])->assertOk();
    }

    /* ------------------------------------------------------------- */

    private function canal(string $email): Canal
    {
        return Canal::factory()->active()->create([
            'registration_source' => RegistrationSource::SELF->value,
            'email' => $email,
            'email_verified_at' => null,
        ]);
    }

    /** @param array<string, mixed> $message */
    private function webhook(string $event, Canal $canal, string $contact, array $message): \Illuminate\Testing\TestResponse
    {
        $body = json_encode([
            'id' => 'evt-1',
            'event' => $event,
            'data' => [
                'conversation' => [
                    'id' => 'conv-1',
                    'ref' => ['type' => 'canal', 'id' => (string) $canal->id, 'label' => 'Kanál'],
                    'contact_email' => $contact,
                ],
                'message' => ['id' => 'msg-1', ...$message],
            ],
        ]);
        $timestamp = time();

        return $this->call('POST', '/api/webhooks/account', server: [
            'HTTP_X_ACCOUNTS_TIMESTAMP' => $timestamp,
            'HTTP_X_ACCOUNTS_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $body);
    }
}
