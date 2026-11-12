<?php

namespace Tests\Feature\Canal;

use App\Jobs\RunManualLookup;
use App\Models\Canal;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

/**
 * Admin panel „Doplnenie údajov": stav profilu, polohy a e-mailu a ručné
 * spustenie kroku do fronty (Admin\CanalLookupController).
 */
class AdminCanalLookupsTest extends CanalSetupTest
{
    use RefreshDatabase;

    #[Test]
    public function admin_sees_the_status_of_all_three_steps(): void
    {
        $canal = $this->canalWithoutLocation();
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $this->getJson("/api/admin/canals/{$canal->id}/lookups")
            ->assertOk()
            ->assertJsonPath('data.coordinates.missing', true)
            ->assertJsonPath('data.coordinates.blocker', null)
            ->assertJsonPath('data.profile.running', false)
            ->assertJsonStructure(['data' => ['profile' => ['missing', 'blocker'], 'coordinates', 'email' => ['result', 'autoSkipReason', 'blocker']]]);
    }

    #[Test]
    public function a_step_is_queued_and_shown_as_running(): void
    {
        Queue::fake();
        $canal = $this->canalWithoutLocation();
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $this->postJson("/api/admin/canals/{$canal->id}/lookups/coordinates")
            ->assertStatus(202)
            ->assertJsonPath('data.coordinates.running', true);

        Queue::assertPushed(RunManualLookup::class, fn ($job) => $job->canalId === $canal->id && $job->kind === 'coordinates');
    }

    #[Test]
    public function a_step_without_purpose_is_refused(): void
    {
        Queue::fake();
        $canal = $this->canalWithoutLocation();
        $canal->forceFill(['latitude' => 48.1, 'longitude' => 17.1, 'coordinates_source' => 'manual'])->save();
        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $this->postJson("/api/admin/canals/{$canal->id}/lookups/coordinates")
            ->assertStatus(422)
            ->assertJsonPath('blocker', 'has_coordinates');

        $this->postJson("/api/admin/canals/{$canal->id}/lookups/unknown")->assertNotFound();
        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_regular_user_cannot_run_lookups(): void
    {
        $canal = $this->canalWithoutLocation();
        $this->actingAs($this->user, 'sanctum');

        $this->postJson("/api/admin/canals/{$canal->id}/lookups/coordinates")->assertForbidden();
    }

    private function canalWithoutLocation(): Canal
    {
        $municipalityId = Municipality::query()
            ->where('id', '<>', (int) Municipality::nationwideId())
            ->value('id');

        return Canal::factory()->create([
            'municipality_id' => $municipalityId,
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
