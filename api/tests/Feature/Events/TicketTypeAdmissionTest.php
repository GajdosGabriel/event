<?php

namespace Tests\Feature\Events;

use App\Models\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class TicketTypeAdmissionTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Zaisti, že práva na lístky existujú (perzistentná testovacia DB môže mať starší set).
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function order_across_two_types_creates_individual_admissions_with_distinct_qr(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['tickets_enabled' => true]);
        $standard = $this->futureEvent->ticketTypes()->create(['name' => 'Standard', 'price_amount' => 0, 'is_active' => true]);
        $vip = $this->futureEvent->ticketTypes()->create(['name' => 'VIP', 'price_amount' => 1500, 'is_active' => true]);

        $response = $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Skupina',
            'holder_email' => 'grp@example.com',
            'items' => [
                ['ticket_type_id' => $standard->id, 'quantity' => 2],
                ['ticket_type_id' => $vip->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('admissions_total', 3);

        $order = Ticket::query()->where('holder_email', 'grp@example.com')->firstOrFail();

        // 3 samostatné vstupenky, každá s vlastným QR tokenom.
        $this->assertSame(3, $order->admissions()->count());
        $this->assertSame(3, $order->admissions()->pluck('qr_token')->unique()->count());

        // Objednávka je platená (VIP má cenu) – celková cena je súčet.
        $this->assertSame(1500, (int) $order->price_amount);
    }

    #[Test]
    public function per_type_capacity_is_enforced(): void
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update(['tickets_enabled' => true]);
        $type = $this->futureEvent->ticketTypes()->create(['name' => 'Limit', 'price_amount' => 0, 'capacity' => 2, 'is_active' => true]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Priplno',
            'holder_email' => 'priplno@example.com',
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 3]],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('tickets', ['holder_email' => 'priplno@example.com']);
    }

    #[Test]
    public function checking_in_one_admission_leaves_others_valid_and_blocks_reuse(): void
    {
        // Verejná rezervácia 2 miest.
        $this->app['auth']->forgetGuards();
        $this->futureEvent->update(['tickets_enabled' => true]);
        $type = $this->futureEvent->ticketTypes()->create(['name' => 'Std', 'price_amount' => 0, 'is_active' => true]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Dvaja',
            'holder_email' => 'dvaja@example.com',
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 2]],
        ])->assertStatus(201);

        $order = Ticket::query()->where('holder_email', 'dvaja@example.com')->firstOrFail();
        $admissions = $order->admissions()->orderBy('id')->get();
        $first = $admissions[0];
        $second = $admissions[1];

        // Personál s právom check-inu.
        $this->user->givePermissionTo(['ticket.view', 'ticket.checkin', 'ticket.update']);
        $this->actingAs($this->user, 'sanctum');

        $this->postJson('/api/dashboard/tickets/checkin', ['qr_token' => $first->qr_token])
            ->assertOk()
            ->assertJsonPath('status', 'checked_in');

        // Opätovné naskenovanie tej istej vstupenky.
        $this->postJson('/api/dashboard/tickets/checkin', ['qr_token' => $first->qr_token])
            ->assertOk()
            ->assertJsonPath('status', 'already_checked_in');

        // Druhá vstupenka objednávky ostáva nepoužitá.
        $this->assertNull($second->fresh()->checked_in_at);

        // Undo vráti check-in.
        $this->postJson('/api/dashboard/tickets/checkin/undo', ['admission_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('status', 'reverted');

        $this->assertNull($first->fresh()->checked_in_at);
    }

    #[Test]
    public function checkin_stats_report_arrivals(): void
    {
        $this->app['auth']->forgetGuards();
        $this->futureEvent->update(['tickets_enabled' => true]);
        $type = $this->futureEvent->ticketTypes()->create(['name' => 'Std', 'price_amount' => 0, 'is_active' => true]);

        $this->postJson("/api/events/{$this->futureEvent->id}/tickets", [
            'holder_name' => 'Traja',
            'holder_email' => 'traja@example.com',
            'items' => [['ticket_type_id' => $type->id, 'quantity' => 3]],
        ])->assertStatus(201);

        $order = Ticket::query()->where('holder_email', 'traja@example.com')->firstOrFail();
        $first = $order->admissions()->orderBy('id')->first();

        $this->user->givePermissionTo(['ticket.view', 'ticket.checkin']);
        $this->actingAs($this->user, 'sanctum');

        $this->postJson('/api/dashboard/tickets/checkin', ['qr_token' => $first->qr_token])->assertOk();

        $this->getJson("/api/dashboard/events/{$this->futureEvent->id}/checkin-stats")
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('arrived', 1)
            ->assertJsonPath('remaining', 2);
    }
}
