<?php

namespace Database\Factories;

use App\Models\Prefix;
use App\Models\RpkiValidation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RpkiValidation>
 */
class RpkiValidationFactory extends Factory
{
    public function definition(): array
    {
        $prefix = Prefix::factory()->create();

        return [
            'prefix_id' => $prefix->id,
            'rpki_roa_id' => null,
            'status' => RpkiValidation::STATUS_NOT_FOUND,
            'reason' => 'no_matching_roa',
            'validated_prefix' => $prefix->prefix,
            'ip_version' => $prefix->ip_version,
            'validated_asn' => null,
            'matched_max_length' => null,
            'matching_roas_count' => 0,
            'source' => 'LOCAL',
            'details' => null,
            'metadata' => [],
            'checked_at' => now(),
        ];
    }

    public function valid(): static
    {
        return $this->state(fn (): array => [
            'status' => RpkiValidation::STATUS_VALID,
            'reason' => 'matching_roa',
            'matching_roas_count' => 1,
        ]);
    }

    public function invalid(): static
    {
        return $this->state(fn (): array => [
            'status' => RpkiValidation::STATUS_INVALID,
            'reason' => 'asn_or_max_length_mismatch',
            'matching_roas_count' => 1,
        ]);
    }
}
