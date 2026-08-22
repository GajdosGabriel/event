<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dlaždice prehľadu („Práve prebieha", „Dnes v programe", …) odkazujú do
 * výpisu s `?phase=`. Test drží obe strany spolu: filter musí vrátiť presne
 * tie podujatia, ktoré sa do čísla rátali.
 */
class EventPhaseFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $running;

    private Event $laterToday;

    private Event $nextWeek;

    private Event $farFuture;

    private Event $past;

    protected function setUp(): void
    {
        parent::setUp();

        // Bez zmrazeného času by „dnes" pri behu tesne pred polnocou padlo
        // na iný deň než termíny, ktoré test zakladá.
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');

        $this->running = $this->event(now()->subHour(), now()->addHour());
        $this->laterToday = $this->event(now()->addHours(3), now()->addHours(5));
        $this->nextWeek = $this->event(now()->addDays(3), now()->addDays(3)->addHours(2));
        $this->farFuture = $this->event(now()->addDays(30), now()->addDays(30)->addHours(2));
        $this->past = $this->event(now()->subDays(3), now()->subDays(3)->addHours(2));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_running_lists_only_events_in_progress(): void
    {
        $this->assertPhaseReturns('running', [$this->running]);
    }

    public function test_today_lists_only_events_starting_today(): void
    {
        $this->assertPhaseReturns('today', [$this->running, $this->laterToday]);
    }

    public function test_next7d_lists_only_events_starting_within_a_week(): void
    {
        $this->assertPhaseReturns('next7d', [$this->laterToday, $this->nextWeek]);
    }

    public function test_active_lists_everything_that_has_not_finished_yet(): void
    {
        $this->assertPhaseReturns('active', [$this->running, $this->laterToday, $this->nextWeek, $this->farFuture]);
    }

    public function test_past_lists_only_finished_events(): void
    {
        $this->assertPhaseReturns('past', [$this->past]);
    }

    /** Podujatie bez konca beží, kým sa nezmení deň — inak by „aktívne" nikdy nezhaslo. */
    public function test_open_ended_event_counts_as_active_only_on_its_own_day(): void
    {
        $openToday = $this->event(now()->subHours(2), null);
        $openYesterday = $this->event(now()->subDay(), null);

        $this->assertPhaseReturns('active', [$this->running, $this->laterToday, $this->nextWeek, $this->farFuture, $openToday]);
        $this->assertPhaseReturns('past', [$this->past, $openYesterday]);
    }

    public function test_unknown_phase_is_rejected(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/events?phase=whenever')
            ->assertStatus(422);
    }

    /** Filter je čisto časový — stav si výpis pýta samostatne, ako dlaždice. */
    public function test_phase_combines_with_status(): void
    {
        $draftToday = $this->event(now()->addHours(4), now()->addHours(6), ModelStatus::Draft);

        $this->assertPhaseReturns('today', [$this->running, $this->laterToday, $draftToday]);
        $this->assertPhaseReturns('today', [$draftToday], '&status=' . ModelStatus::Draft->value);
    }

    private function event(Carbon $startAt, ?Carbon $endAt, ModelStatus $status = ModelStatus::Published): Event
    {
        return Event::factory()->create([
            'user_id' => $this->admin->id,
            'status' => $status->value,
            'published_at' => $status === ModelStatus::Published ? now() : null,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }

    /** @param  array<int, Event>  $expected */
    private function assertPhaseReturns(string $phase, array $expected, string $extraQuery = ''): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/events?per_page=100&phase={$phase}{$extraQuery}");

        $response->assertOk();

        $this->assertSame(
            collect($expected)->pluck('id')->sort()->values()->all(),
            collect($response->json('data'))->pluck('id')->sort()->values()->all(),
            "Filter phase={$phase} vrátil iné podujatia, než ktoré do okna patria.",
        );
    }
}
