<?php

namespace Tests\Feature\Organizations;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestSupport\CanalSetupTest;

/**
 * Izolácia organizácií v dashboarde.
 *
 * `organization.view` seeder priraďuje aj rolám `canal-owner` a `canal-editor`,
 * takže samotné globálne právo nesmie stačiť — inak si hociktorý organizátor
 * vypíše všetky firmy v systéme aj s ich väzbou na Account. Rozhoduje až to,
 * či firma visí na niektorom z jeho kanálov.
 */
class DashboardOrganizationScopeTest extends CanalSetupTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user->givePermissionTo([
            'organization.view',
            'organization.create',
            'organization.update',
            'organization.delete',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_index_lists_only_organizations_reachable_through_own_canals(): void
    {
        $own = Organization::factory()->create(['title' => 'Vlastná firma']);
        $this->canalPrimary->forceFill(['organization_id' => $own->id])->save();

        $foreign = Organization::factory()->create(['title' => 'Cudzia firma']);
        $foreignCanal = Canal::factory()->create(['status' => ModelStatus::Published->value]);
        $foreignCanal->forceFill(['organization_id' => $foreign->id])->save();

        // Firma bez kanála — pozostatok z admin rozhrania. Do dashboardu
        // nepatrí nikomu.
        $orphan = Organization::factory()->create(['title' => 'Firma bez kanála']);

        $response = $this->getJson('/api/dashboard/organizations');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $own->id]);
        $response->assertJsonMissing(['id' => $foreign->id]);
        $response->assertJsonMissing(['id' => $orphan->id]);
    }

    public function test_show_of_foreign_organization_is_not_found(): void
    {
        $foreign = Organization::factory()->linkedToAccount()->create();
        $foreignCanal = Canal::factory()->create(['status' => ModelStatus::Published->value]);
        $foreignCanal->forceFill(['organization_id' => $foreign->id])->save();

        $this->getJson("/api/dashboard/organizations/{$foreign->id}")->assertNotFound();
    }

    public function test_update_of_foreign_organization_is_not_found(): void
    {
        $foreign = Organization::factory()->create();
        $foreignCanal = Canal::factory()->create(['status' => ModelStatus::Published->value]);
        $foreignCanal->forceFill(['organization_id' => $foreign->id])->save();

        $this->putJson("/api/dashboard/organizations/{$foreign->id}", [
            'title' => 'Prepísané cudzou rukou',
        ])->assertNotFound();

        $this->assertNotSame('Prepísané cudzou rukou', $foreign->fresh()->title);
    }

    /**
     * Bez väzby by bola nová firma pre svojho autora okamžite neviditeľná —
     * scope dashboardu ide cez kanály. Preto vzniká v tej istej transakcii.
     */
    public function test_store_links_new_organization_to_the_personal_canal(): void
    {
        $response = $this->postJson('/api/dashboard/organizations', [
            'title' => 'Nová firma',
            'email' => 'firma@example.test',
        ]);

        $response->assertCreated();

        $organizationId = $response->json('id');

        $this->assertSame(
            (int) $organizationId,
            (int) $this->canalPrimary->fresh()->organization_id,
        );

        $this->getJson('/api/dashboard/organizations')
            ->assertOk()
            ->assertJsonFragment(['id' => $organizationId]);
    }

    public function test_store_refuses_a_canal_the_user_does_not_manage(): void
    {
        $foreignCanal = Canal::factory()->create(['status' => ModelStatus::Published->value]);

        $this->postJson('/api/dashboard/organizations', [
            'title' => 'Firma pod cudzím kanálom',
            'canal_id' => $foreignCanal->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('organizations', ['title' => 'Firma pod cudzím kanálom']);
    }

    /**
     * Dramaturg (editor) vidí profil organizátora, ale nie doklady firmy.
     * Account sa mu ani nevolá.
     */
    public function test_editor_does_not_receive_billing_data(): void
    {
        $organization = Organization::factory()->linkedToAccount()->create();
        $this->canalPrimary->forceFill(['organization_id' => $organization->id])->save();

        $editor = User::factory()->create();
        $editor->assignRole('canal-editor');
        $editor->givePermissionTo('organization.view');

        $this->canalPrimary->users()->attach($editor->id, [
            'is_owner' => false,
            'role' => CanalRole::Editor->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($editor, 'sanctum');

        $response = $this->getJson("/api/dashboard/organizations/{$organization->id}");

        $response->assertOk();
        $response->assertJsonPath('account', null);
        $response->assertJsonPath('permissions.viewBilling', false);
        $response->assertJsonPath('permissions.update', false);
    }

    public function test_detail_lists_canals_with_their_team(): void
    {
        $organization = Organization::factory()->create();
        $this->canalPrimary->forceFill(['organization_id' => $organization->id])->save();

        $editor = User::factory()->create();
        $this->canalPrimary->users()->attach($editor->id, [
            'is_owner' => false,
            'role' => CanalRole::Editor->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/dashboard/organizations/{$organization->id}");

        $response->assertOk();
        $response->assertJsonPath('canals_count', 1);
        $response->assertJsonPath('canals.0.id', $this->canalPrimary->id);

        $roles = collect($response->json('canals.0.members'))->pluck('role')->all();

        $this->assertContains(CanalRole::Owner->value, $roles);
        $this->assertContains(CanalRole::Editor->value, $roles);
    }

    public function test_canal_can_be_attached_and_detached(): void
    {
        $organization = Organization::factory()->create();
        $this->canalPrimary->forceFill(['organization_id' => $organization->id])->save();

        $second = Canal::factory()->create(['status' => ModelStatus::Published->value]);
        $this->user->canals()->attach($second->id, [
            'is_owner' => true,
            'role' => CanalRole::Owner->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user->forgetCanalRoles();

        $this->postJson("/api/dashboard/organizations/{$organization->id}/canals", [
            'canal_id' => $second->id,
        ])->assertNoContent();

        $this->assertSame($organization->id, (int) $second->fresh()->organization_id);

        $this->deleteJson("/api/dashboard/organizations/{$organization->id}/canals/{$second->id}")
            ->assertNoContent();

        $this->assertNull($second->fresh()->organization_id);
    }

    /**
     * Prístup k firme vedie cez kanály — odpojením poslednej väzby by si
     * používateľ zamkol vlastné dvere.
     */
    public function test_last_canal_cannot_be_detached(): void
    {
        $organization = Organization::factory()->create();
        $this->canalPrimary->forceFill(['organization_id' => $organization->id])->save();

        $this->deleteJson("/api/dashboard/organizations/{$organization->id}/canals/{$this->canalPrimary->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('canal_id');

        $this->assertSame(
            $organization->id,
            (int) $this->canalPrimary->fresh()->organization_id,
        );
    }

    public function test_foreign_canal_cannot_be_attached(): void
    {
        $organization = Organization::factory()->create();
        $this->canalPrimary->forceFill(['organization_id' => $organization->id])->save();

        $foreignCanal = Canal::factory()->create(['status' => ModelStatus::Published->value]);

        $this->postJson("/api/dashboard/organizations/{$organization->id}/canals", [
            'canal_id' => $foreignCanal->id,
        ])->assertForbidden();

        $this->assertNull($foreignCanal->fresh()->organization_id);
    }

    public function test_owner_receives_the_billing_permission(): void
    {
        $organization = Organization::factory()->linkedToAccount()->create();
        $this->canalPrimary->forceFill(['organization_id' => $organization->id])->save();

        $this->getJson("/api/dashboard/organizations/{$organization->id}")
            ->assertOk()
            ->assertJsonPath('permissions.viewBilling', true);
    }
}
