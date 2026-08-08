<?php

namespace Database\Factories;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Fakturačné polia (IČO, DIČ, sídlo, banka) tu zámerne nie sú — tabuľka
     * `organizations` ich nedrží, vlastní ich Account. Väzbu naň predstavuje
     * iba `account_uuid`, ktorý základný stav nemá: organizácia bez Accountu
     * je platný stav a znamená, že sa na ňu ešte nedá fakturovať.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->company();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'person' => false,
            'email' => $this->faker->unique()->companyEmail(),
            'description' => $this->faker->paragraph(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'website' => $this->faker->url(),
            'published' => true,
            'status' => ModelStatus::Published->value,
            'account_uuid' => null,
            'account_synced_at' => null,
        ];
    }

    /** Firma zapísaná v Accounte — až takej sa dá fakturovať. */
    public function linkedToAccount(): self
    {
        return $this->state(fn (array $attributes) => [
            'account_uuid' => (string) Str::uuid(),
            'account_synced_at' => now(),
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => ModelStatus::Archived->value,
        ]);
    }
}
