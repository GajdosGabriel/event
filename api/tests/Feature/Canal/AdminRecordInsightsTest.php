<?php

namespace Tests\Feature\Canal;

use App\Models\AiUsage;
use App\Models\Canal;
use App\Models\ProfileEnrichment;
use App\Models\Venue;
use App\Services\SystemLog\Recorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

/**
 * Admin panel „Údaje o zázname" na detaile kanála a miesta
 * (Admin\RecordInsightsController).
 */
class AdminRecordInsightsTest extends CanalSetupTest
{
    use RefreshDatabase;

    #[Test]
    public function admin_sees_facts_and_a_merged_timeline_of_a_venue(): void
    {
        $venue = Venue::factory()->create();
        $other = Venue::factory()->create();

        Recorder::info('import', 'venue_created', 'Miesto z importu', subject: $venue);
        Recorder::info('import', 'venue_created', 'Cudzie miesto', subject: $other);
        $this->aiUsage(['subject_type' => 'Venue', 'subject_id' => $venue->id]);
        $this->aiUsage(['subject_type' => 'Venue', 'subject_id' => $other->id]);
        ProfileEnrichment::create([
            'subject_type' => $venue->getMorphClass(), 'subject_id' => $venue->id,
            'completed_at' => now()->subDay(), 'changes' => ['website' => 'https://example.test'],
        ]);

        $response = $this->actingAs($this->userSuperAdmin, 'sanctum')
            ->getJson("/api/admin/venues/{$venue->id}/insights")
            ->assertOk()
            ->assertJsonPath('data.facts.createdAt', $venue->created_at->toIso8601String())
            ->assertJsonPath('data.facts.ai.calls', 1)
            ->assertJsonPath('data.facts.followers', null)
            ->assertJsonPath('data.facts.eventsTotal', 0)
            ->assertJsonCount(3, 'data.timeline');

        $this->assertEqualsCanonicalizing(['log', 'ai', 'profile'], array_column($response->json('data.timeline'), 'type'));
    }

    #[Test]
    public function canal_timeline_covers_its_mail_and_ai_calls_of_its_events(): void
    {
        $canal = Canal::factory()->create(['email' => 'kanal@example.test']);

        Recorder::info('mail', 'sent', 'Správa kanálu', recipient: 'kanal@example.test');
        $this->aiUsage(['canal_id' => $canal->id]);

        $this->actingAs($this->userSuperAdmin, 'sanctum')
            ->getJson("/api/admin/canals/{$canal->id}/insights")
            ->assertOk()
            ->assertJsonPath('data.facts.followers', 0)
            ->assertJsonPath('data.facts.ai.calls', 1)
            ->assertJsonFragment(['message' => 'Správa kanálu']);
    }

    #[Test]
    public function a_regular_user_cannot_see_insights(): void
    {
        $venue = Venue::factory()->create();
        $canal = Canal::factory()->create();
        $this->actingAs($this->user, 'sanctum');

        $this->getJson("/api/admin/venues/{$venue->id}/insights")->assertForbidden();
        $this->getJson("/api/admin/canals/{$canal->id}/insights")->assertForbidden();
    }

    private function aiUsage(array $attributes): void
    {
        AiUsage::create($attributes + ['feature' => 'content_review', 'model' => 'gpt-test', 'cost_usd' => 0.01, 'success' => true]);
    }
}
