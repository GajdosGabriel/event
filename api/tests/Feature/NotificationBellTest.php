<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Notifications\Channels\BellChannel;
use App\Notifications\EventInterestRecorded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::withoutEvents(fn () => User::factory()->create(['uuid' => (string) Str::uuid()]));
    }

    private function item(User $user, bool $read = false, string $message = 'Správa'): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => EventInterestRecorded::class,
            'data' => ['message' => $message, 'link' => '/dashboard/spravy'],
            'read_at' => $read ? now() : null,
        ]);

        return $id;
    }

    public function test_guests_cannot_read_or_change_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/count')->assertUnauthorized();
        $this->postJson('/api/notifications/read')->assertUnauthorized();
        $this->postJson('/api/notifications/unread')->assertUnauthorized();
        $this->deleteJson('/api/notifications')->assertUnauthorized();
    }

    public function test_list_is_paginated_and_count_includes_older_items_only_for_owner(): void
    {
        $user = $this->user();
        foreach (range(1, 17) as $i) {
            $this->item($user, false, "Správa {$i}");
        }
        $this->item($user, true);
        $this->item($this->user(), false, 'Cudzia správa');
        $this->actingAs($user)->getJson('/api/notifications')
            ->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.unread', 17)->assertJsonPath('meta.last_page', 2)
            ->assertJsonMissing(['message' => 'Cudzia správa']);
        $this->getJson('/api/notifications?page=2')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/notifications/count')->assertJsonPath('unread', 17);
    }

    public function test_read_unread_and_delete_are_scoped_to_owner(): void
    {
        $user = $this->user();
        $own = $this->item($user);
        $foreign = $this->item($this->user());
        $this->actingAs($user)->postJson('/api/notifications/read', ['ids' => [$own, $foreign]])->assertNoContent();
        $this->assertNotNull($user->notifications()->find($own)->read_at);
        $this->assertDatabaseHas('notifications', ['id' => $foreign, 'read_at' => null]);
        $this->postJson('/api/notifications/unread', ['ids' => [$own, $foreign]])->assertNoContent();
        $this->assertNull($user->notifications()->find($own)->read_at);
        $this->deleteJson('/api/notifications', ['ids' => [$own, $foreign]])->assertNoContent();
        $this->assertDatabaseMissing('notifications', ['id' => $own]);
        $this->assertDatabaseHas('notifications', ['id' => $foreign]);
    }

    public function test_empty_or_invalid_selection_never_changes_everything(): void
    {
        $user = $this->user();
        $id = $this->item($user);
        $this->actingAs($user)->postJson('/api/notifications/read', ['ids' => []])->assertUnprocessable();
        $this->deleteJson('/api/notifications', ['ids' => []])->assertUnprocessable();
        $this->deleteJson('/api/notifications', ['ids' => null])->assertUnprocessable();
        $this->deleteJson('/api/notifications', ['only' => 'anything'])->assertUnprocessable();
        $this->postJson('/api/notifications/read', ['ids' => ['invalid']])->assertUnprocessable();
        $this->assertDatabaseHas('notifications', ['id' => $id, 'read_at' => null]);
    }

    public function test_bulk_actions_include_items_beyond_first_page(): void
    {
        $user = $this->user();
        foreach (range(1, 17) as $i) {
            $this->item($user);
        }
        $foreign = $this->item($this->user());
        $this->actingAs($user)->postJson('/api/notifications/read')->assertNoContent();
        $this->assertSame(0, $user->unreadNotifications()->count());
        $unread = $this->item($user);
        $this->deleteJson('/api/notifications', ['only' => 'read'])->assertNoContent();
        $this->assertSame(1, $user->notifications()->count());
        $this->assertDatabaseHas('notifications', ['id' => $unread, 'read_at' => null]);
        $this->deleteJson('/api/notifications')->assertNoContent();
        $this->assertSame(0, $user->notifications()->count());
        $this->assertDatabaseHas('notifications', ['id' => $foreign, 'read_at' => null]);
    }

    public function test_existing_email_notification_creates_bell_item_for_verified_recipient_without_sending_mail(): void
    {
        $user = $this->user();
        $event = (new Event)->forceFill(['id' => 123, 'name' => 'Koncert']);
        $notification = new EventInterestRecorded($event);
        $recipient = Notification::route('mail', $user->email);
        Notification::sendNow($recipient, $notification, [BellChannel::class]);
        $item = $user->notifications()->sole();
        $this->assertStringContainsString('Koncert', $item->data['message']);
        $this->assertStringContainsString('/123/', $item->data['link']);
        $this->assertNull($item->read_at);
        $this->assertContains('mail', $notification->via($recipient));
        $this->assertContains(BellChannel::class, $notification->via($recipient));

        $item->markAsRead();
        $notification->id = $item->id;
        app(BellChannel::class)->send($recipient, $notification);
        $this->assertSame(1, $user->notifications()->count());
        $this->assertNotNull($item->fresh()->read_at);
    }

    public function test_unknown_and_unverified_email_recipients_do_not_receive_bell_items(): void
    {
        $user = $this->user();
        $user->forceFill(['email_verified_at' => null])->saveQuietly();
        $event = (new Event)->forceFill(['id' => 123, 'name' => 'Koncert']);
        foreach ([$user->email, 'unknown@example.test'] as $email) {
            Notification::sendNow(Notification::route('mail', $email), new EventInterestRecorded($event), [BellChannel::class]);
        }
        $this->assertDatabaseCount('notifications', 0);
    }
}
