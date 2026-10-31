<?php

namespace Tests\Feature\Canal;

use App\Enums\ModelStatus;
use App\Enums\RegistrationSource;
use App\Models\Canal;
use App\Models\CanalInvitation;
use App\Models\EmailSuppression;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Channels\AccountMailChannel;
use App\Notifications\MessageRelayed;
use App\Services\Account\AccountClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Správa kanálu bez správcu ide e-mailom na jeho kontakt s ponukou prevzatia —
 * rovnako ako otázka a prihláška (CanalTakeover, CanalRelay).
 */
class CanalRelayMessageTest extends TestCase
{
    use DatabaseTransactions;

    private Canal $canal;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Cache::flush();

        $this->canal = Canal::factory()->create([
            'name' => 'Farnosť Hájske',
            'registration_source' => RegistrationSource::IMPORT->value,
            'status' => ModelStatus::Published->value,
            'published_at' => now(),
            'email' => 'farnost@example.sk',
        ]);
    }

    private function send(string $body = 'Kedy začína omša?'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/messages', [
            'target_type' => 'canal',
            'target_id' => $this->canal->id,
            'body' => $body,
        ]);
    }

    private function sender(): User
    {
        return User::factory()->create(['email' => 'pisatel@example.sk', 'email_verified_at' => now()]);
    }

    #[Test]
    public function message_goes_to_the_canal_contact_with_a_takeover_offer(): void
    {
        $this->actingAs($this->sender(), 'sanctum');

        $this->send()->assertCreated();

        Notification::assertSentOnDemand(MessageRelayed::class, function (MessageRelayed $n, array $channels, $notifiable) {
            $mail = $n->toMail($notifiable);
            $text = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

            return $notifiable->routes['mail'] === 'farnost@example.sk'
                && $n->audience === MessageRelayed::ORGANIZER
                && str_contains($text, 'Kedy začína omša?')
                && str_contains($text, 'pisatel@example.sk')
                && str_contains($text, 'email/unsubscribe')
                && str_contains((string) $mail->actionUrl, '/pozvanka/');
        });

        $this->assertSame(1, CanalInvitation::where('canal_id', $this->canal->id)->pending()->count());
        $this->assertSame(0, Message::query()->where('messageable_id', $this->canal->id)->count());
    }

    #[Test]
    public function detail_offers_the_button_only_when_the_contact_is_usable(): void
    {
        $this->assertTrue($this->canal->isContactableBy(null));

        EmailSuppression::add('farnost@example.sk', 'bounced');
        $this->assertFalse($this->canal->isContactableBy(null));
    }

    #[Test]
    public function suppressed_contact_and_collection_canal_get_nothing(): void
    {
        $this->actingAs($this->sender(), 'sanctum');

        EmailSuppression::add('farnost@example.sk', 'bounced');
        $this->send()->assertStatus(422);

        EmailSuppression::query()->delete();
        $this->canal->update(['name' => 'vyveska.sk']);
        $this->send()->assertStatus(422);

        Notification::assertNothingSent();
    }

    #[Test]
    public function guest_and_duplicate_are_refused(): void
    {
        $this->send()->assertUnauthorized();

        $this->actingAs($this->sender(), 'sanctum');
        $this->send()->assertCreated();
        $this->send()->assertStatus(422);

        Notification::assertSentOnDemandTimes(MessageRelayed::class, 1);
    }

    #[Test]
    public function relay_goes_through_account_when_messaging_is_on(): void
    {
        $this->mock(AccountClient::class, fn ($mock) => $mock->shouldReceive('messagingEnabled')->andReturnTrue());
        $notice = new MessageRelayed($this->canal, $this->canal, MessageRelayed::ORGANIZER, 'x', 'X', 'x@example.sk', 'u', 'u', 'farnost@example.sk');
        $notifiable = Notification::route('mail', 'farnost@example.sk');

        $this->assertSame([AccountMailChannel::class], $notice->via($notifiable));
        $this->assertSame((string) $this->canal->id, $notice->accountRef($notifiable)['id']);
    }
}
