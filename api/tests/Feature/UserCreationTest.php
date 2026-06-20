<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use App\Models\Canal;
use App\Enums\ModelStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserCreationTest extends TestCase
{
    use DatabaseTransactions;

    // Ak vaša UserFactory používa Canal, zabezpečte, aby nejaký Canal existoval
    protected $user;
    protected $canal;

    /**
     * Nastaví testovacie prostredie pred každým testom.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        // ak factory nekreslí už existujúce.
        $this->canal = Canal::factory()->create();
    }



    #[Test]
    public function a_user_can_be_created_with_specified_attributes_via_factory()
    {
        // 1. Arrange: Vytvoríme používateľa pomocou factory
        // 'create()' uloží používateľa priamo do databázy


        // 2. Assert: Overíme, že používateľ existuje v databáze s očakávanými atribútmi
        $this->assertDatabaseHas('users', [
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'canal_id' => $this->user->canal_id,
            // Pre 'email_verified_at' môžeme overiť, že nie je null,
            // alebo konkrétnu formátovanú hodnotu, ak vieme presný čas.
            'status' => $this->user->status, // Ak je status enum, overíme hodnotu
            // Heslo je zahashované, takže ho nemôžeme priamo overiť.
            // remember_token nie je vždy dôležitý na overenie existencie používateľa,
            // ale môžete ho pridať, ak chcete overiť jeho prítomnosť.
        ]);

        // Overíme, že v tabuľke 'users' je presne jeden záznam.
        $this->assertCount(1, User::all());
    }

    #[Test]
    public function a_user_can_be_created_with_specific_status()
    {
        // Vytvoríme používateľa so špecifickým statusom
        $user = User::factory()->withStatus(ModelStatus::Published)->create();

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'status' => ModelStatus::Published->value,
        ]);
    }

    #[Test]
    public function a_user_can_be_created_with_unverified_email()
    {
        // Vytvoríme používateľa s neovereným emailom
        $user = User::factory()->unverified()->create();

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
            'email_verified_at' => null,
        ]);
        $this->assertNull($user->email_verified_at);
    }

    #[Test]
    public function a_user_can_be_created_through_the_form()
    {
        // Vytvoríme používateľa s neovereným emailom
        $userData = User::factory()->unverified()->make()->toArray();

        // Add password confirmation and ensure proper password format
        $formData = array_merge($userData, [
            'password' => 'Password123!', // explicit test password
            'password_confirmation' => 'Password123!',
        ]);

        // 2. Submit registration request
        $response = $this->postJson('/api/register', $formData);

        $response->assertStatus(201); // Očakávame 201 Created pre API

        $this->assertDatabaseHas('users', [
            'first_name' => $formData['first_name'],
            'last_name' => $formData['last_name'],
            'email' => $formData['email'],
            'email_verified_at' => null,
            // 'status' => $formData['status'],
            // 'password' => $formData['password'],
        ]);

        // // 5. Debug
        // if ($response->status() !== 200 && $response->status() !== 201) {
        //     dump($response->json());
        // }
    }

    #[Test]
    public function a_user_can_be_update_through_the_form()
    {
        // Vytvoríme používateľa s neovereným emailom
        $user = User::factory()->unverified()->make();
        // Prihlásime sa ako tento používateľ
        $this->actingAs($this->user, 'sanctum');

        // Vytvoríme dáta pre nového používateľa (ale neukladáme ho)
        $udajePouzivatela = User::factory()->unverified()->make()->toArray();

        // Pridáme potvrdenie hesla a upravíme meno a priezvisko
        $formularoveData = array_merge($udajePouzivatela, [
            'first_name' => $user->first_name . 'update FirstName',
            'last_name' => $user->last_name . 'update LastName',
        ]);

        // Odošleme PUT požiadavku na API
        $odpoved = $this->putJson("/api/dashboard/users/{$this->user->id}", $formularoveData);

        // Overíme, či bola odpoveď úspešná (201 Created)
        $odpoved->assertStatus(200);

        // Overíme, či sa údaje v databáze zmenili
        $this->assertDatabaseHas('users', [
            'first_name' => $formularoveData['first_name'],
            'last_name' => $formularoveData['last_name'],
            'email' => $formularoveData['email'],
        ]);
    }
}
