<?php

namespace Tests\Feature\Messages;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketCreated;
use App\Notifications\SupportTicketReplied;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Podpora v sekcii Správy: používateľ píše super-adminom, tí odpovedajú.
 *
 * Najviac záleží na tom, aby cudzie vlákno nebolo vidieť, aby sa admin
 * používateľovi neukázal menom a aby odznaky „nové" sedeli na oboch stranách.
 */
class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->member = User::factory()->create(['email_verified_at' => now()]);
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('super-admin');
    }

    private function open(array $overrides = []): SupportTicket
    {
        $response = $this->actingAs($this->member, 'sanctum')->postJson('/api/dashboard/support/tickets', array_merge([
            'category' => 'problem',
            'subject' => 'Nevidím svoje podujatie',
            'body' => 'Po uložení podujatie zmizlo zo zoznamu.',
            'page_url' => '/dashboard/events',
        ], $overrides))->assertCreated();

        return SupportTicket::query()->findOrFail($response->json('id'));
    }

    #[Test]
    public function user_opens_a_ticket_and_admins_are_notified(): void
    {
        Notification::fake();

        $ticket = $this->open();

        $this->assertSame('open', $ticket->status->value);
        $this->assertCount(1, $ticket->messages);
        Notification::assertSentTo($this->admin, SupportTicketCreated::class);
        Notification::assertNotSentTo($this->member, SupportTicketCreated::class);
    }

    #[Test]
    public function foreign_ticket_is_not_found_for_other_users(): void
    {
        $ticket = $this->open();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/dashboard/support/tickets/{$ticket->id}")
            ->assertNotFound();

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/dashboard/support/tickets?all=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function admin_sees_all_tickets_with_counts(): void
    {
        $this->open();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/support/tickets?all=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.counts.open', 1)
            ->assertJsonPath('data.0.unread', true)
            ->assertJsonPath('data.0.user_email', $this->member->email);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/dashboard/support/summary')
            ->assertJsonPath('inbox', 1);
    }

    #[Test]
    public function admin_reply_is_anonymous_to_user_and_marks_it_unread(): void
    {
        Notification::fake();
        $ticket = $this->open();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/dashboard/support/tickets/{$ticket->id}/messages", ['body' => 'Pozrieme sa na to.'])
            ->assertOk()
            ->assertJsonPath('status.value', 'answered');

        Notification::assertSentTo($this->member, SupportTicketReplied::class);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/dashboard/messages/unread-count')
            ->assertJsonPath('support', 1);

        $thread = $this->actingAs($this->member, 'sanctum')
            ->getJson("/api/dashboard/support/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('messages.1.author', __('support.staff_name'))
            ->assertJsonMissingPath('user_email');

        $this->assertStringNotContainsString($this->admin->email, $thread->getContent());

        // Otvorením je odpoveď prečítaná.
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/dashboard/support/summary')
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('inbox', null);
    }

    #[Test]
    public function user_follow_up_goes_to_the_admin_who_answered_and_reopens(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);
        $other->assignRole('super-admin');
        $ticket = $this->open();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/dashboard/support/tickets/{$ticket->id}/messages", ['body' => 'Skúste obnoviť stránku.']);
        $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/dashboard/support/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertJsonPath('status.value', 'closed');

        Notification::fake();

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/dashboard/support/tickets/{$ticket->id}/messages", ['body' => 'Stále to nejde.'])
            ->assertOk()
            ->assertJsonPath('status.value', 'open');

        Notification::assertSentTo($this->admin, SupportTicketReplied::class);
        Notification::assertNotSentTo($other, SupportTicketReplied::class);
    }

    #[Test]
    public function foreign_canal_is_not_attached_as_context(): void
    {
        $ticket = $this->open(['canal_id' => 999999]);

        $this->assertNull($ticket->canal_id);
    }
}
