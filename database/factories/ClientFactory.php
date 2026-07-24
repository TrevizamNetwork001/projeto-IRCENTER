<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company().' LTDA',
            'trade_name' => fake()->company(),
            'document' => null,
            'email' => fake()->companyEmail(),
            'phone' => '(11) 99999-9999',
            'website' => fake()->url(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'country' => 'BR',
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
