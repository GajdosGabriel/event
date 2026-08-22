<?php

namespace Tests\Feature\Canal;

use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

class DashboardCanalDestroyTest extends CanalSetupTest
{
    /**
     * Osobný kanál zakladá PersonalCanalProvisioner rovno ako publikovaný, a
     * publikovaný kanál sa mazať nedá — zamieta to CanalPolicy::delete(), teda
     * 403. Test tu čakal 422 z ad-hoc kontrol v kontroléri; k tým sa nikdy
     * nedostal a padal aj predtým, len to prekrývala náhoda vo CanalFactory.
     */
    #[Test]
    public function user_cannot_delete_published_canal_from_dashboard_scope(): void
    {
        $this->assertSame(ModelStatus::Published, $this->canalPrimary->status);

        $response = $this->deleteJson('/api/dashboard/canals/' . $this->canalPrimary->id);

        $response->assertForbidden();

        $this->assertNotSoftDeleted('canals', [
            'id' => $this->canalPrimary->id,
        ]);
    }

    /**
     * Podujatia boli jediná závislosť, ktorú pôvodné ad-hoc kontroly v
     * DashboardCanalController::destroy() nepozerali — kanál sa dal zmazať aj
     * s nimi. Odkedy prekážku počíta model, drží aj tento prípad.
     */
    #[Test]
    public function canal_whose_only_dependency_is_an_event_cannot_be_deleted(): void
    {
        $this->user->givePermissionTo('canal.delete');

        $canal = Canal::factory()->inactive()->create();
        $this->user->canals()->attach($canal->id, [
            'is_owner' => true,
            'role' => 'owner',
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Event::factory()->create([
            'canal_id' => $canal->id,
            'venue_id' => null,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->deleteJson('/api/dashboard/canals/' . $canal->id)->assertStatus(422);

        $this->assertNotSoftDeleted('canals', ['id' => $canal->id]);
    }

    /**
     * Archív mazanie neblokuje — blokuje ho história (podujatia, miesta, členovia).
     * Prázdny archivovaný kanál si vlastník aj tak odomkol prepnutím stavu na
     * koncept, takže zámok len pridával krok navyše. Viď CanalPolicy::delete().
     */
    #[Test]
    public function archived_canal_without_dependencies_can_be_deleted(): void
    {
        $this->user->givePermissionTo('canal.delete');

        $canal = Canal::factory()->inactive()->create(['status' => ModelStatus::Archived->value]);
        $this->user->canals()->attach($canal->id, [
            'is_owner' => true,
            'role' => 'owner',
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteJson('/api/dashboard/canals/' . $canal->id)->assertStatus(204);

        $this->assertSoftDeleted('canals', ['id' => $canal->id]);
    }

    /** Zámkom ostáva história — na archivovanom kanáli platí rovnako. */
    #[Test]
    public function archived_canal_with_an_event_still_cannot_be_deleted(): void
    {
        $this->user->givePermissionTo('canal.delete');

        $canal = Canal::factory()->inactive()->create(['status' => ModelStatus::Archived->value]);
        $this->user->canals()->attach($canal->id, [
            'is_owner' => true,
            'role' => 'owner',
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Event::factory()->create([
            'canal_id' => $canal->id,
            'venue_id' => null,
            'user_id' => User::factory()->create()->id,
        ]);

        $this->deleteJson('/api/dashboard/canals/' . $canal->id)->assertStatus(422);

        $this->assertNotSoftDeleted('canals', ['id' => $canal->id]);
    }
}
