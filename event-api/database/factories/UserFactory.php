<?php

namespace Database\Factories;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registeredVia = $this->faker->randomElement(['local', 'google', 'facebook']);

        return [
            'email' => fake()->unique()->safeEmail(),
            'canal_id' => $this->faker->numberBetween(1, 20),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'last_activity' => $this->faker->dateTimeInInterval('-1 years', '1 days'),
            'registered_via' => $registeredVia,
            'provider_id' => $registeredVia === 'local' ? null : Str::uuid()->toString(),
            'last_login_at' => $this->faker->dateTimeInInterval('-6 months', '1 days'),
            'status' => ModelStatus::Published,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
