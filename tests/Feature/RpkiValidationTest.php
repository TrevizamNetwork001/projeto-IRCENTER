<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Prefix;
use App\Models\RpkiRoa;
use App\Models\RpkiValidation;
use App\Services\RpkiValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RpkiValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefix_is_valid_when_roa_matches_asn_and_length(): void
    {
        $autonomousSystem = AutonomousSystem::factory()->create([
            'asn' => 264001,
        ]);

        $prefix = Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        $roa = RpkiRoa::factory()->create([
            'client_id' => $prefix->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix_id' => $prefix->id,
            'prefix' => '192.0.2.0/23',
            'ip_version' => 4,
            'asn' => 264001,
            'max_length' => 24,
        ]);

        $validation = app(RpkiValidationService::class)
            ->validate($prefix);

        $this->assertSame(
            RpkiValidation::STATUS_VALID,
            $validation->status
        );

        $this->assertSame($roa->id, $validation->rpki_roa_id);
        $this->assertSame('matching_roa', $validation->reason);
    }

    public function test_prefix_is_invalid_when_origin_asn_differs(): void
    {
        $autonomousSystem = AutonomousSystem::factory()->create([
            'asn' => 264001,
        ]);

        $prefix = Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '198.51.100.0/24',
            'ip_version' => 4,
        ]);

        RpkiRoa::factory()->create([
            'prefix_id' => null,
            'prefix' => '198.51.100.0/24',
            'ip_version' => 4,
            'asn' => 264999,
            'max_length' => 24,
        ]);

        $validation = app(RpkiValidationService::class)
            ->validate($prefix);

        $this->assertSame(
            RpkiValidation::STATUS_INVALID,
            $validation->status
        );

        $this->assertSame(
            'origin_asn_mismatch',
            $validation->reason
        );
    }

    public function test_prefix_is_invalid_when_max_length_is_exceeded(): void
    {
        $autonomousSystem = AutonomousSystem::factory()->create([
            'asn' => 264001,
        ]);

        $prefix = Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '203.0.113.0/25',
            'ip_version' => 4,
        ]);

        RpkiRoa::factory()->create([
            'prefix_id' => null,
            'prefix' => '203.0.113.0/24',
            'ip_version' => 4,
            'asn' => 264001,
            'max_length' => 24,
        ]);

        $validation = app(RpkiValidationService::class)
            ->validate($prefix);

        $this->assertSame(
            RpkiValidation::STATUS_INVALID,
            $validation->status
        );

        $this->assertSame(
            'max_length_exceeded',
            $validation->reason
        );
    }

    public function test_prefix_is_not_found_without_covering_roa(): void
    {
        $autonomousSystem = AutonomousSystem::factory()->create();

        $prefix = Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        $validation = app(RpkiValidationService::class)
            ->validate($prefix);

        $this->assertSame(
            RpkiValidation::STATUS_NOT_FOUND,
            $validation->status
        );

        $this->assertSame('no_covering_roa', $validation->reason);
    }

    public function test_missing_origin_asn_generates_error_result(): void
    {
        $prefix = Prefix::factory()->create([
            'autonomous_system_id' => null,
        ]);

        $validation = app(RpkiValidationService::class)
            ->validate($prefix);

        $this->assertSame(
            RpkiValidation::STATUS_ERROR,
            $validation->status
        );

        $this->assertSame(
            'missing_origin_asn',
            $validation->reason
        );
    }

    public function test_validation_history_is_preserved(): void
    {
        $autonomousSystem = AutonomousSystem::factory()->create();

        $prefix = Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
        ]);

        $service = app(RpkiValidationService::class);

        $service->validate($prefix);
        $service->validate($prefix);

        $this->assertDatabaseCount('rpki_validations', 2);

        $prefix->load('latestRpkiValidation');

        $this->assertNotNull($prefix->latestRpkiValidation);
    }
}
