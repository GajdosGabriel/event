<?php

namespace Tests\Feature\Events;

use App\Enums\ModelStatus;
use App\Models\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;

class DashboardSuperAdminArchivedLockTest extends EventSetupTest
{
    #[Test]
    public function super_admin_cannot_bypass_archived_lock_in_dashboard_scope(): void
    {
        $canalId = (int) $this->userSuperAdmin->canal_id;
        $this->assertNotNull($canalId, 'Super-admin should have a personal canal provisioned.');

        $archived = Event::query()->create([
            'name' => 'Super admin archived ' . uniqid(),
            'status' => ModelStatus::Archived->value,
            'canal_id' => $canalId,
            'user_id' => $this->userSuperAdmin->id,
        ]);

        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $this->putJson('/api/dashboard/events/' . $archived->id, ['name' => 'Changed'])
            ->assertStatus(403);

        $this->getJson('/api/dashboard/events/' . $archived->id)
            ->assertOk()
            ->assertJsonPath('permissions.update', false)
            ->assertJsonPath('permissions.duplicate', true);
    }

    #[Test]
    public function super_admin_still_bypasses_archived_lock_in_admin_scope(): void
    {
        $canalId = (int) $this->userSuperAdmin->canal_id;

        $archived = Event::query()->create([
            'name' => 'Super admin archived (admin scope) ' . uniqid(),
            'status' => ModelStatus::Archived->value,
            'canal_id' => $canalId,
            'user_id' => $this->userSuperAdmin->id,
        ]);

        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $this->putJson('/api/admin/events/' . $archived->id, ['name' => 'Changed via admin'])
            ->assertStatus(200)
            ->assertJsonPath('name', 'Changed via admin');
    }

    #[Test]
    public function super_admin_keeps_editing_non_archived_own_canal_events_in_dashboard(): void
    {
        $canalId = (int) $this->userSuperAdmin->canal_id;

        $draft = Event::query()->create([
            'name' => 'Super admin draft ' . uniqid(),
            'status' => ModelStatus::Draft->value,
            'canal_id' => $canalId,
            'user_id' => $this->userSuperAdmin->id,
        ]);

        $this->actingAs($this->userSuperAdmin, 'sanctum');

        $this->putJson('/api/dashboard/events/' . $draft->id, ['name' => 'Changed draft'])
            ->assertStatus(200)
            ->assertJsonPath('name', 'Changed draft');
    }
}
