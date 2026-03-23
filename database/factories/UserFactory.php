<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => null,
            'github_id' => (string) fake()->unique()->numberBetween(10000, 99999),
            'github_username' => fake()->userName(),
            'avatar_url' => fake()->imageUrl(200, 200),
            'generation_count' => 0,
            'is_admin' => false,
        ];
    }
}
