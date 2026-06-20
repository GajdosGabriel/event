<?php

namespace Database\Factories;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Canal;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CanalUser>
 */
class CanalUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'canal_id' => Canal::inRandomOrder()->first()->id ?? Canal::factory(),
            'user_id' => User::inRandomOrder()->first()->id ?? User::factory(),
            'is_owner' => false,
            'status' => ModelStatus::Published->value,
        ];
    }

    /**
     * Create a canal-user relationship where the user is an owner.
     */
    public function asOwner(): self
    {
        return $this->state(function (array $attributes) {
            $user = User::find($attributes['user_id']) ?? User::factory()->create();

            if (! $user->hasRole('canal-owner')) {
                $user->assignRole('canal-owner');
            }

            return [
                'user_id' => $user->id,
                'is_owner' => true,
                'status' => ModelStatus::Published->value,
            ];
        });
    }

    /**
     * Create a canal-user relationship where the user is only active (not owner).
     */
    public function asEditor(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'is_owner' => false,
                'status' => ModelStatus::Published->value,
            ];
        });
    }
}
