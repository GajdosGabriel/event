<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tlačidlo „Kúpiť lístok" / „Rezervovať" na karte podujatia — `ticket_cta`
 * vo verejných výpisoch (Event::ticketCta()).
 */
class PublicEventTicketCtaTest extends TestCase
{
    use RefreshDatabase;

    private function publishedEvent(array $attributes = []): Event
    {
        // Pevné časy, nie faker — náhodný termín vie trafiť posun času (DST).
        return Event::factory()->create(array_merge([
            'status' => ModelStatus::Published->value,
            'published_at' => now()->subWeek(),
            'start_at' => now()->addWeek()->setTime(18, 0),
            'end_at' => now()->addWeek()->setTime(21, 0),
            'registration_deadline_at' => null,
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    private function ctaFromIndex(Event $event): mixed
    {
        // Popisok ide v jazyku požiadavky — SPA ho posiela v X-Locale.
        $response = $this->withHeader('X-Locale', 'sk')->getJson('/api/events?list=all')->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $event->id);
        $this->assertNotNull($row, 'Podujatie chýba vo verejnom výpise.');

        return $row['ticket_cta'];
    }

    #[Test]
    public function paid_ticket_type_offers_buy(): void
    {
        $event = $this->publishedEvent();
        $event->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 1500, 'is_active' => true]);

        $this->assertSame(
            ['kind' => 'buy', 'label' => 'Kúpiť lístok'],
            $this->ctaFromIndex($event),
        );
    }

    #[Test]
    public function free_ticket_type_offers_reservation(): void
    {
        $event = $this->publishedEvent();
        $event->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        $this->assertSame(
            ['kind' => 'reserve', 'label' => 'Rezervovať'],
            $this->ctaFromIndex($event),
        );
    }

    #[Test]
    public function inactive_types_and_passed_deadline_offer_nothing(): void
    {
        $inactive = $this->publishedEvent();
        $inactive->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 1500, 'is_active' => false]);

        $closed = $this->publishedEvent(['registration_deadline_at' => now()->subHour()]);
        $closed->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        $this->assertNull($this->ctaFromIndex($inactive));
        $this->assertNull($this->ctaFromIndex($closed));
    }

    #[Test]
    public function canal_event_list_carries_the_cta_and_hides_helper_flags(): void
    {
        $canal = Canal::factory()->create();
        $event = $this->publishedEvent(['canal_id' => $canal->id]);
        $event->ticketTypes()->create(['name' => 'Vstupenka', 'price_amount' => 0, 'is_active' => true]);

        $row = collect($this->getJson("/api/canals/{$canal->id}/events")->assertOk()->json())
            ->firstWhere('id', $event->id);

        $this->assertSame('reserve', $row['ticket_cta']['kind']);

        $indexRow = collect($this->getJson('/api/events?list=all')->json('data'))->firstWhere('id', $event->id);
        $this->assertArrayNotHasKey('has_active_ticket_types', $indexRow);
        $this->assertArrayNotHasKey('has_paid_ticket_types', $indexRow);
        $this->assertTrue($indexRow['tickets_enabled']);
    }
}
