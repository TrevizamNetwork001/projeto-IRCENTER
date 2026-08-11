<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientContactManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_contact_card_is_visible(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->viewer())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('CONTATOS DO CLIENTE')
            ->assertSee('Nenhum contato estruturado cadastrado.')
            ->assertDontSee('+ Adicionar contato');

        $this->actingAs($this->admin())
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('+ Adicionar contato');
    }

    public function test_administrator_can_create_financial_contact(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('clients.contacts.store', $client), [
                'type' => ClientContact::TYPE_FINANCIAL,
                'name' => ' Financeiro Cliente ',
                'email' => 'FINANCEIRO@CLIENTE.TEST',
                'phone' => '(11) 99999-8888',
                'active' => '1',
                'is_primary' => '0',
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'type' => ClientContact::TYPE_FINANCIAL,
            'name' => 'Financeiro Cliente',
            'email' => 'financeiro@cliente.test',
            'phone' => '+5511999998888',
            'active' => true,
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client_contact.created',
            'resource_type' => 'ClientContact',
        ]);
    }

    public function test_administrator_can_edit_contact(): void
    {
        [$client, $contact] = $this->contact();

        $this->actingAs($this->admin())
            ->put(route('clients.contacts.update', [$client, $contact]), [
                'type' => ClientContact::TYPE_FISCAL,
                'name' => 'Fiscal Atualizado',
                'email' => 'fiscal@cliente.test',
                'phone' => '',
                'active' => '1',
                'is_primary' => '0',
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_contacts', [
            'id' => $contact->id,
            'type' => ClientContact::TYPE_FISCAL,
            'name' => 'Fiscal Atualizado',
            'email' => 'fiscal@cliente.test',
        ]);
    }

    public function test_deactivation_clears_primary_and_reactivation_does_not_restore_it(): void
    {
        [$client, $contact] = $this->contact(['is_primary' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('clients.contacts.toggle-active', [$client, $contact]))
            ->assertRedirect();
        $contact->refresh();
        $this->assertFalse($contact->active);
        $this->assertFalse($contact->is_primary);

        $this->actingAs($admin)
            ->patch(route('clients.contacts.toggle-active', [$client, $contact]))
            ->assertRedirect();
        $contact->refresh();
        $this->assertTrue($contact->active);
        $this->assertFalse($contact->is_primary);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_contact.deactivated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_contact.activated']);
    }

    public function test_creating_primary_removes_previous_primary_of_same_type(): void
    {
        [$client, $previous] = $this->contact(['is_primary' => true]);

        $this->actingAs($this->admin())
            ->post(route('clients.contacts.store', $client), [
                'type' => ClientContact::TYPE_FINANCIAL,
                'name' => 'Novo principal',
                'email' => 'novo@cliente.test',
                'phone' => '',
                'active' => '1',
                'is_primary' => '1',
            ])
            ->assertRedirect();

        $this->assertFalse($previous->refresh()->is_primary);
        $this->assertDatabaseHas('client_contacts', [
            'email' => 'novo@cliente.test',
            'active' => true,
            'is_primary' => true,
        ]);
    }

    public function test_different_types_can_each_have_a_primary_contact(): void
    {
        [$client, $financial] = $this->contact(['is_primary' => true]);
        $general = ClientContact::query()->create([
            'client_id' => $client->id,
            'type' => ClientContact::TYPE_GENERAL,
            'name' => 'Geral',
            'email' => 'geral@cliente.test',
            'active' => true,
            'is_primary' => false,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('clients.contacts.toggle-primary', [$client, $general]))
            ->assertRedirect();

        $this->assertTrue($financial->refresh()->is_primary);
        $this->assertTrue($general->refresh()->is_primary);
    }

    public function test_administrator_can_mark_and_unmark_primary(): void
    {
        [$client, $contact] = $this->contact();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('clients.contacts.toggle-primary', [$client, $contact]))
            ->assertRedirect();
        $this->assertTrue($contact->refresh()->is_primary);

        $this->actingAs($admin)
            ->patch(route('clients.contacts.toggle-primary', [$client, $contact]))
            ->assertRedirect();
        $this->assertFalse($contact->refresh()->is_primary);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client_contact.primary_changed',
            'resource_id' => $contact->id,
        ]);
    }

    public function test_viewer_cannot_manage_contacts(): void
    {
        [$client, $contact] = $this->contact();
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get(route('clients.contacts.create', $client))->assertForbidden();
        $this->actingAs($viewer)->post(route('clients.contacts.store', $client), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put(route('clients.contacts.update', [$client, $contact]), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->patch(route('clients.contacts.toggle-active', [$client, $contact]))->assertForbidden();
    }

    public function test_contact_from_another_client_is_blocked(): void
    {
        $client = Client::factory()->create();
        [, $contact] = $this->contact();

        $this->actingAs($this->admin())
            ->get(route('clients.contacts.edit', [$client, $contact]))
            ->assertNotFound();
        $this->actingAs($this->admin())
            ->put(route('clients.contacts.update', [$client, $contact]), $this->payload())
            ->assertNotFound();
    }

    public function test_invalid_type_email_and_blank_name_are_rejected(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('clients.contacts.store', $client), [
                'type' => 'other',
                'name' => '   ',
                'email' => 'invalido',
                'phone' => '',
                'active' => '1',
                'is_primary' => '0',
            ])
            ->assertSessionHasErrors(['type', 'name', 'email']);

        $this->assertDatabaseCount('client_contacts', 0);
    }

    /** @return array{Client, ClientContact} */
    private function contact(array $attributes = []): array
    {
        $client = Client::factory()->create();
        $contact = ClientContact::query()->create(array_merge([
            'client_id' => $client->id,
            'type' => ClientContact::TYPE_FINANCIAL,
            'name' => 'Financeiro',
            'email' => 'financeiro@cliente.test',
            'active' => true,
            'is_primary' => false,
        ], $attributes));

        return [$client, $contact];
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            'type' => ClientContact::TYPE_FINANCIAL,
            'name' => 'Financeiro',
            'email' => 'financeiro@cliente.test',
            'phone' => '',
            'active' => '1',
            'is_primary' => '0',
        ];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'active' => true]);
    }

    private function viewer(): User
    {
        return User::factory()->create(['role' => 'viewer', 'active' => true]);
    }
}
