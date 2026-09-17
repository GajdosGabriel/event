<?php

namespace Tests\Feature\OpenAI;

use App\Models\AiUsage;
use App\Models\Canal;
use App\Models\User;
use App\Services\OpenAI\AiUsageRecorder;
use App\Services\OpenAI\ChatGPT;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    public function regular_user_cannot_see_overview(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/ai-usage')
            ->assertForbidden();
    }
}
