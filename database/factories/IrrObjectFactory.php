<?php

namespace Database\Factories;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\IrrObject>
 */
class IrrObjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'autonomous_system_id' => null,
            'prefix_id' => null,
            'object_type' => 'mntner',
            'object_key' => 'MAINT-'.strtoupper(fake()->unique()->lexify('????')),
            'source' => 'LOCAL',
            'maintainer' => null,
            'status' => 'active',
            'description' => fake()->sentence(),
            'raw_text' => null,
            'attributes' => [],
            'last_synced_at' => null,
            'active' => true,
        ];
    }

    public function routeObject(
        ?Prefix $prefix = null,
        ?AutonomousSystem $autonomousSystem = null
    ): static {
        return $this->state(function () use (
            $prefix,
            $autonomousSystem
        ): array {
            $autonomousSystem ??= AutonomousSystem::factory()->create();

            $prefix ??= Prefix::factory()->create([
                'client_id' => $autonomousSystem->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
            ]);

            return [
                'client_id' => $prefix->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'prefix_id' => $prefix->id,
                'object_type' => $prefix->ip_version === 6
                    ? 'route6'
                    : 'route',
                'object_key' => $prefix->prefix,
                'maintainer' => 'MAINT-LOCAL',
            ];
        });
    }

    public function autNum(
        ?AutonomousSystem $autonomousSystem = null
    ): static {
        return $this->state(function () use ($autonomousSystem): array {
            $autonomousSystem ??= AutonomousSystem::factory()->create();

            return [
                'client_id' => $autonomousSystem->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'object_type' => 'aut-num',
                'object_key' => $autonomousSystem->formattedAsn(),
            ];
        });
    }
}
