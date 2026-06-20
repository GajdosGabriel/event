<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Enums\{CanalIdentityMode, ModelStatus, RegistrationSource};
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Canal>
 */
class CanalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name;
        return [
            'name' => $this->faker->company,
            'slug' => Str::slug($name),
            'body' => $this->faker->paragraph(),
            'published_at' => $this->faker->dateTimeInInterval('-30 years', '10 days'),
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->optional()->phoneNumber(),
            'website' => $this->faker->domainName(),
            'registration_source' => $this->faker->randomElement(RegistrationSource::cases())->value,
            'identity_mode' => $this->faker->randomElement(CanalIdentityMode::cases())->value,
            'email_verified_at' => now(),
            'status' => $this->faker->randomElement(ModelStatus::cases())->value,
            'municipality_id' => $this->faker->numberBetween(1, 4200)
        ];
    }

    public function active(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => $this->faker->randomElement(ModelStatus::cases())->value,
                'published_at' => $this->faker->dateTimeInInterval('-5 years', '1 days'),
            ];
        });
    }

    // Published_at je vtedy ak je kanál aktívny or overený
    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'draft',
                'published_at' => null
            ];
        });
    }

    /**
     * Create a canal with an owner user who has the canal-owner role assigned.
     */
    public function withOwner(?User $user = null): self
    {
        return $this->afterCreating(function ($canal) use ($user) {
            $owner = $user ?? User::factory()->create();

            if (! $owner->hasRole('canal-owner')) {
                $owner->assignRole('canal-owner');
            }

            $canal->users()->attach($owner->id, [
                'is_owner' => true,
                'status' => ModelStatus::Published->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
