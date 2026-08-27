<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * /cron/schedule-run je jediná cesta, ako sa na tomto hostingu spustí scheduler
 * — chráni ho len token v query stringu a jeho jediný strážca je tento test.
 */
class WebcronEndpointTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_rejects_a_missing_or_wrong_token(): void
    {
        config(['app.cron_secret' => 'tajny-token']);

        $this->get('/cron/schedule-run')->assertForbidden();
        $this->get('/cron/schedule-run?token=zle')->assertForbidden();
    }

    /**
     * Bez tejto poistky by `hash_equals('', '')` pustilo dnu hocikoho — a to
     * práve na inštalácii, ktorá si CRON_SECRET zabudla nastaviť.
     */
    public function test_it_rejects_everything_when_the_secret_is_not_configured(): void
    {
        config(['app.cron_secret' => null]);

        $this->get('/cron/schedule-run')->assertForbidden();
        $this->get('/cron/schedule-run?token=')->assertForbidden();
    }

    public function test_it_runs_the_scheduler_with_a_valid_token(): void
    {
        config(['app.cron_secret' => 'tajny-token', 'services.cron_heartbeat.url' => '']);

        // Telo odpovede je jediný pohľad na beh, ktorý webcron dostane. Príkazy
        // sa volajú v procese a každé vnorené Artisan::call() prepisuje
        // Artisan::output(), takže výpis musí prísť z vlastného bufferu.
        $this->get('/cron/schedule-run?token=tajny-token')
            ->assertOk()
            ->assertSee('app:ai-detector');
    }

    public function test_it_pings_the_watchdog_after_a_successful_run(): void
    {
        Http::fake();
        config([
            'app.cron_secret' => 'tajny-token',
            'services.cron_heartbeat.url' => 'https://hc.example/ping/abc',
        ]);

        $this->get('/cron/schedule-run?token=tajny-token')->assertOk();

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://hc.example/ping/abc');
    }

    public function test_it_does_not_ping_when_the_token_is_wrong(): void
    {
        Http::fake();
        config([
            'app.cron_secret' => 'tajny-token',
            'services.cron_heartbeat.url' => 'https://hc.example/ping/abc',
        ]);

        $this->get('/cron/schedule-run?token=zle')->assertForbidden();

        Http::assertNothingSent();
    }

    /**
     * Watchdog je pomocná infraštruktúra — jeho výpadok nesmie zhodiť scheduler.
     */
    public function test_a_failing_ping_does_not_break_the_cron_run(): void
    {
        Http::fake(['hc.example/*' => Http::response('nope', 500)]);
        config([
            'app.cron_secret' => 'tajny-token',
            'services.cron_heartbeat.url' => 'https://hc.example/ping/abc',
        ]);

        $this->get('/cron/schedule-run?token=tajny-token')->assertOk();
    }
}
