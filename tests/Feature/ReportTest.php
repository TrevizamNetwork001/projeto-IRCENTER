<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\RoutingIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_reports(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_reports(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        Client::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Relatórios')
            ->assertViewHas(
                'resourceTotals',
                fn (array $totals): bool =>
                    $totals['clients'] === 2
            );
    }

    public function test_user_can_export_clients_csv(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        Client::factory()->create([
            'legal_name' => 'Cliente Exportação Ltda',
            'trade_name' => 'Cliente Exportação',
            'email' => 'exportacao@example.net',
        ]);

        $response = $this->actingAs($user)
            ->get(route('reports.export', [
                'report' => 'clients',
            ]));

        $response
            ->assertOk()
            ->assertHeader(
                'content-type',
                'text/csv; charset=UTF-8'
            );

        $this->assertStringContainsString(
            'Cliente Exportação Ltda',
            $response->streamedContent()
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'exported_report',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'resource_label' => 'Relatório: Clientes',
        ]);
    }

    public function test_export_neutralizes_every_dangerous_text_cell(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        Client::factory()->create([
            'legal_name' => '=SUM(A1:A2)',
            'trade_name' => "\t+cmd",
            'email' => 'safe@example.net',
        ]);

        $content = $this->actingAs($user)
            ->get(route('reports.export', ['report' => 'clients']))
            ->streamedContent();

        $this->assertStringContainsString("'=SUM(A1:A2)", $content);
        $this->assertStringContainsString("'\t+cmd", $content);
        $this->assertStringNotContainsString(";=SUM(A1:A2)", $content);
    }

    public function test_incident_report_respects_severity_filter(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        RoutingIncident::create([
            'reference' => 'INC-2026-000010',
            'reported_by_user_id' => $user->id,
            'title' => 'Incidente crítico',
            'type' => RoutingIncident::TYPE_PREFIX_HIJACK,
            'severity' => RoutingIncident::SEVERITY_CRITICAL,
            'status' => RoutingIncident::STATUS_OPEN,
            'summary' => 'Teste crítico.',
            'detected_at' => now(),
        ]);

        RoutingIncident::create([
            'reference' => 'INC-2026-000011',
            'reported_by_user_id' => $user->id,
            'title' => 'Incidente médio',
            'type' => RoutingIncident::TYPE_OTHER,
            'severity' => RoutingIncident::SEVERITY_MEDIUM,
            'status' => RoutingIncident::STATUS_OPEN,
            'summary' => 'Teste médio.',
            'detected_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('reports.index', [
                'severity' => RoutingIncident::SEVERITY_CRITICAL,
            ]))
            ->assertOk();

        $this->assertSame(
            1,
            $response->viewData('incidentTotals')['total']
        );

        $response
            ->assertSee('Incidente crítico')
            ->assertDontSee('Incidente médio');
    }

    public function test_invalid_report_export_returns_not_found(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)
            ->get('/reports/invalid/csv')
            ->assertNotFound();
    }
}
