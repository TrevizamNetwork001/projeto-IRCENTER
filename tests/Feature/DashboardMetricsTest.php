<?php

namespace Tests\Feature;

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
        $user = User::factory()->create();

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
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalResources', 0)
            ->assertSee('0 recursos');
    }
}
