<?php

namespace Database\Factories;

use App\Models\AutonomousSystem;
use App\Models\Prefix;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RpkiRoa>
 */
class RpkiRoaFactory extends Factory
{
    public function definition(): array
    {
        $autonomousSystem = AutonomousSystem::factory()->create();

        $prefix = Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
        ]);

        return [
            'client_id' => $prefix->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix_id' => $prefix->id,
            'prefix' => $prefix->prefix,
            'ip_version' => $prefix->ip_version,
            'asn' => $autonomousSystem->asn,
            'max_length' => $prefix->ip_version === 4 ? 24 : 48,
            'source' => 'RPKI',
            'tal' => 'LACNIC',
            'status' => 'active',
            'payload_hash' => hash('sha256', fake()->uuid()),
            'not_before' => now()->subDay(),
            'not_after' => now()->addMonth(),
            'last_seen_at' => now(),
            'metadata' => [],
            'active' => true,
        ];
    }
}
