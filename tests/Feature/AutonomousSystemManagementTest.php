<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutonomousSystemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_autonomous_systems(): void
    {
        $this->get('/autonomous-systems')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_list_and_view_autonomous_systems(): void
    {
        $user = User::factory()->create();
        $autonomousSystem = AutonomousSystem::factory()->create([
            'asn' => 264001,
            'name' => 'Trevizam Network',
        ]);

        $this->actingAs($user)
            ->get(route('autonomous-systems.index'))
            ->assertOk()
            ->assertSee('AS264001')
            ->assertSee('Trevizam Network');

        $this->actingAs($user)
            ->get(route('autonomous-systems.show', $autonomousSystem))
            ->assertOk()
            ->assertSee('AS264001');
    }

    public function test_administrator_can_create_autonomous_system_with_as_prefix(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('autonomous-systems.store'),
            [
                'client_id' => $client->id,
                'asn' => 'AS264001',
                'name' => 'Trevizam Network',
                'description' => 'Sistema autônomo principal.',
                'rir' => 'lacnic',
                'country' => 'br',
                'website' => 'https://example.com',
                'noc_contact' => 'NOC',
                'noc_email' => 'NOC@EXAMPLE.COM',
                'noc_phone' => '(11) 99999-9999',
                'notes' => 'Cadastro inicial.',
                'active' => '1',
            ]
        );

        $autonomousSystem = AutonomousSystem::query()->firstOrFail();

        $response->assertRedirect(
            route('autonomous-systems.show', $autonomousSystem)
        );

        $this->assertDatabaseHas('autonomous_systems', [
            'client_id' => $client->id,
            'asn' => 264001,
            'name' => 'Trevizam Network',
            'rir' => 'LACNIC',
            'country' => 'BR',
            'noc_email' => 'noc@example.com',
            'active' => true,
        ]);
    }

    public function test_non_administrator_cannot_create_autonomous_system(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $this->actingAs($user)
            ->post(route('autonomous-systems.store'), [
                'client_id' => $client->id,
                'asn' => 264001,
                'name' => 'Sem permissão',
                'country' => 'BR',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('autonomous_systems', 0);
    }

    public function test_administrator_can_update_and_toggle_autonomous_system(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $autonomousSystem = AutonomousSystem::factory()->create([
            'client_id' => $client->id,
            'asn' => 264001,
            'name' => 'Nome antigo',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('autonomous-systems.update', $autonomousSystem), [
                'client_id' => $client->id,
                'asn' => 'AS264001',
                'name' => 'Nome atualizado',
                'country' => 'BR',
                'active' => '1',
            ])
            ->assertRedirect(
                route('autonomous-systems.show', $autonomousSystem)
            );

        $this->assertDatabaseHas('autonomous_systems', [
            'id' => $autonomousSystem->id,
            'name' => 'Nome atualizado',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(
                route('autonomous-systems.toggle-active', $autonomousSystem)
            )
            ->assertRedirect();

        $this->assertDatabaseHas('autonomous_systems', [
            'id' => $autonomousSystem->id,
            'active' => false,
        ]);
    }

    public function test_asn_must_be_unique(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        AutonomousSystem::factory()->create([
            'asn' => 264001,
        ]);

        $this->actingAs($admin)
            ->post(route('autonomous-systems.store'), [
                'client_id' => $client->id,
                'asn' => 'AS264001',
                'name' => 'Duplicado',
                'country' => 'BR',
            ])
            ->assertSessionHasErrors('asn');

        $this->assertDatabaseCount('autonomous_systems', 1);
    }
}
