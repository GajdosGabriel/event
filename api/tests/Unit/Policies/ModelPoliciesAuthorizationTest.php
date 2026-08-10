<?php

namespace Tests\Unit\Policies;

use App\Enums\CanalRole;
use App\Enums\ModelStatus;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Policies\CanalPolicy;
use App\Policies\EventPolicy;
use App\Policies\UserPolicy;
use App\Policies\VenuePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelPoliciesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function canal_policy_requires_ownership_for_delete_and_restore(): void
    {
        $owner = User::factory()->create();
        $canal = $owner->canals()->firstOrFail();
        $canal->update(['status' => ModelStatus::Draft->value]);

        $member = User::factory()->create();
        $member->canals()->attach($canal->id, [
            'is_owner' => false,
            'role' => CanalRole::Editor->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policy = new CanalPolicy;

        // Editor robí obsah v kanáli, ale samotný kanál nespravuje — z canal.*
        // má len canal.view (viď CanalRole::abilities()).
        $this->assertTrue($policy->view($member, $canal));
        $this->assertFalse($policy->update($member, $canal));
        $this->assertFalse($policy->delete($member, $canal));
        $this->assertFalse($policy->restore($member, $canal));

        $this->assertTrue($policy->update($owner, $canal));
        $this->assertTrue($policy->delete($owner, $canal));
        $this->assertTrue($policy->restore($owner, $canal));
    }

    #[Test]
    public function event_policy_requires_ownership_for_delete_and_restore(): void
    {
        $owner = User::factory()->create();
        $canal = $owner->canals()->firstOrFail();
        $canal->update(['status' => ModelStatus::Draft->value]);

        $member = User::factory()->create();
        $member->canals()->attach($canal->id, [
            'is_owner' => false,
            'role' => CanalRole::Editor->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outsider = User::factory()->create();

        $venue = Venue::factory()->forCanal($canal->id)->create();
        $event = Event::factory()->create([
            'canal_id' => $canal->id,
            'venue_id' => $venue->id,
            'user_id' => $owner->id,
            'status' => ModelStatus::Draft->value,
        ]);

        $policy = new EventPolicy;

        $this->assertTrue($policy->update($member, $event));
        $this->assertFalse($policy->delete($member, $event));
        $this->assertFalse($policy->restore($member, $event));

        $this->assertTrue($policy->delete($owner, $event));
        $this->assertTrue($policy->restore($owner, $event));

        $this->assertFalse($policy->update($outsider, $event));
    }

    #[Test]
    public function venue_policy_requires_ownership_for_delete_and_restore(): void
    {
        $owner = User::factory()->create();
        $canal = $owner->canals()->firstOrFail();

        $member = User::factory()->create();
        $member->canals()->attach($canal->id, [
            'is_owner' => false,
            'role' => CanalRole::Editor->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checkin = User::factory()->create();
        $checkin->canals()->attach($canal->id, [
            'is_owner' => false,
            'role' => CanalRole::Checkin->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $venueByCanal = Venue::factory()->forCanal($canal->id)->create([
            'status' => ModelStatus::Draft->value,
        ]);

        $policy = new VenuePolicy;

        // Editor miesta spravuje (venue.view/create/update), ale venue.delete
        // nemá. delete() mu prejde len preto, že je to preňho odpojenie —
        // EloquentVenueRepository::delete() nevlastníkovi miesto len odpojí od
        // jeho kanálov. restore() je naopak čisto vlastnícka vec.
        $this->assertTrue($policy->view($member, $venueByCanal));
        $this->assertTrue($policy->update($member, $venueByCanal));
        $this->assertTrue($policy->delete($member, $venueByCanal));
        $this->assertFalse($policy->restore($member, $venueByCanal));

        // Brigádnik na vstupe s miestami nerobí nič.
        $this->assertFalse($policy->view($checkin, $venueByCanal));
        $this->assertFalse($policy->update($checkin, $venueByCanal));
        $this->assertFalse($policy->delete($checkin, $venueByCanal));
        $this->assertFalse($policy->restore($checkin, $venueByCanal));

        $venueByCanal->canals()->updateExistingPivot($canal->id, ['status' => ModelStatus::Draft->value]);
        $this->assertFalse($policy->view($member, $venueByCanal));
        $venueByCanal->canals()->updateExistingPivot($canal->id, ['status' => ModelStatus::Published->value]);

        $this->assertTrue($policy->update($owner, $venueByCanal));
        $this->assertTrue($policy->delete($owner, $venueByCanal));
        $this->assertTrue($policy->restore($owner, $venueByCanal));
    }

    #[Test]
    public function user_policy_allows_owner_to_manage_shared_users_and_blocks_self_delete(): void
    {
        $owner = User::factory()->create();
        $ownerCanal = $owner->canals()->firstOrFail();

        $member = User::factory()->create();
        $member->canals()->attach($ownerCanal->id, [
            'is_owner' => false,
            'role' => CanalRole::Editor->value,
            'status' => ModelStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outsider = User::factory()->create();
        $unverified = User::factory()->unverified()->create();

        $policy = new UserPolicy;

        $this->assertTrue($policy->view($owner, $member));
        $this->assertTrue($policy->update($owner, $member));
        $this->assertTrue($policy->delete($owner, $member));
        $this->assertTrue($policy->restore($owner, $member));

        $this->assertFalse($policy->delete($member, $owner));
        $this->assertFalse($policy->restore($member, $owner));
        $this->assertFalse($policy->delete($owner, $owner));

        $this->assertTrue($policy->create($owner));
        $this->assertFalse($policy->create($unverified));

        $this->assertFalse($policy->delete($owner, $outsider));
    }
}
