<?php

namespace Tests\Feature\OpenAI;

use App\Models\AiUsage;
use App\Models\User;
use App\Services\Profiles\ProfileEnricher;
use App\Services\Profiles\ProfileResearch;
use App\Support\AppSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Limity AI vyhľadávania zo stránky Spotreba AI — uložené hodnoty prepisujú
 * config, bez nich platí config.
 */
class AiLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'openai.api_key' => 'test-key',
            'profile_enrichment.enabled' => true,
            'profile_enrichment.monthly_limit_usd' => 1,
            'canals.emails.discovery.ai' => true,
        ]);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        return $admin;
    }

    #[Test]
    public function show_returns_config_values_and_budget(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
        AiUsage::query()->create(['feature' => ProfileResearch::FEATURE, 'model' => 'gpt-4.1-mini', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'cost_usd' => 0.2]);
        AiUsage::query()->create(['feature' => 'copywriter', 'model' => 'gpt-4.1-mini', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'cost_usd' => 5]);

        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/ai-usage/limits')
            ->assertOk()
            ->assertJsonPath('data.profileEnrichment', true)
            ->assertJsonPath('data.emailDiscoveryAi', true)
            ->assertJsonPath('data.monthlyLimitUsd', 1)
            ->assertJsonPath('data.apiKey', true)
            ->assertJsonPath('data.budget.spentUsd', 0.2)
            ->assertJsonPath('data.budget.releasedUsd', 0.5)
            ->assertJsonPath('data.budget.available', true);
    }

    #[Test]
    public function saved_values_override_config(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/ai-usage/limits', [
            'profileEnrichment' => false,
            'emailDiscoveryAi' => false,
            'monthlyLimitUsd' => 5.5,
        ])->assertOk()
            ->assertJsonPath('data.profileEnrichment', false)
            ->assertJsonPath('data.emailDiscoveryAi', false)
            ->assertJsonPath('data.monthlyLimitUsd', 5.5)
            ->assertJsonPath('data.budget.limitUsd', 5.5);

        $this->assertFalse(AppSettings::get('profile_enrichment.enabled'));
        $this->assertFalse(AppSettings::get('canals.emails.discovery.ai'));
        $this->assertSame(5.5, AppSettings::get('profile_enrichment.monthly_limit_usd'));
        $this->assertFalse(app(ProfileEnricher::class)->enabled());
        $this->assertDatabaseHas('app_settings', ['key' => 'profile_enrichment.enabled', 'updated_by' => $admin->id]);
        $this->assertDatabaseHas('system_logs', ['event' => 'admin.ai_limits_updated', 'user_id' => $admin->id]);
    }

    #[Test]
    public function saved_limit_drives_the_budget(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 30)->setTime(12, 0));
        AiUsage::query()->create(['feature' => ProfileResearch::FEATURE, 'model' => 'gpt-4.1-mini', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'cost_usd' => 1.5]);
        $service = app(ProfileEnricher::class);
        $this->assertFalse($service->budgetAvailable());

        AppSettings::set(['profile_enrichment.monthly_limit_usd' => 2]);

        $this->assertTrue($service->budgetAvailable());
    }

    #[Test]
    public function update_is_validated(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/ai-usage/limits', [
            'profileEnrichment' => true,
            'emailDiscoveryAi' => true,
            'monthlyLimitUsd' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('monthlyLimitUsd');

        $this->assertDatabaseCount('app_settings', 0);
    }

    #[Test]
    public function regular_user_cannot_change_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/ai-usage/limits')->assertForbidden();
        $this->actingAs($user, 'sanctum')->putJson('/api/admin/ai-usage/limits', [
            'profileEnrichment' => false, 'emailDiscoveryAi' => false, 'monthlyLimitUsd' => 1,
        ])->assertForbidden();
    }
}
