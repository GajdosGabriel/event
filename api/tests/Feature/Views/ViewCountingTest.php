<?php

namespace Tests\Feature\Views;

use App\Enums\ModelStatus;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class ViewCountingTest extends EventSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // EventFactory losuje stav aj dátumy — verejný detail ich nekontroluje,
        // ale ostatné testy áno, tak nech je východisko deterministické.
        $this->futureEvent->update(['status' => ModelStatus::Published->value]);
    }

    /**
     * UserSetupTest prihlasuje používateľa v setUp(), takže bez tohto by bola
     * „verejná" požiadavka autentifikovaná ako majiteľ kanála — ViewRecorder by
     * ju vyhodnotil ako kontrolu vlastného záznamu a nezapočítal.
     */
    private function asGuest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Návštevník s bežným prehliadačovým user-agentom. ViewRecorder počíta
     * pseudonym z IP a user-agenta, takže obe musia byť v teste stabilné.
     */
    private function visit(?Event $event = null, array $headers = [], bool $asGuest = true): \Illuminate\Testing\TestResponse
    {
        $event ??= $this->futureEvent;

        if ($asGuest) {
            $this->asGuest();
        }

        return $this->withHeaders(array_merge([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120',
        ], $headers))->getJson('/api/events/' . $event->id);
    }

    private function viewsOf(?Event $event = null): int
    {
        return (int) ($event ?? $this->futureEvent)->fresh()->views_count;
    }

    #[Test]
    public function a_public_visit_is_counted(): void
    {
        $this->visit()->assertOk();

        $this->assertSame(1, $this->viewsOf());
        $this->assertDatabaseCount('views', 1);
    }

    #[Test]
    public function repeated_visits_on_the_same_day_are_not_counted(): void
    {
        $this->visit()->assertOk();
        $this->visit()->assertOk();
        $this->visit()->assertOk();

        // Obnovenie stránky nesmie štatistiku nafukovať.
        $this->assertSame(1, $this->viewsOf());
        $this->assertDatabaseCount('views', 1);
    }

    #[Test]
    public function the_same_visitor_counts_again_the_next_day(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $this->visit()->assertOk();

        Carbon::setTestNow('2026-08-02 10:00:00');
        $this->visit()->assertOk();

        $this->assertSame(2, $this->viewsOf());

        Carbon::setTestNow();
    }

    #[Test]
    public function different_visitors_are_counted_separately(): void
    {
        $this->visit()->assertOk();
        // Iný user-agent = iný pseudonym.
        $this->visit(headers: ['User-Agent' => 'Mozilla/5.0 (Macintosh) Safari/17'])->assertOk();

        $this->assertSame(2, $this->viewsOf());
    }

    #[Test]
    public function bots_are_not_counted(): void
    {
        $this->visit(headers: ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->assertOk();
        $this->visit(headers: ['User-Agent' => 'facebookexternalhit/1.1'])->assertOk();

        $this->assertSame(0, $this->viewsOf());
        $this->assertDatabaseCount('views', 0);
    }

    #[Test]
    public function a_missing_user_agent_is_not_counted(): void
    {
        $this->asGuest();

        $this->withHeaders(['User-Agent' => ''])
            ->getJson('/api/events/' . $this->futureEvent->id)
            ->assertOk();

        $this->assertSame(0, $this->viewsOf());
    }

    #[Test]
    public function the_organizer_does_not_inflate_their_own_count(): void
    {
        // Prihlásený člen kanála má na podujatie právo `view` — jeho návšteva
        // je kontrola vlastného záznamu, nie záujem publika.
        $this->visit(asGuest: false)->assertOk();

        $this->assertSame(0, $this->viewsOf());
    }

    #[Test]
    public function views_count_is_hidden_from_the_public(): void
    {
        $this->futureEvent->forceFill(['views_count' => 42])->save();

        $payload = $this->visit()->assertOk()->json();

        $this->assertArrayNotHasKey('views_count', $payload);
    }

    #[Test]
    public function views_count_is_visible_in_the_dashboard(): void
    {
        $this->futureEvent->forceFill(['views_count' => 42])->save();

        $this->actingAs($this->user, 'sanctum');

        $row = collect($this->getJson('/api/dashboard/events?per_page=100')->assertOk()->json('data'))
            ->firstWhere('id', $this->futureEvent->id);

        $this->assertSame(42, $row['views_count']);
    }

    #[Test]
    public function views_count_is_absent_from_the_public_listing(): void
    {
        $this->futureEvent->forceFill(['views_count' => 42])->save();

        $this->asGuest();

        $rows = $this->getJson('/api/events?list=all&per_page=100')->assertOk()->json('data');

        foreach ($rows as $row) {
            $this->assertArrayNotHasKey('views_count', $row);
        }
    }

    #[Test]
    public function prune_removes_only_old_rows(): void
    {
        $this->visit()->assertOk();

        DB::table('views')->insert([
            'viewable_type' => Event::class,
            'viewable_id' => $this->futureEvent->id,
            'visitor_hash' => str_repeat('a', 64),
            'viewed_on' => now()->subDays(120)->toDateString(),
            'created_at' => now()->subDays(120),
        ]);

        $this->assertDatabaseCount('views', 2);

        $this->artisan('app:views-prune')->assertSuccessful();

        // Mazanie riadkov nesmie siahnuť na trvalé počítadlo.
        $this->assertDatabaseCount('views', 1);
        $this->assertSame(1, $this->viewsOf());
    }
}
