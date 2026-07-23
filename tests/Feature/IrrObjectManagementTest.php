<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\IrrObject;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IrrObjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post(
            '/test/irr-objects',
            fn (\App\Http\Requests\StoreIrrObjectRequest $request) =>
                response()->json(
                    IrrObject::query()->create($request->validated())
                )
        )->middleware('web');

        Route::put(
            '/test/irr-objects/{irr_object}',
            fn (
                \App\Http\Requests\UpdateIrrObjectRequest $request,
                IrrObject $irrObject
            ) => response()->json(
                tap($irrObject)->update($request->validated())
            )
        )->middleware('web');
    }

    public function test_administrator_can_create_ipv4_route_object(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $autonomousSystem = AutonomousSystem::factory()->create();

        $prefix = Prefix::factory()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        $this->actingAs($admin)
            ->post('/test/irr-objects', [
                'client_id' => $prefix->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'prefix_id' => $prefix->id,
                'object_type' => 'route',
                'object_key' => '192.0.2.37/24',
                'source' => 'local',
                'maintainer' => 'maint-example',
                'status' => 'active',
                'active' => '1',
            ])
            ->assertOk();

        $this->assertDatabaseHas('irr_objects', [
            'object_type' => 'route',
            'object_key' => '192.0.2.0/24',
            'source' => 'LOCAL',
            'maintainer' => 'MAINT-EXAMPLE',
        ]);
    }

    public function test_route_rejects_ipv6_prefix(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $autonomousSystem = AutonomousSystem::factory()->create();

        $prefix = Prefix::factory()->ipv6()->create([
            'client_id' => $autonomousSystem->client_id,
            'autonomous_system_id' => $autonomousSystem->id,
            'prefix' => '2001:db8::/32',
            'ip_version' => 6,
        ]);

        $this->actingAs($admin)
            ->post('/test/irr-objects', [
                'client_id' => $prefix->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'prefix_id' => $prefix->id,
                'object_type' => 'route',
                'object_key' => $prefix->prefix,
                'source' => 'LOCAL',
                'status' => 'active',
                'active' => '1',
            ])
            ->assertSessionHasErrors('prefix_id');

        $this->assertDatabaseCount('irr_objects', 0);
    }

    public function test_route6_rejects_ipv4_prefix(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $prefix = Prefix::factory()->create([
            'prefix' => '198.51.100.0/24',
            'ip_version' => 4,
        ]);

        $autonomousSystem = AutonomousSystem::factory()->create([
            'client_id' => $prefix->client_id,
        ]);

        $this->actingAs($admin)
            ->post('/test/irr-objects', [
                'client_id' => $prefix->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'prefix_id' => $prefix->id,
                'object_type' => 'route6',
                'object_key' => $prefix->prefix,
                'source' => 'LOCAL',
                'status' => 'active',
                'active' => '1',
            ])
            ->assertSessionHasErrors('prefix_id');
    }

    public function test_asn_and_prefix_must_belong_to_same_client(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $prefix = Prefix::factory()->create();
        $otherAutonomousSystem = AutonomousSystem::factory()->create();

        $this->actingAs($admin)
            ->post('/test/irr-objects', [
                'client_id' => $prefix->client_id,
                'autonomous_system_id' => $otherAutonomousSystem->id,
                'prefix_id' => $prefix->id,
                'object_type' => 'route',
                'object_key' => $prefix->prefix,
                'source' => 'LOCAL',
                'status' => 'active',
                'active' => '1',
            ])
            ->assertSessionHasErrors('autonomous_system_id');
    }

    public function test_aut_num_must_match_selected_asn(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $autonomousSystem = AutonomousSystem::factory()->create([
            'asn' => 264001,
        ]);

        $this->actingAs($admin)
            ->post('/test/irr-objects', [
                'client_id' => $autonomousSystem->client_id,
                'autonomous_system_id' => $autonomousSystem->id,
                'object_type' => 'aut-num',
                'object_key' => 'AS264999',
                'source' => 'LOCAL',
                'status' => 'active',
                'active' => '1',
            ])
            ->assertSessionHasErrors('object_key');
    }

    public function test_non_administrator_cannot_create_irr_object(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $this->actingAs($user)
            ->post('/test/irr-objects', [
                'client_id' => $client->id,
                'object_type' => 'mntner',
                'object_key' => 'MAINT-EXAMPLE',
                'source' => 'LOCAL',
                'status' => 'active',
            ])
            ->assertForbidden();
    }
}
