<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Canal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Carbon\Carbon;


class CanalUserPivotTest extends TestCase
{
    use DatabaseTransactions;
    // use RefreshDatabase;

    #[Test]
    public function user_can_be_attached_to_canal()
    {
        $user = User::factory()->create();
        $canal = Canal::factory()->create();

        // Attach user to canal
        $canal->users()->attach($user->id);

        // Verify in database
        $this->assertDatabaseHas('canal_user', [
            'canal_id' => $canal->id,
            'user_id' => $user->id
        ]);

        // Verify relationship
        $this->assertTrue($canal->users->contains($user));
        $this->assertTrue($user->canals->contains($canal));
    }

    #[Test]
    public function multiple_users_can_be_attached_to_canal()
    {
        $canal = Canal::factory()->create();
        $users = User::factory()->count(3)->create();

        $canal->users()->attach($users->pluck('id'));

        $this->assertCount(3, $canal->fresh()->users);
    }

    #[Test]
    public function user_can_be_detached_from_canal()
    {
        $user = User::factory()->create();
        $canal = Canal::factory()->create();

        $canal->users()->attach($user->id);
        $canal->users()->detach($user->id);

        $this->assertDatabaseMissing('canal_user', [
            'canal_id' => $canal->id,
            'user_id' => $user->id
        ]);
    }

    #[Test]
    public function canal_user_relationship_has_timestamps()
    {
        $user = User::factory()->create();
        $canal = Canal::factory()->create();

        // Explicitne nastavíme timestamp hodnoty
        $now = now();
        $canal->users()->attach($user->id, [
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // Načítame pivot záznam
        $pivot = $canal->users()->first()->pivot;

        // Overíme timestamp hodnoty
        $this->assertEquals(
            $now->format('Y-m-d H:i:s'),
            Carbon::parse($pivot->created_at)->format('Y-m-d H:i:s')
        );
        $this->assertEquals(
            $now->format('Y-m-d H:i:s'),
            Carbon::parse($pivot->created_at)->format('Y-m-d H:i:s')
        );
    }

    // #[Test]
    // public function relationship_is_deleted_on_canal_delete()
    // {
    //     DB::beginTransaction();

    //     try {
    //         $user = User::factory()->create();
    //         $canal = Canal::factory()->create();

    //         $canal->users()->attach($user->id);

    //         $this->assertDatabaseHas('canal_user', [
    //             'canal_id' => $canal->id,
    //             'user_id' => $user->id
    //         ]);

    //         $canal->delete();

    //         // Overte cez raw SQL query
    //         $exists = DB::table('canal_user')
    //             ->where('canal_id', $canal->id)
    //             ->exists();

    //         $this->assertFalse($exists, 'Pivot record was not deleted');

    //         DB::commit();
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

    // #[Test]
    // public function relationship_is_deleted_on_user_delete()
    // {
    //     $user = User::factory()->create();
    //     $canal = Canal::factory()->create();

    //     $canal->users()->attach($user->id);
    //     $user->delete();

    //     $this->assertDatabaseMissing('canal_user', [
    //         'user_id' => $user->id
    //     ]);
    // }

    #[Test]
    public function duplicate_relationships_are_prevented()
    {
        $user = User::factory()->create();
        $canal = Canal::factory()->create();

        $canal->users()->attach($user->id);

        // Attempt to attach same user again
        $this->expectException(\Illuminate\Database\QueryException::class);
        $canal->users()->attach($user->id);
    }
}
