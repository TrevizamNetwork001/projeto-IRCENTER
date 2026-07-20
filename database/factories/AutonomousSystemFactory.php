<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AutonomousSystem>
 */
class AutonomousSystemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $asn = fake()->unique()->numberBetween(64512, 4294967294);

        return [
            'client_id' => Client::factory(),
            'asn' => $asn,
            'name' => 'AS'.$asn.' '.fake()->company(),
            'description' => fake()->optional()->sentence(),
            'rir' => fake()->randomElement([
                'LACNIC',
                'ARIN',
                'RIPE NCC',
                'APNIC',
                'AFRINIC',
            ]),
            'country' => 'BR',
            'website' => fake()->optional()->url(),
            'noc_contact' => fake()->optional()->name(),
            'noc_email' => fake()->optional()->companyEmail(),
            'noc_phone' => fake()->optional()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
