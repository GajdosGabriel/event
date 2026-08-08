<?php

namespace Tests\Feature\Organizations;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Models\Canal;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Tests\TestSupport\CanalSetupTest;

/**
 * Reťazec nároku na platené funkcie:
 *
 *     User ──canal_user(role)── Canal ──organization_id──▶ Organization ──account_uuid──▶ Account
 *
 * Pretrhne sa kdekoľvek a kanál spadne na neplatený režim. Testy idú
 * článok po článku, lebo práve tu je lákavé skontrolovať len polovicu
 * (napr. „má organizáciu" bez toho, či sa na ňu dá fakturovať).
 */
class OrganizationPaidAccessTest extends CanalSetupTest
{
    public function test_user_without_organization_has_no_paid_access(): void
    {
        $this->assertNull($this->canalPrimary->organization_id);
        $this->assertFalse($this->canalPrimary->hasPaidAccess());
        $this->assertFalse($this->user->hasPaidAccessTo((int) $this->canalPrimary->id));
        $this->assertTrue($this->user->paidCanalIds()->isEmpty());
    }

    public function test_organization_linked_to_account_unlocks_paid_access(): void
    {
        $organization = Organization::factory()->linkedToAccount()->create();
        $this->link($this->canalPrimary, $organization);

        $this->assertTrue($this->canalPrimary->fresh()->hasPaidAccess());
        $this->assertTrue($this->user->hasPaidAccessTo((int) $this->canalPrimary->id));
        $this->assertEqualsCanonicalizing(
            [(int) $this->canalPrimary->id],
            $this->user->paidCanalIds()->all(),
        );
    }

    public function test_organization_without_account_does_not_unlock_paid_access(): void
    {
        $organization = Organization::factory()->create(); // bez account_uuid
        $this->link($this->canalPrimary, $organization);

        $this->assertFalse($this->canalPrimary->fresh()->hasPaidAccess());
        $this->assertFalse($this->user->hasPaidAccessTo((int) $this->canalPrimary->id));
    }

    public function test_archived_organization_stops_paid_access(): void
    {
        $organization = Organization::factory()->linkedToAccount()->archived()->create();
        $this->link($this->canalPrimary, $organization);

        $this->assertFalse($this->canalPrimary->fresh()->hasPaidAccess());
    }

    public function test_soft_deleted_organization_stops_paid_access(): void
    {
        $organization = Organization::factory()->linkedToAccount()->create();
        $this->link($this->canalPrimary, $organization);
        $organization->delete();

        $this->assertFalse($this->canalPrimary->fresh()->hasPaidAccess());
        $this->assertFalse($this->user->hasPaidAccessTo((int) $this->canalPrimary->id));
    }

    /**
     * Samotný nárok kanála nestačí — kto v ňom nie je, nedostane sa k nemu ani
     * keď pozná jeho id. Bez tejto podmienky by platené funkcie odomkol
     * ktokoľvek, kto uhádne cudzí `canal_id`.
     */
    public function test_paid_access_requires_membership_in_the_canal(): void
    {
        $organization = Organization::factory()->linkedToAccount()->create();
        $this->link($this->canalPrimary, $organization);

        $outsider = User::factory()->create()->refresh();

        $this->assertFalse($outsider->hasPaidAccessTo((int) $this->canalPrimary->id));
    }

    /** Jedna firma, viac kanálov — divízie či značky pod jednou faktúrou. */
    public function test_one_organization_can_bill_for_several_canals(): void
    {
        $organization = Organization::factory()->linkedToAccount()->create();

        $second = Canal::factory()->create(['status' => ModelStatus::Published->value]);
        $this->user->canals()->attach($second->id, [
            'is_owner' => true,
            'role' => CanalRole::Owner->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->user->forgetCanalRoles();

        $this->link($this->canalPrimary, $organization);
        $this->link($second, $organization);

        $this->assertEqualsCanonicalizing(
            [(int) $this->canalPrimary->id, (int) $second->id],
            $this->user->paidCanalIds()->all(),
        );
        $this->assertSame(2, $organization->canals()->count());
    }

    /**
     * Členovia sa čítajú cez kanály, nie z tabuľky `organization_user` —
     * tá nikdy neexistovala a relácia na ňu padala.
     */
    public function test_members_and_owners_resolve_through_canals(): void
    {
        $organization = Organization::factory()->create();
        $this->link($this->canalPrimary, $organization);

        $editor = User::factory()->create();
        $this->canalPrimary->users()->attach($editor->id, [
            'is_owner' => false,
            'role' => CanalRole::Editor->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberIds = $organization->members()->pluck('users.id')->all();

        $this->assertContains($this->user->id, $memberIds);
        $this->assertContains($editor->id, $memberIds);

        $ownerIds = $organization->owners()->pluck('users.id')->all();

        $this->assertContains($this->user->id, $ownerIds);
        $this->assertNotContains($editor->id, $ownerIds);
    }

    /** Podujatia visia na kanáli; organizácia sa k nim dostane cez neho. */
    public function test_events_resolve_through_canals(): void
    {
        $organization = Organization::factory()->create();
        $this->link($this->canalPrimary, $organization);

        $event = Event::factory()->create([
            'canal_id' => $this->canalPrimary->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($organization->events()->pluck('events.id')->contains($event->id));
    }

    private function link(Canal $canal, Organization $organization): void
    {
        $canal->forceFill(['organization_id' => $organization->getKey()])->save();
    }
}
