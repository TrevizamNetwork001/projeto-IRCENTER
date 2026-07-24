<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_audit(): void
    {
        $this->get(route('audit.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_audit(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('audit.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_access_audit(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk();
    }

    public function test_client_creation_is_audited(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.store'), [
                'legal_name' => 'Cliente Auditoria Ltda',
                'trade_name' => 'Cliente Auditoria',
                'document' => '11222333000181',
                'email' => 'audit@example.net',
                'phone' => '11999999999',
                'country' => 'BR',
                'state' => 'SP',
                'city' => 'São Paulo',
                'address' => 'Rua de Teste, 10',
                'notes' => null,
                'active' => true,
            ])
            ->assertRedirect();

        $client = Client::query()->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'created',
            'resource_type' => 'Client',
            'resource_id' => $client->id,
            'resource_label' => 'Cliente Auditoria Ltda',
        ]);
    }

    public function test_client_update_preserves_old_and_new_values(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
        ]);

        $client = Client::factory()->create([
            'legal_name' => 'Nome Antigo',
        ]);

        $payload = $client->only([
            'legal_name',
            'trade_name',
            'email',
            'phone',
            'country',
            'state',
            'city',
            'notes',
            'active',
        ]);

        $payload['legal_name'] = 'Nome Novo';

        $this->actingAs($admin)
            ->put(route('clients.update', $client), $payload)
            ->assertRedirect();

        $log = AuditLog::query()
            ->where('resource_type', 'Client')
            ->where('resource_id', $client->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame(
            'Nome Antigo',
            $log->old_values['legal_name']
        );

        $this->assertSame(
            'Nome Novo',
            $log->new_values['legal_name']
        );
    }
}
