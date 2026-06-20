<?php

namespace Tests\Unit\Canals;

use App\Enums\ModelStatus;

use Tests\TestCase; // <-- Dôležité: Použite Laravel TestCase namiesto PHPUnit TestCase
use App\Models\Canal;
use Illuminate\Support\Facades\Schema; // <-- Pridajte tento import pre prácu s databázou
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Repositories\Contracts\CanalRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions; // <-- Používame DatabaseTransactions pre testy, ktoré potrebujú transakcie
use Illuminate\Validation\Rules\Can;


class CanalHasPivotTableTest extends TestCase // <-- Zmena základnej triedy
{
    // Po registrácií usera vytvorý canal a priradí ho k userovi. Pomocou Observeru.

    use DatabaseTransactions;

    protected CanalRepository $canalRepository;
    protected $user;
    protected $canal;

    protected function setUp(): void
    {
        parent::setUp(); // <-- Musí byť prvé
        $this->canalRepository = app(CanalRepository::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
        $canal = $this->canalRepository->create(Canal::factory()->make()->toArray());

        // Pripoj používateľa ku kanálu cez pivot tabuľku
        $this->canal = $this->user->canals()->where('canal_id', $canal->id)->first();
    }

    public function test_create_new_canal_by_user()
    {
        // 1. Získajte kanál a pivot údaje

        // 2. Overte existenciu
        $this->assertNotNull($this->canal, 'Používateľ by mal mať kanál');
        $this->assertNotNull($this->canal->pivot, 'Kanál by mal mať pivot údaje');

        // 3. Debug výpis (voliteľné)
        // dump([
        // 'pivot_data' => $canal->pivot,
        // 'is_owner_type' => gettype($this->canal->pivot->is_owner),
        // 'status_type' => gettype($this->canal->pivot->status)
        // ]);

        // 4. Upravené asercie pre integer/boolean hodnoty
        $this->assertEquals(1, $this->canal->pivot->is_owner, 'is_owner by mal byť 1 (true)');
        $this->assertSame(ModelStatus::Published->value, $this->canal->pivot->status, 'status by mal byt published');


        // Alebo alternatívne pre boolean check:
        $this->assertTrue((bool)$this->canal->pivot->is_owner, 'is_owner by mal byť true');
        $this->assertSame(ModelStatus::Published->value, $this->canal->pivot->status, 'status by mal byt published');

        // 5. Vlastníctvo canalu
        $this->assertEquals($this->user->id, $this->canal->pivot->user_id, 'vlastník canalu je $this->user');
        $this->assertEquals($this->canal->id, $this->canal->pivot->canal_id, 'pivot patrí canal');
    }


    // public function test_pivot_record_creation()
    // {

    //     $this->user->canals()->attach($this->canal, [
    //         'is_owner' => 1,
    //         'status' => ModelStatus::Published->value
    //     ]);

    //     $this->assertDatabaseHas('canal_user', [
    //         'user_id' => $this->user->id,
    //         'canal_id' => $this->canal->id,
    //         'is_owner' => 1,
    //         'status' => ModelStatus::Published->value
    //     ]);
    // }

    // public function test_pivot_attribute_updates()
    // {
    //     $this->user->canals()->attach($this->canal);

    //     // Aktualizácia pivot hodnoty
    //     $this->user->canals()->updateExistingPivot($this->canal->id, [
    //         'status' => ModelStatus::Draft->value
    //     ]);

    //     $this->assertDatabaseHas('canal_user', [
    //         'user_id' => $this->user->id,
    //         'canal_id' => $this->canal->id,
    //         'status' => ModelStatus::Draft->value
    //     ]);
    // }

    // public function test_pivot_record_deletion()
    // {
    //     $this->user->canals()->attach($this->canal);

    //     $this->user->canals()->detach($this->canal);

    //     $this->assertDatabaseMissing('canal_user', [
    //         'user_id' => $this->user->id,
    //         'canal_id' => $this->canal->id
    //     ]);
    // }

    // public function test_relationship_through_pivot()
    // {
    //     $this->user->canals()->attach($this->canal, ['is_owner' => 1]);

    //     $this->assertTrue($this->user->canals->contains($this->canal));
    //     $this->assertEquals(1, $this->user->canals->first()->pivot->is_owner);
    //     $this->assertInstanceOf(Canal::class, $this->user->canals->first());
    // }

    // public function test_unique_user_canal_combination()
    // {

    //     $this->user->canals()->attach($this->canal);

    //     $this->expectException(\Illuminate\Database\QueryException::class);
    //     $this->user->canals()->attach($this->canal); // Duplicitný vstup
    // }

    // public function test_pivot_default_values()
    // {

    //     $this->user->canals()->attach($this->canal); // Bez explicitných hodnôt

    //     $this->assertDatabaseHas('canal_user', [
    //         'user_id' => $this->user->id,
    //         'canal_id' => $this->canal->id,
    //         'is_owner' => 0, // Očakávaná defaultná hodnota
    //         'status' => ModelStatus::Published->value // Očakávaná defaultná hodnota
    //     ]);
    // }

    // public function test_pivot_accessor_methods()
    // {

    //     // 2. Vytvorenie pivot záznamu s explicitnými hodnotami
    //     $this->user->canals()->attach($this->canal, [
    //         'is_owner' => 1,  // Databázový boolean (1/0)
    //         'status' => ModelStatus::Published->value
    //     ]);

    //     // 3. Získanie fresh inštancie s načítanými vzťahmi
    //     $this->user->fresh()->load('canals');
    //     $pivot = $this->user->canals->first()->pivot;

    //     // 4. Debug výpis (voliteľné)
    //     // dump([
    //     //     'pivot_data' => $pivot->toArray(),
    //     //     'is_owner_type' => gettype($pivot->is_owner),
    //     //     'status_type' => gettype($pivot->status)
    //     // ]);

    //     // 5. Upravené asercie pre databázové boolean hodnoty
    //     $this->assertEquals(1, $pivot->is_owner, 'is_owner by mal byť 1 (true)');
    //     $this->assertEquals(1, $pivot->status, 'status by mal byť published');

    //     // Alternatívne: Konverzia na boolean
    //     $this->assertTrue((bool)$pivot->is_owner, 'is_owner by mal byť true');
    //     $this->assertTrue((bool)$pivot->status, 'status by mal byť published');
    // }
}

