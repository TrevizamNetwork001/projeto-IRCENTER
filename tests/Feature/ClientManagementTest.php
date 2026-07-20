<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_clients(): void
    {
        $this->get('/clients')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_list_and_view_clients(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'trade_name' => 'Trevizam Network',
        ]);

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Trevizam Network');

        $this->actingAs($user)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Trevizam Network');
    }

    public function test_administrator_can_create_client(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('clients.store'), [
            'legal_name' => 'Trevizam Network LTDA',
            'trade_name' => 'Trevizam Network',
            'document' => '12.345.678/0001-90',
            'email' => 'CONTATO@EXAMPLE.COM',
            'phone' => '(11) 99999-9999',
            'website' => 'https://example.com',
            'city' => 'São Paulo',
            'state' => 'SP',
            'country' => 'br',
            'notes' => 'Cliente principal.',
            'active' => '1',
        ]);

        $client = Client::query()->firstOrFail();

        $response->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('clients', [
            'legal_name' => 'Trevizam Network LTDA',
            'trade_name' => 'Trevizam Network',
            'document' => '12345678000190',
            'email' => 'contato@example.com',
            'country' => 'BR',
            'active' => true,
        ]);
    }

    public function test_non_administrator_cannot_create_client(): void
    {
        $user = User::factory()->create([
            'role' => 'viewer',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente sem permissão',
                'country' => 'BR',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_administrator_can_update_and_toggle_client(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create([
            'legal_name' => 'Nome antigo LTDA',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('clients.update', $client), [
                'legal_name' => 'Nome atualizado LTDA',
                'country' => 'BR',
                'active' => '1',
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'legal_name' => 'Nome atualizado LTDA',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('clients.toggle-active', $client))
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'active' => false,
        ]);
    }

    public function test_client_document_must_be_unique(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        Client::factory()->create([
            'document' => '12345678000190',
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente duplicado',
                'document' => '12.345.678/0001-90',
                'country' => 'BR',
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('clients', 1);
    }
}
