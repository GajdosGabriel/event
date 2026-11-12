<?php

namespace Tests\Feature\OpenAI;

use App\Models\AiUsage;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use App\Services\OpenAI\AiUsageRecorder;
use App\Services\OpenAI\ChatGPT;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Každé volanie OpenAI sa zapíše do ai_usages s operáciou, tokenmi a cenou —
 * podklad pre prehľad spotreby v administrácii.
 */
class AiUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('openai.api_key', 'test-key');
        Sleep::fake();
    }

    /**
     * Mimo testov Laravel preposiela udalosti príkazov (CommandStarting…) vždy,
     * v testoch nie — bez nich AiUsageSource nevie, ktorý príkaz beží.
     */
    private function routeCommandEvents(): void
    {
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        // Artisan aplikácia si dispatcher berie pri vzniku.
        $kernel->setArtisan(null);
    }

    private function fakeOpenAi(string $content, array $usage = ['prompt_tokens' => 1000, 'completion_tokens' => 200]): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'model' => 'gpt-4o-mini-2024-07-18',
            'choices' => [['finish_reason' => 'stop', 'message' => ['content' => $content]]],
            'usage' => $usage + ['total_tokens' => array_sum($usage)],
        ])]);
    }

    #[Test]
    public function successful_call_records_feature_tokens_and_cost(): void
    {
        $this->fakeOpenAi(json_encode(['improved_text' => '<p>Lepší text</p>', 'changes_summary' => 'Úprava']));

        (new ChatGPT)->extractTextEdit('Pôvodný text podujatia.', ['grammar']);

        $usage = AiUsage::query()->sole();
        $this->assertSame('text_edit', $usage->feature);
        $this->assertSame('gpt-4o-mini-2024-07-18', $usage->model);
        $this->assertSame(1000, $usage->prompt_tokens);
        $this->assertSame(200, $usage->completion_tokens);
        // 1000 × 0,15 + 200 × 0,60 za milión tokenov (dátum verzie sa odreže).
        $this->assertEqualsWithDelta(0.00027, $usage->cost_usd, 0.000001);
        $this->assertTrue($usage->success);
    }

    #[Test]
    public function failed_call_is_recorded_as_unsuccessful(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'invalid_request']], 400)]);

        try {
            (new ChatGPT)->extractTextEdit('Text.', ['grammar']);
        } catch (\RuntimeException) {
        }

        $usage = AiUsage::query()->sole();
        $this->assertFalse($usage->success);
        $this->assertSame(0, $usage->prompt_tokens);
    }

    #[Test]
    public function subject_assigns_canal(): void
    {
        $this->fakeOpenAi(json_encode(['improved_text' => '<p>Text</p>', 'changes_summary' => 'Úprava']));
        $canal = Canal::factory()->create();

        app(AiUsageRecorder::class)->within($canal, fn () => (new ChatGPT)->extractTextEdit('Text kanála.', ['grammar']));

        $usage = AiUsage::query()->sole();
        $this->assertSame($canal->id, (int) $usage->canal_id);
        $this->assertSame('Canal', $usage->subject_type);
    }

    #[Test]
    public function source_is_innermost_command_with_import_host(): void
    {
        $this->routeCommandEvents();
        $this->fakeOpenAi(json_encode(['improved_text' => '<p>Text</p>', 'changes_summary' => 'Úprava']));

        Artisan::command('test:ai-inner {--url=}', fn () => (new ChatGPT)->extractTextEdit('Vnútri.', ['grammar']));
        // Ako schedule:run: vonkajší príkaz púšťa vnútorný v tom istom procese.
        Artisan::command('test:ai-outer', function () {
            Artisan::call('test:ai-inner', ['--url' => 'https://www.vyveska.sk/zoznam-podujati/']);
            (new ChatGPT)->extractTextEdit('Vonku.', ['grammar']);
        });

        Artisan::call('test:ai-outer');

        $this->assertSame(
            ['test:ai-inner vyveska.sk', 'test:ai-outer'],
            AiUsage::query()->orderBy('id')->pluck('source')->all(),
        );
    }

    #[Test]
    public function subject_set_by_one_command_does_not_leak_into_the_next(): void
    {
        $this->routeCommandEvents();
        $this->fakeOpenAi(json_encode(['improved_text' => '<p>Text</p>', 'changes_summary' => 'Úprava']));
        $canal = Canal::factory()->create();

        Artisan::command('test:ai-with-subject', function () use ($canal) {
            app(AiUsageRecorder::class)->setSubject($canal);
            (new ChatGPT)->extractTextEdit('S predmetom.', ['grammar']);
        });
        Artisan::command('test:ai-without-subject', fn () => (new ChatGPT)->extractTextEdit('Bez predmetu.', ['grammar']));

        Artisan::call('test:ai-with-subject');
        Artisan::call('test:ai-without-subject');

        $this->assertSame([$canal->id, null], AiUsage::query()->orderBy('id')->pluck('canal_id')->map(fn ($id) => $id === null ? null : (int) $id)->all());
    }

    #[Test]
    public function collected_calls_get_subject_once_it_exists(): void
    {
        $this->fakeOpenAi(json_encode(['improved_text' => '<p>Text</p>', 'changes_summary' => 'Úprava']));
        $otherCanal = Canal::factory()->create();
        $recorder = app(AiUsageRecorder::class);

        $event = $recorder->collectUnassigned(function () use ($recorder, $otherCanal) {
            (new ChatGPT)->extractTextEdit('Článok.', ['grammar']);
            // Volanie s vlastným predmetom sa neprepisuje.
            $recorder->within($otherCanal, fn () => (new ChatGPT)->extractTextEdit('Kanál.', ['grammar']));

            $event = Event::factory()->create(['user_id' => User::factory()]);
            $recorder->assignCollected($event);

            return $event;
        });

        // Mimo zberu sa už nič dodatočne nepriraďuje.
        (new ChatGPT)->extractTextEdit('Potom.', ['grammar']);

        $rows = AiUsage::query()->orderBy('id')->get();
        $this->assertSame(['Event', 'Canal', null], $rows->pluck('subject_type')->all());
        $this->assertSame($event->id, (int) $rows[0]->subject_id);
        $this->assertSame($event->canal_id, (int) $rows[0]->canal_id);
        $this->assertSame($otherCanal->id, (int) $rows[1]->canal_id);
    }

    #[Test]
    public function admin_overview_sums_usage_by_feature(): void
    {
        AiUsage::query()->create(['feature' => 'copywriter', 'model' => 'gpt-4o-mini', 'prompt_tokens' => 500, 'completion_tokens' => 100, 'cost_usd' => 0.1]);
        AiUsage::query()->create(['feature' => 'copywriter', 'model' => 'gpt-4o-mini', 'prompt_tokens' => 500, 'completion_tokens' => 100, 'cost_usd' => 0.2]);
        AiUsage::query()->create(['feature' => 'tags', 'model' => 'gpt-4o-mini', 'prompt_tokens' => 10, 'completion_tokens' => 5, 'cost_usd' => 0.01, 'success' => false]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/ai-usage?days=30')->assertOk();

        $response->assertJsonPath('data.totals.period.calls', 3);
        $response->assertJsonPath('data.totals.period.failed', 1);
        $response->assertJsonPath('data.byFeature.0.key', 'copywriter');
        $response->assertJsonPath('data.byFeature.0.calls', 2);
        $response->assertJsonPath('data.byFeature.0.promptTokens', 1000);
        $this->assertEqualsWithDelta(0.31, $response->json('data.totals.period.costUsd'), 0.0001);
    }

    #[Test]
    public function admin_overview_paginates_recent_calls(): void
    {
        foreach (range(1, 21) as $i) {
            AiUsage::query()->create(['feature' => "feature-{$i}", 'model' => 'gpt-4o-mini', 'prompt_tokens' => 1, 'completion_tokens' => 1, 'cost_usd' => 0.01]);
        }

        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/ai-usage')
            ->assertOk()
            ->assertJsonCount(20, 'data.recent')
            ->assertJsonCount(21, 'data.byFeature')
            ->assertJsonPath('data.recentMeta.lastPage', 2)
            ->assertJsonPath('data.recentMeta.total', 21);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/ai-usage?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.recent')
            ->assertJsonPath('data.recent.0.feature', 'feature-1')
            ->assertJsonPath('data.recentMeta.currentPage', 2)
            ->assertJsonPath('data.totals.period.calls', 21);
    }

    #[Test]
    public function regular_user_cannot_see_overview(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/ai-usage')
            ->assertForbidden();
    }
}
