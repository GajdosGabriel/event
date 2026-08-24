<?php

namespace Tests\Feature\Canal;

use App\Enums\ModelStatus;
use App\Models\Canal;  // Import the User model
use App\Models\User; // Import the Canal model
use Illuminate\Support\Str; // Import the ModelStatus enum if needed
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\CanalSetupTest;

class DashboardCanalStoreTest extends CanalSetupTest
{
    #[Test]
    public function an_canal_can_be_created_through_the_form()
    {

        // 1. Odoslanie požiadavky
        $response = $this->postJson('/api/dashboard/canals', $this->formCanal);

        // Kontrola odpovede
        $response->assertStatus(201);

        // Získanie ID kanála z odpovede
        $canalId = $response->json('data.id') ?? $response->json('id');
        $this->assertNotNull($canalId, 'V odpovedi sa nenašlo ID vytvoreného kanála');

        // 3. Assert: Check the results
        // Assert that the canal was stored in the database
        $this->assertDatabaseHas('canals', [
            'name' => $this->formCanal['name'],
            'slug' => Str::slug($this->formCanal['name']),
            'body' => $this->formCanal['body'],
            'municipality_id' => $this->formCanal['municipality_id'],
        ]);

        // 5. Získaj práve vytvorený Canal
        $canal = Canal::where('name', $this->formCanal['name'])->first();

        // 6. Assert: Pivot záznam v `canal_user`
        $this->assertDatabaseHas('canal_user', [
            'canal_id' => $canal->id,
            'user_id' => $this->user->id,
            'is_owner' => 1,     // ak to nastavuješ
            'status' => ModelStatus::Published->value,    // ak to nastavuješ
        ]);

        $this->assertEquals(1, Canal::where('name', $this->formCanal['name'])->count());
    }

    #[Test]
    public function creating_canal_marks_creator_as_owner_in_canal_membership(): void
    {
        /** @var User $creator */
        $creator = User::factory()->createOne();
        $creator->assignRole('canal-editor');
        $creator->givePermissionTo('canal.update');
        $this->actingAs($creator, 'sanctum');

        // Nie Canal::factory()->make(), tá losuje status aj spomedzi hodnôt,
        // ktoré CanalStoreRequest neprijme (pending_review, rejected, blocked).
        $payload = $this->formCanal;

        $response = $this->postJson('/api/dashboard/canals', $payload);

        $response->assertStatus(201);

        $canalId = $response->json('data.id') ?? $response->json('id');

        $this->assertDatabaseHas('canal_user', [
            'canal_id' => $canalId,
            'user_id' => $creator->id,
            'is_owner' => 1,
            'status' => ModelStatus::Published->value,
        ]);
    }

    // #[Test]
    public function an_canal_can_be_created_with_a_specific_status()
    {
        // 2. Vytvorenie dát canalu
        $canalData = Canal::factory()->make([
            'status' => ModelStatus::Draft->value,
        ])->toArray();

        // 3. Formátovanie všetkých dátumových polí
        $canalData['published_at'] = $canalData['published_at'];
        // 3. Odoslanie požiadavky
        $response = $this->postJson('/api/dashboard/canals', $canalData);

        // 4. Overenie odpovede
        $response->assertStatus(201); // Očakávame 201 Created pre API

        // 5. Overenie v databáze
        $this->assertDatabaseHas('canals', [
            'name' => $canalData['name'],
            'status' => ModelStatus::Draft->value,
        ]);
    }
}
