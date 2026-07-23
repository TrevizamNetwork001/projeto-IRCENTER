<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_real_resource_totals(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
        ]);

        $firstClient = Client::factory()->create();
        $secondClient = Client::factory()->create();

        AutonomousSystem::factory()->create([
            'client_id' => $firstClient->id,
        ]);

        AutonomousSystem::factory()->create([
            'client_id' => $secondClient->id,
        ]);

        Prefix::factory()->create([
            'client_id' => $firstClient->id,
            'prefix' => '192.0.2.0/24',
            'ip_version' => 4,
        ]);

        Prefix::factory()->ipv6()->create([
            'client_id' => $secondClient->id,
            'prefix' => '2001:db8::/32',
            'ip_version' => 6,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totals', [
                'clients' => 2,
                'asns' => 2,
                'ipv4' => 1,
                'ipv6' => 1,
            ])
            ->assertViewHas('totalResources', 6)
            ->assertSee('Visão geral do ambiente')
            ->assertSee('6 recursos');
    }

    public function test_dashboard_supports_empty_database(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalResources', 0)
            ->assertSee('0 recursos')
            ->assertSee('Nenhuma pendência operacional');
    }

    public function test_dashboard_displays_real_operational_issues(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        User::factory()->create([
            'active' => false,
        ]);

        User::factory()->create([
            'active' => true,
            'must_change_password' => true,
        ]);

        Client::factory()->create([
            'active' => true,
        ]);

        $client = Client::factory()->create([
            'active' => true,
        ]);

        AutonomousSystem::factory()->create([
            'client_id' => $client->id,
            'active' => true,
        ]);

        Prefix::factory()->create([
            'client_id' => $client->id,
            'active' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Clientes sem ASN')
            ->assertSee('ASNs sem prefixos')
            ->assertSee('Prefixos inativos')
            ->assertSee('Usuários bloqueados')
            ->assertSee('Troca de senha pendente');

        $issues = $response->viewData('operationalIssues');

        $this->assertSame(
            1,
            $issues->firstWhere('label', 'Clientes sem ASN')['value']
        );

        $this->assertSame(
            1,
            $issues->firstWhere('label', 'ASNs sem prefixos')['value']
        );

        $this->assertSame(
            1,
            $issues->firstWhere('label', 'Prefixos inativos')['value']
        );

        $this->assertSame(
            1,
            $issues->firstWhere('label', 'Usuários bloqueados')['value']
        );

        $this->assertSame(
            1,
            $issues->firstWhere(
                'label',
                'Troca de senha pendente'
            )['value']
        );
    }

    public function test_administrator_sees_recent_audit_activity(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'updated',
            'resource_type' => 'Client',
            'resource_id' => 10,
            'resource_label' => 'Cliente de Teste',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Atividade recente')
            ->assertSee('Cliente de Teste')
            ->assertSee('updated');
    }

    public function test_viewer_does_not_see_administrative_activity(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Usuários bloqueados')
            ->assertDontSee('Troca de senha pendente')
            ->assertDontSee('Atividade recente');
    }
}
