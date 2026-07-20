<?php

namespace Database\Factories;

use App\Models\AutonomousSystem;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Prefix>
 */
class PrefixFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $client = Client::factory();

        return [
            'client_id' => $client,
            'autonomous_system_id' => null,
            'prefix' => fake()->unique()->ipv4().'/32',
            'ip_version' => 4,
            'description' => fake()->optional()->sentence(),
            'rir' => 'LACNIC',
            'country' => 'BR',
            'allocation_status' => 'allocated',
            'purpose' => fake()->optional()->randomElement([
                'BGP',
                'Clientes',
                'Infraestrutura',
                'Serviços',
            ]),
            'notes' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }

    public function ipv6(): static
    {
        return $this->state(fn (): array => [
            'prefix' => strtolower(fake()->unique()->ipv6()).'/128',
            'ip_version' => 6,
        ]);
    }

    public function forAutonomousSystem(
        ?AutonomousSystem $autonomousSystem = null
    ): static {
        return $this->state(function () use ($autonomousSystem): array {
            $autonomousSystem ??= AutonomousSystem::factory()->create();

            return [
                'client_id' => $autonomousSystem->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
