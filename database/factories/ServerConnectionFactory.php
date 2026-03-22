<?php

namespace Database\Factories;

use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerConnection>
 */
class ServerConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'manual',
            'server_name' => fake()->domainWord() . '-server',
            'server_ip' => fake()->ipv4(),
            'coolify_url' => 'https://' . fake()->domainName() . ':8000',
            'coolify_api_key' => fake()->sha256(),
            'status' => 'active',
        ];
    }

    public function hetzner(): static
    {
        return $this->state(fn () => [
            'provider' => 'hetzner',
            'encrypted_api_key' => fake()->sha256(),
            'server_id' => (string) fake()->randomNumber(8),
            'server_spec' => 'cx22',
        ]);
    }

    public function provisioning(): static
    {
        return $this->state(fn () => [
            'provider' => 'hetzner',
            'encrypted_api_key' => fake()->sha256(),
            'status' => 'provisioning',
            'server_ip' => null,
            'coolify_url' => null,
            'coolify_api_key' => null,
        ]);
    }
}
