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
            'document' => '11.222.333/0001-81',
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
            'document' => '11222333000181',
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

    public function test_client_receives_automatic_internal_code(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente Código LTDA',
                'country' => 'BR',
                'active' => '1',
            ])
            ->assertRedirect();

        $client = Client::query()->firstOrFail();

        $this->assertSame('CLI-000001', $client->client_code);
    }

    public function test_contract_number_can_be_generated_automatically(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente Contrato LTDA',
                'generate_contract_number' => '1',
                'country' => 'BR',
                'active' => '1',
            ])
            ->assertRedirect();

        $client = Client::query()->firstOrFail();

        $this->assertSame(
            'CTR-'.now()->format('Y').'-000001',
            $client->contract_number
        );
    }

    public function test_manual_contract_number_is_normalized(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente Contrato Manual',
                'contract_number' => ' ctr-externo/2026-10 ',
                'country' => 'BR',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'contract_number' => 'CTR-EXTERNO/2026-10',
        ]);
    }

    public function test_invalid_cpf_or_cnpj_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente Documento Inválido',
                'document' => '111.111.111-11',
                'country' => 'BR',
            ])
            ->assertSessionHasErrors('document');
    }

    public function test_phone_website_and_address_are_normalized(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente Normalizado',
                'phone' => '(11) 99999-8888',
                'website' => 'empresa.com.br',
                'postal_code' => '01001-000',
                'street' => 'Praça da Sé',
                'address_number' => '100',
                'district' => 'Sé',
                'city' => 'São Paulo',
                'state' => 'sp',
                'country' => 'br',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'phone' => '+5511999998888',
            'website' => 'https://empresa.com.br',
            'postal_code' => '01001000',
            'street' => 'Praça da Sé',
            'address_number' => '100',
            'district' => 'Sé',
            'city' => 'São Paulo',
            'state' => 'SP',
            'country' => 'BR',
        ]);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente Telefone Inválido',
                'phone' => '11111111111',
                'country' => 'BR',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_invalid_postal_code_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente CEP Inválido',
                'postal_code' => '123',
                'country' => 'BR',
            ])
            ->assertSessionHasErrors('postal_code');
    }

    public function test_client_document_must_be_unique(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        Client::factory()->create([
            'document' => '11222333000181',
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente duplicado',
                'document' => '11.222.333/0001-81',
                'country' => 'BR',
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('clients', 1);
    }
}
