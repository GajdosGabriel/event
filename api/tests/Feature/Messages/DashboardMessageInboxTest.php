<?php

namespace Tests\Feature\Messages;

use App\Models\Message;
use App\Models\User;
use App\Notifications\MessageReplied;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

/**
 * Inbox správ v dashboarde (roadmap 3.4).
 *
 * Popri bežnom čítaní a odpovedaní strážia testy dve veci, na ktorých záleží
 * najviac: cudzí inbox nie je vidieť a v odpovedi API nefiguruje e-mailová
 * adresa protistrany.
 */
class DashboardMessageInboxTest extends EventSetupTest
{
    private User $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visitor = User::factory()->create(['email_verified_at' => now()]);
    }

    private function incoming(array $overrides = []): Message
    {
        return Message::query()->create(array_merge([
            'messageable_type' => $this->futureEvent->getMorphClass(),
            'messageable_id' => $this->futureEvent->id,
            'sender_user_id' => $this->visitor->id,
            'recipient_user_id' => $this->user->id,
            'body' => 'Bude na akcii bezbariérový prístup?',
        ], $overrides));
    }

    #[Test]
    public function inbox_lists_only_messages_addressed_to_me(): void
    {
        $mine = $this->incoming();
        $this->incoming(['recipient_user_id' => $this->visitor->id, 'body' => 'Cudzia správa']);

        $response = $this->getJson('/api/dashboard/messages')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
    }

    #[Test]
    public function inbox_never_exposes_the_other_partys_email(): void
    {
        $this->incoming();

        $body = $this->getJson('/api/dashboard/messages')->assertOk()->getContent();

        $this->assertStringNotContainsString($this->visitor->email, (string) $body);
    }

    #[Test]
    public function unread_filter_and_counter_agree(): void
    {
        $unread = $this->incoming();
        $this->incoming(['read_at' => now(), 'body' => 'Už prečítané']);

        $this->getJson('/api/dashboard/messages?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);

        $this->getJson('/api/dashboard/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('unread', 1);
    }

    #[Test]
    public function opening_a_thread_marks_it_read(): void
    {
        $message = $this->incoming();

        $this->getJson("/api/dashboard/messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('id', $message->id)
            ->assertJsonPath('target.type', 'event');

        $this->assertNotNull($message->fresh()->read_at);

        // A späť na neprečítanú, keď sa k nej chce organizátor vrátiť.
        $this->postJson("/api/dashboard/messages/{$message->id}/read", ['read' => false])->assertOk();

        $this->assertNull($message->fresh()->read_at);
    }

    #[Test]
    public function foreign_message_is_not_readable(): void
    {
        $foreign = $this->incoming([
            'sender_user_id' => $this->userSuperAdmin->id,
            'recipient_user_id' => $this->visitor->id,
        ]);

        $this->getJson("/api/dashboard/messages/{$foreign->id}")->assertStatus(403);
        $this->postJson("/api/dashboard/messages/{$foreign->id}/read")->assertStatus(403);
        $this->postJson("/api/dashboard/messages/{$foreign->id}/reply", ['body' => 'Ahoj'])->assertStatus(403);
    }

    #[Test]
    public function reply_lands_in_the_thread_and_notifies_the_sender(): void
    {
        Notification::fake();

        $this->user->forceFill(['email_verified_at' => now()])->save();

        $message = $this->incoming();

        $this->postJson("/api/dashboard/messages/{$message->id}/reply", [
            'body' => 'Áno, celý areál je bezbariérový.',
        ])->assertStatus(201)->assertJsonPath('outgoing', true);

        $reply = Message::query()->where('parent_message_id', $message->id)->firstOrFail();

        $this->assertSame($this->user->id, (int) $reply->sender_user_id);
        $this->assertSame($this->visitor->id, (int) $reply->recipient_user_id);
        $this->assertSame($message->messageable_id, $reply->messageable_id);

        Notification::assertSentOnDemand(MessageReplied::class);

        // Vlákno ostáva jednoúrovňové — odpoveď na odpoveď visí na tom istom koreni.
        $this->postJson("/api/dashboard/messages/{$message->id}/reply", ['body' => 'Ešte doplním…'])
            ->assertStatus(201);

        $this->assertSame(2, Message::query()->where('parent_message_id', $message->id)->count());

        $this->getJson("/api/dashboard/messages/{$message->id}")
            ->assertOk()
            ->assertJsonCount(2, 'replies');
    }

    #[Test]
    public function replies_are_not_listed_as_separate_threads(): void
    {
        $this->user->forceFill(['email_verified_at' => now()])->save();

        $message = $this->incoming();

        $this->postJson("/api/dashboard/messages/{$message->id}/reply", ['body' => 'Odpoveď.'])
            ->assertStatus(201);

        $this->getJson('/api/dashboard/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_reply_to_my_own_message_lands_in_my_inbox(): void
    {
        // Opačný smer: správu poslal môj účet, odpoveď prišla mne. Jej koreň
        // v mojom inboxe nie je, takže musí dostať vlastný riadok — inak by sa
        // odpoveď nikde nezobrazila a odznak neprečítaných by svietil naprázdno.
        $sent = $this->incoming([
            'sender_user_id' => $this->user->id,
            'recipient_user_id' => $this->visitor->id,
            'body' => 'Otázka na iného organizátora.',
        ]);

        $reply = $this->incoming([
            'parent_message_id' => $sent->id,
            'sender_user_id' => $this->visitor->id,
            'recipient_user_id' => $this->user->id,
            'body' => 'Odpoveď pre mňa.',
        ]);

        $this->getJson('/api/dashboard/messages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reply->id);

        $this->getJson('/api/dashboard/messages/unread-count')
            ->assertOk()
            ->assertJsonPath('unread', 1);

        // Detail otvorí celé vlákno od koreňa a odpoveď označí za prečítanú.
        $this->getJson("/api/dashboard/messages/{$reply->id}")
            ->assertOk()
            ->assertJsonPath('id', $sent->id)
            ->assertJsonPath('outgoing', true);

        $this->assertNotNull($reply->fresh()->read_at);
        $this->assertNull($sent->fresh()->read_at, 'Vlastnú odoslanú správu nemá kto čítať.');
    }

    #[Test]
    public function unverified_account_cannot_reply(): void
    {
        $this->user->forceFill(['email_verified_at' => null])->save();

        $message = $this->incoming();

        $this->postJson("/api/dashboard/messages/{$message->id}/reply", ['body' => 'Odpoveď.'])
            ->assertStatus(403);
    }
}
