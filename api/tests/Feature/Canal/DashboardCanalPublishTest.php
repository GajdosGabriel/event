<?php

namespace Tests\Feature\Canal;

use App\Enums\ModelStatus;
use App\Models\Canal;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

/**
 * Kanál sa doteraz nedal publikovať vôbec: routa neexistovala, formulár nemal
 * pole stavu a CanalStoreRequest status nevalidoval.
 */
class DashboardCanalPublishTest extends CanalSetupTest
{
    private function ownedCanal(ModelStatus $status): Canal
    {
        $canal = Canal::factory()->create(['status' => $status->value]);

        $this->user->canals()->attach($canal->id, [
            'is_owner' => true,
            'role' => 'owner',
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $canal;
    }

    #[Test]
    public function draft_canal_can_be_published(): void
    {
        $canal = $this->ownedCanal(ModelStatus::Draft);

        $this->postJson('/api/dashboard/canals/' . $canal->id . '/publish')
            ->assertOk()
            ->assertJsonPath('status', ModelStatus::Published->value);

        $fresh = $canal->fresh();
        $this->assertSame(ModelStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->published_at, 'Publikovaný kanál musí mať čas prvého zverejnenia.');
    }

    #[Test]
    public function published_canal_can_be_unpublished(): void
    {
        $canal = $this->ownedCanal(ModelStatus::Published);

        $this->postJson('/api/dashboard/canals/' . $canal->id . '/publish', ['published' => false])
            ->assertOk()
            ->assertJsonPath('status', ModelStatus::Draft->value);

        $this->assertSame(ModelStatus::Draft, $canal->fresh()->status);
    }

    /** Archív je zámok proti mazaniu, nie proti oprave. */
    #[Test]
    public function archived_canal_can_be_edited_through_the_form(): void
    {
        $canal = $this->ownedCanal(ModelStatus::Archived);

        $this->putJson('/api/dashboard/canals/' . $canal->id, array_merge($this->formCanal, [
            'name' => 'Odarchivovany kanal ' . uniqid(),
            'status' => ModelStatus::Published->value,
        ]))->assertOk();

        $this->assertSame(ModelStatus::Published, $canal->fresh()->status);
    }

    #[Test]
    public function form_rejects_a_status_the_user_may_not_set(): void
    {
        $canal = $this->ownedCanal(ModelStatus::Draft);

        $this->putJson('/api/dashboard/canals/' . $canal->id, array_merge($this->formCanal, [
            'status' => ModelStatus::Blocked->value,
        ]))->assertStatus(422)->assertJsonValidationErrors(['status']);
    }
}
