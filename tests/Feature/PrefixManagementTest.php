<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrefixManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_prefixes(): void
    {
        $this->get(route('prefixes.ipv4'))
            ->assertRedirect(route('login'));

        $this->get(route('prefixes.ipv6'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_list_ipv4_and_ipv6_prefixes(): void
    {
        $user = User::factory()->create();

        Prefix::factory()->create([
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        Prefix::factory()->ipv6()->create([
            'prefix' => '2001:db8::/32',
            'ip_version' => 6,
        ]);

        $this->actingAs($user)
            ->get(route('prefixes.ipv4'))
            ->assertOk()
            ->assertSee('192.0.2.0/24')
            ->assertDontSee('2001:db8::/32');

        $this->actingAs($user)
            ->get(route('prefixes.ipv6'))
            ->assertOk()
            ->assertSee('2001:db8::/32')
            ->assertDontSee('192.0.2.0/24');
    }

    public function test_administrator_can_create_and_normalize_ipv4_prefix(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('prefixes.store'),
            [
                'client_id' => $client->id,
                'autonomous_system_id' => '',
                'prefix' => '192.0.2.37/24',
                'ip_version' => 6,
                'description' => 'Bloco principal',
                'rir' => 'lacnic',
                'country' => 'br',
                'allocation_status' => 'allocated',
                'purpose' => 'BGP',
                'notes' => 'Cadastro inicial.',
                'active' => '1',
            ]
        );

        $prefix = Prefix::query()->firstOrFail();

        $response->assertRedirect(route('prefixes.show', $prefix));

        $this->assertDatabaseHas('prefixes', [
            'client_id' => $client->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
            'rir' => 'LACNIC',
            'country' => 'BR',
            'allocation_status' => 'allocated',
            'active' => true,
        ]);
    }

    public function test_administrator_can_create_and_normalize_ipv6_prefix(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $this->actingAs($admin)->post(
            route('prefixes.store'),
            [
                'client_id' => $client->id,
                'prefix' => '2001:0db8:0000:0001::1234/48',
                'ip_version' => 4,
                'country' => 'BR',
                'allocation_status' => 'assigned',
                'active' => '1',
            ]
        )->assertRedirect();

        $this->assertDatabaseHas('prefixes', [
            'prefix' => '2001:db8::/48',
            'ip_version' => 6,
            'allocation_status' => 'assigned',
        ]);
    }

    public function test_asn_must_belong_to_selected_client(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $selectedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $autonomousSystem = AutonomousSystem::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $this->actingAs($admin)
            ->post(route('prefixes.store'), [
                'client_id' => $selectedClient->id,
                'autonomous_system_id' => $autonomousSystem->id,
                'prefix' => '198.51.100.0/24',
                'ip_version' => 4,
                'country' => 'BR',
                'allocation_status' => 'allocated',
                'active' => '1',
            ])
            ->assertSessionHasErrors('autonomous_system_id');

        $this->assertDatabaseCount('prefixes', 0);
    }

    public function test_non_administrator_cannot_create_prefix(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $this->actingAs($user)
            ->post(route('prefixes.store'), [
                'client_id' => $client->id,
                'prefix' => '203.0.113.0/24',
                'ip_version' => 4,
                'country' => 'BR',
                'allocation_status' => 'allocated',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('prefixes', 0);
    }

    public function test_canonical_prefix_must_be_unique(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        Prefix::factory()->create([
            'client_id' => $client->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        $this->actingAs($admin)
            ->post(route('prefixes.store'), [
                'client_id' => $client->id,
                'prefix' => '192.0.2.99/24',
                'ip_version' => 4,
                'country' => 'BR',
                'allocation_status' => 'allocated',
                'active' => '1',
            ])
            ->assertSessionHasErrors('prefix');

        $this->assertDatabaseCount('prefixes', 1);
    }

    public function test_invalid_cidr_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->post(route('prefixes.store'), [
                'client_id' => $client->id,
                'prefix' => '192.0.2.1/99',
                'ip_version' => 4,
                'country' => 'BR',
                'allocation_status' => 'allocated',
            ])
            ->assertSessionHasErrors('prefix');

        $this->assertDatabaseCount('prefixes', 0);
    }

    public function test_administrator_can_update_and_toggle_prefix(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create();

        $prefix = Prefix::factory()->create([
            'client_id' => $client->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('prefixes.update', $prefix), [
                'client_id' => $client->id,
                'prefix' => '198.51.100.77/24',
                'ip_version' => 6,
                'description' => 'Bloco atualizado',
                'country' => 'BR',
                'allocation_status' => 'assigned',
                'active' => '1',
            ])
            ->assertRedirect(route('prefixes.show', $prefix));

        $this->assertDatabaseHas('prefixes', [
            'id' => $prefix->id,
            'prefix' => '198.51.100.0/24',
            'ip_version' => 4,
            'description' => 'Bloco atualizado',
            'allocation_status' => 'assigned',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('prefixes.toggle-active', $prefix))
            ->assertRedirect();

        $this->assertDatabaseHas('prefixes', [
            'id' => $prefix->id,
            'active' => false,
        ]);
    }
}
