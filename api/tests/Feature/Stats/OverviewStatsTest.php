<?php

namespace Tests\Feature\Stats;

use App\Enums\AdmissionStatus;
use App\Enums\ModelStatus;
use App\Enums\TicketPaymentStatus;
use App\Models\Admission;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class OverviewStatsTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app['auth']->forgetGuards();
    }

    /**
     * EventFactory prepisuje canal_id podľa priradeného miesta (afterMaking),
     * takže samotné `canal_id` v atribútoch sa zahodí. Podujatie do kanála
     * používateľa sa preto zakladá cez jeho miesto.
     */
    private function ownEvent(array $attributes = []): Event
    {
        return Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'venue_id' => $this->futureEvent->venue_id,
            'user_id' => $this->user->id,
            ...$attributes,
        ]);
    }

    #[Test]
    public function dashboard_overview_returns_the_full_payload(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('scope', 'dashboard')
            ->assertJsonStructure([
                'generated_at',
                'trend_days',
                'periods' => ['day', 'week', 'month', 'all'],
                'totals' => ['events' => ['total', 'published', 'active', 'today', 'next_7d'], 'venues', 'canals'],
                'trend',
                'ticketing' => ['orders', 'seats', 'capacity', 'attendance'],
                'statuses',
                'sources',
                'attention',
                'top_events',
                'upcoming',
                'top_canals',
            ]);

        $this->assertCount(30, $response->json('trend'));
    }

    #[Test]
    public function guests_cannot_read_the_dashboard_overview(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    #[Test]
    public function admin_overview_is_limited_to_super_admins(): void
    {
        $this->actingAs($this->user, 'sanctum');
        $this->getJson('/api/admin')->assertForbidden();

        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $this->getJson('/api/admin')
            ->assertOk()
            ->assertJsonPath('scope', 'admin')
            // Používatelia sú metrika, ktorá dáva zmysel len celosystémovo.
            ->assertJsonStructure(['users' => ['total', 'verified', 'blocked', 'active_30d']]);
    }

    #[Test]
    public function dashboard_overview_counts_only_events_from_own_canals(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $own = $this->getJson('/api/dashboard')->json('totals.events.total');

        $this->actingAs($this->userSuperAdmin, 'sanctum');
        $all = $this->getJson('/api/admin')->json('totals.events.total');

        // V setUpe existuje aj podujatie cudzieho kanála — dashboard ho vidieť nesmie.
        $this->assertSame(2, $own);
        $this->assertSame(3, $all);
    }

    #[Test]
    public function period_metrics_compare_against_the_previous_window(): void
    {
        // Čas mrazíme na dnešok napoludnie: porovnávacie okno pre „Dnes" je
        // vtedy včerajšok od 12:00 do polnoci, takže test nezávisí od toho,
        // o ktorej hodine beží. Podujatia zo setUpu zostávajú v dnešku.
        $this->travelTo(CarbonImmutable::now()->startOfDay()->addHours(12));

        // V setUpe už dve podujatia dneškom vznikli — pridáme dve dnes a dve
        // včera večer, takže dnešok má 4 oproti 2 z predošlého dňa → +100 %.
        $this->ownEvent(['created_at' => now()]);
        $this->ownEvent(['created_at' => now()]);
        $this->ownEvent(['created_at' => now()->subHours(18)]);
        $this->ownEvent(['created_at' => now()->subHours(18)]);

        $this->actingAs($this->user, 'sanctum');
        $metric = $this->getJson('/api/dashboard')->json('periods.day.metrics.events');

        $this->assertSame(4, $metric['value']);
        $this->assertSame(2, $metric['previous']);
        // round() vracia float, JSON ho prenesie ako celé číslo — porovnávame hodnotu.
        $this->assertEquals(100, $metric['change']);
    }

    #[Test]
    public function change_stays_null_when_there_is_nothing_to_compare_against(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $metrics = $this->getJson('/api/dashboard')->json('periods');

        // Delenie nulou nesmie skončiť ako „+100 %"…
        $this->assertNull($metrics['day']['metrics']['tickets']['change']);
        // …a obdobie „Celkovo" nemá s čím porovnávať vôbec.
        $this->assertNull($metrics['all']['metrics']['events']['previous']);
        $this->assertNull($metrics['all']['metrics']['events']['change']);
    }

    #[Test]
    public function ticketing_block_sums_seats_capacity_and_paid_revenue(): void
    {
        $type = TicketType::query()->create([
            'event_id' => $this->futureEvent->id,
            'name' => 'Vstupenka',
            'price_amount' => 1000,
            'capacity' => 10,
            'is_active' => true,
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $this->futureEvent->id,
            'holder_name' => 'Gabriel',
            'holder_email' => 'gabriel@example.com',
            'quantity' => 2,
            'price_amount' => 2000,
            'price_currency' => 'EUR',
            'payment_status' => TicketPaymentStatus::Paid->value,
        ]);

        Admission::query()->create([
            'ticket_id' => $ticket->id,
            'ticket_type_id' => $type->id,
            'event_id' => $this->futureEvent->id,
            'status' => AdmissionStatus::Valid->value,
        ]);
        Admission::query()->create([
            'ticket_id' => $ticket->id,
            'ticket_type_id' => $type->id,
            'event_id' => $this->futureEvent->id,
            'status' => AdmissionStatus::Valid->value,
        ]);

        $this->actingAs($this->user, 'sanctum');
        $ticketing = $this->getJson('/api/dashboard')->json('ticketing');

        $this->assertSame(2000, $ticketing['orders']['revenue_paid']);
        $this->assertSame(2, $ticketing['seats']['valid']);
        $this->assertSame(10, $ticketing['capacity']['seats']);
        $this->assertSame(2, $ticketing['capacity']['sold']);
        $this->assertEquals(20, $ticketing['capacity']['rate']);
    }

    #[Test]
    public function attention_reports_stale_drafts_and_hides_resolved_items(): void
    {
        $this->ownEvent([
            'status' => ModelStatus::Draft->value,
            'created_at' => now()->subDays(30),
        ]);

        $this->actingAs($this->user, 'sanctum');
        $attention = collect($this->getJson('/api/dashboard')->json('attention'));

        $this->assertSame(1, $attention->firstWhere('key', 'stale_drafts')['count']);
        // Položky s nulou sa nezobrazujú — zoznam má byť to, čo treba riešiť.
        $this->assertTrue($attention->every(fn (array $item) => $item['count'] > 0));
    }
}
