<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Prefix;
use App\Models\RoutingIncident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientScopedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_client_id_sees_prefix_of_any_client(): void
    {
        $unrestricted = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
            'client_id' => null,
        ]);

        $client = Client::factory()->create();
        $prefix = Prefix::factory()->create(['client_id' => $client->id]);

        $this->actingAs($unrestricted)
            ->get(route('prefixes.show', $prefix))
            ->assertOk();
    }

    public function test_staff_scoped_to_client_cannot_see_prefix_of_another_client(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $scopedUser = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
            'client_id' => $clientA->id,
        ]);

        $prefixOfB = Prefix::factory()->create(['client_id' => $clientB->id]);

        $this->actingAs($scopedUser)
            ->get(route('prefixes.show', $prefixOfB))
            ->assertNotFound();
    }

    public function test_staff_scoped_to_client_still_sees_prefix_of_own_client(): void
    {
        $client = Client::factory()->create();

        $scopedUser = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
            'client_id' => $client->id,
        ]);

        $prefix = Prefix::factory()->create(['client_id' => $client->id]);

        $this->actingAs($scopedUser)
            ->get(route('prefixes.show', $prefix))
            ->assertOk();
    }

    public function test_staff_scoped_to_client_cannot_see_routing_incident_of_another_client(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $scopedUser = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
            'client_id' => $clientA->id,
        ]);

        $incidentOfB = RoutingIncident::query()->create([
            'reference' => 'INC-2026-000001',
            'client_id' => $clientB->id,
            'title' => 'Incidente de outro cliente',
            'type' => RoutingIncident::TYPE_UNEXPECTED_ANNOUNCEMENT,
            'severity' => RoutingIncident::SEVERITY_HIGH,
            'status' => RoutingIncident::STATUS_OPEN,
            'summary' => 'Não deve ser visível para clientA.',
            'detected_at' => now(),
        ]);

        $this->actingAs($scopedUser)
            ->get(route('routing-incidents.show', $incidentOfB))
            ->assertNotFound();
    }
}
