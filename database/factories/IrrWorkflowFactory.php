<?php

namespace Database\Factories;

use App\Models\AutonomousSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\IrrWorkflow>
 */
class IrrWorkflowFactory extends Factory
{
    public function definition(): array
    {
        $autonomousSystem = AutonomousSystem::factory()->create();

        return [
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'name' => 'Implantação IRR '.$autonomousSystem->formattedAsn(),
            'irr_source' => 'LOCAL',
            'destination_email' => 'irr@example.net',
            'maintainer' => 'MAINT-'.$autonomousSystem->formattedAsn(),
            'as_set' => $autonomousSystem->formattedAsn().':AS-ALL',
            'route_set' => $autonomousSystem->formattedAsn().':RS-ROUTES',
            'contact_name' => fake()->name(),
            'contact_handle' => 'CONTACT-EXAMPLE',
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '+55 11 0000-0000',
            'contact_address' => 'Endereço de documentação',
            'status' => 'in_progress',
            'current_step' => 1,
            'started_at' => now(),
            'completed_at' => null,
            'notes' => null,
        ];
    }
}
