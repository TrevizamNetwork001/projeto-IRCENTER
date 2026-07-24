<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\RoutingIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_incidents(): void
    {
        $this->get(route('routing-incidents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_viewer_can_list_but_cannot_create_incident(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('routing-incidents.index'))
            ->assertOk()
            ->assertSee('Incidentes');

        $this->actingAs($viewer)
            ->get(route('routing-incidents.create'))
            ->assertForbidden();
    }

    public function test_operator_can_create_incident(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $client = Client::factory()->create();

        $response = $this->actingAs($operator)
            ->post(route('routing-incidents.store'), [
                'client_id' => $client->id,
                'title' => 'Anúncio inesperado detectado',
                'type' => RoutingIncident::TYPE_UNEXPECTED_ANNOUNCEMENT,
                'severity' => RoutingIncident::SEVERITY_HIGH,
                'status' => RoutingIncident::STATUS_OPEN,
                'summary' => 'Prefixo anunciado por origem inesperada.',
                'detected_at' => now()->format('Y-m-d H:i:s'),
            ]);

        $incident = RoutingIncident::query()->firstOrFail();

        $response->assertRedirect(
            route('routing-incidents.show', $incident)
        );

        $this->assertMatchesRegularExpression(
            '/^INC-\d{4}-\d{6}$/',
            $incident->reference
        );

        $this->assertDatabaseHas('routing_incident_updates', [
            'routing_incident_id' => $incident->id,
            'kind' => 'created',
            'new_status' => RoutingIncident::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'resource_type' => 'RoutingIncident',
            'resource_id' => $incident->id,
        ]);
    }

    public function test_incident_resources_must_belong_to_same_client(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $selectedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $asn = AutonomousSystem::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $this->actingAs($operator)
            ->post(route('routing-incidents.store'), [
                'client_id' => $selectedClient->id,
                'autonomous_system_id' => $asn->id,
                'title' => 'Incidente inconsistente',
                'type' => RoutingIncident::TYPE_ROUTE_LEAK,
                'severity' => RoutingIncident::SEVERITY_MEDIUM,
                'status' => RoutingIncident::STATUS_OPEN,
                'summary' => 'Teste de validação.',
                'detected_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('autonomous_system_id');

        $this->assertDatabaseCount('routing_incidents', 0);
    }

    public function test_operator_can_change_status_and_create_timeline(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $incident = RoutingIncident::create([
            'reference' => 'INC-2026-000001',
            'reported_by_user_id' => $operator->id,
            'title' => 'Incidente em investigação',
            'type' => RoutingIncident::TYPE_RPKI_INVALID,
            'severity' => RoutingIncident::SEVERITY_HIGH,
            'status' => RoutingIncident::STATUS_OPEN,
            'summary' => 'Validação RPKI divergente.',
            'detected_at' => now(),
        ]);

        $this->actingAs($operator)
            ->put(route('routing-incidents.update', $incident), [
                'title' => $incident->title,
                'type' => $incident->type,
                'severity' => $incident->severity,
                'status' => RoutingIncident::STATUS_INVESTIGATING,
                'summary' => $incident->summary,
                'detected_at' => $incident->detected_at
                    ->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(
                route('routing-incidents.show', $incident)
            );

        $incident->refresh();

        $this->assertSame(
            RoutingIncident::STATUS_INVESTIGATING,
            $incident->status
        );

        $this->assertNotNull($incident->acknowledged_at);

        $this->assertDatabaseHas('routing_incident_updates', [
            'routing_incident_id' => $incident->id,
            'kind' => 'status_change',
            'old_status' => RoutingIncident::STATUS_OPEN,
            'new_status' => RoutingIncident::STATUS_INVESTIGATING,
        ]);
    }

    public function test_operator_can_add_timeline_note(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $incident = RoutingIncident::create([
            'reference' => 'INC-2026-000002',
            'reported_by_user_id' => $operator->id,
            'title' => 'Incidente de teste',
            'type' => RoutingIncident::TYPE_SECURITY,
            'severity' => RoutingIncident::SEVERITY_MEDIUM,
            'status' => RoutingIncident::STATUS_OPEN,
            'summary' => 'Resumo.',
            'detected_at' => now(),
        ]);

        $this->actingAs($operator)
            ->post(
                route(
                    'routing-incidents.updates.store',
                    $incident
                ),
                [
                    'message' => 'Contato realizado com o provedor.',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas('routing_incident_updates', [
            'routing_incident_id' => $incident->id,
            'user_id' => $operator->id,
            'kind' => 'note',
            'message' => 'Contato realizado com o provedor.',
        ]);
    }

    public function test_operator_receives_critical_incident_notification(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        RoutingIncident::create([
            'reference' => 'INC-2026-000003',
            'reported_by_user_id' => $operator->id,
            'title' => 'Sequestro de prefixo',
            'type' => RoutingIncident::TYPE_PREFIX_HIJACK,
            'severity' => RoutingIncident::SEVERITY_CRITICAL,
            'status' => RoutingIncident::STATUS_OPEN,
            'summary' => 'Origem não autorizada detectada.',
            'detected_at' => now(),
        ]);

        $this->artisan('ircenter:sync-notifications')
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $operator->id,
            'unique_key' => 'critical-routing-incidents',
            'priority' => 'critical',
            'read_at' => null,
            'resolved_at' => null,
        ]);
    }
}
