<?php

namespace Tests\Feature;

use App\Jobs\TestExternalIntegration;
use App\Models\AuditLog;
use App\Models\ExternalIntegration;
use App\Models\ExternalIntegrationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExternalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_integrations(): void
    {
        $this->get(route('external-integrations.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_integrations(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERATOR,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($operator)
            ->get(route('external-integrations.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_create_integration(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('external-integrations.store'), [
                'name' => 'API de monitoramento',
                'type' => ExternalIntegration::TYPE_MONITORING,
                'endpoint' => 'https://93.184.216.34/status',
                'authentication_type' =>
                    ExternalIntegration::AUTH_BEARER,
                'secret' => 'token-super-secreto',
                'timeout_seconds' => 10,
                'active' => '1',
            ]);

        $integration = ExternalIntegration::query()
            ->firstOrFail();

        $response->assertRedirect(
            route('external-integrations.show', $integration)
        );

        $this->assertSame(
            'token-super-secreto',
            $integration->secret
        );

        $rawSecret = $integration->getRawOriginal('secret');

        $this->assertNotSame(
            'token-super-secreto',
            $rawSecret
        );

        $this->assertStringNotContainsString(
            'token-super-secreto',
            (string) $rawSecret
        );

        $log = AuditLog::query()
            ->where('action', 'created')
            ->where('resource_type', 'ExternalIntegration')
            ->firstOrFail();

        $this->assertFalse(
            str_contains(
                json_encode($log->new_values),
                'token-super-secreto'
            )
        );

        $this->assertTrue(
            (bool) $log->new_values['has_secret']
        );
    }

    public function test_local_endpoint_is_blocked(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('external-integrations.store'), [
                'name' => 'Endpoint local',
                'type' => ExternalIntegration::TYPE_WEBHOOK,
                'endpoint' => 'https://127.0.0.1/status',
                'authentication_type' =>
                    ExternalIntegration::AUTH_NONE,
                'timeout_seconds' => 10,
                'active' => '1',
            ])
            ->assertSessionHasErrors('endpoint');

        $this->assertDatabaseCount(
            'external_integrations',
            0
        );
    }

    public function test_http_endpoint_is_blocked(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('external-integrations.store'), [
                'name' => 'Endpoint inseguro',
                'type' => ExternalIntegration::TYPE_WEBHOOK,
                'endpoint' => 'http://93.184.216.34/status',
                'authentication_type' =>
                    ExternalIntegration::AUTH_NONE,
                'timeout_seconds' => 10,
                'active' => '1',
            ])
            ->assertSessionHasErrors('endpoint');
    }

    public function test_administrator_can_queue_connectivity_test(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $integration = ExternalIntegration::create([
            'name' => 'Teste de fila',
            'type' => ExternalIntegration::TYPE_MONITORING,
            'endpoint' => 'https://93.184.216.34/status',
            'authentication_type' =>
                ExternalIntegration::AUTH_NONE,
            'timeout_seconds' => 10,
            'active' => true,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route(
                'external-integrations.test',
                $integration
            ))
            ->assertRedirect();

        $run = ExternalIntegrationRun::query()
            ->firstOrFail();

        $this->assertSame(
            ExternalIntegration::TEST_PENDING,
            $run->status
        );

        Queue::assertPushed(
            TestExternalIntegration::class,
            fn (TestExternalIntegration $job): bool =>
                $job->integrationId === $integration->id
                && $job->runId === $run->id
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'integration_test_queued',
            'resource_type' => 'ExternalIntegration',
            'resource_id' => $integration->id,
        ]);
    }

    public function test_disabled_integration_cannot_be_tested(): void
    {
        Queue::fake();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $integration = ExternalIntegration::create([
            'name' => 'Integração desativada',
            'type' => ExternalIntegration::TYPE_MONITORING,
            'endpoint' => 'https://93.184.216.34/status',
            'authentication_type' =>
                ExternalIntegration::AUTH_NONE,
            'timeout_seconds' => 10,
            'active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route(
                'external-integrations.test',
                $integration
            ))
            ->assertStatus(409);

        $this->assertDatabaseCount(
            'external_integration_runs',
            0
        );

        Queue::assertNothingPushed();
    }

    public function test_job_records_success_without_storing_body(): void
    {
        Http::fake([
            'https://93.184.216.34/*' => Http::response(
                ['secret_response' => 'nao-armazenar'],
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $integration = ExternalIntegration::create([
            'name' => 'Endpoint público',
            'type' => ExternalIntegration::TYPE_MONITORING,
            'endpoint' => 'https://93.184.216.34/status',
            'authentication_type' =>
                ExternalIntegration::AUTH_BEARER,
            'secret' => 'bearer-nao-vazar',
            'timeout_seconds' => 10,
            'active' => true,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $run = $integration->runs()->create([
            'requested_by_user_id' => $admin->id,
            'operation' => 'connectivity_test',
            'status' => ExternalIntegration::TEST_PENDING,
        ]);

        app(TestExternalIntegration::class, [
            'integrationId' => $integration->id,
            'runId' => $run->id,
        ])->handle(app(
            \App\Support\ExternalEndpointGuard::class
        ));

        $run->refresh();
        $integration->refresh();

        $this->assertSame(
            ExternalIntegration::TEST_SUCCESS,
            $run->status
        );

        $this->assertSame(200, $run->http_status);
        $this->assertSame('93.184.216.34', $run->resolved_ip);

        $serializedRun = json_encode(
            $run->getAttributes()
        );

        $this->assertStringNotContainsString(
            'nao-armazenar',
            $serializedRun
        );

        $this->assertStringNotContainsString(
            'bearer-nao-vazar',
            $serializedRun
        );

        $this->assertSame(
            ExternalIntegration::TEST_SUCCESS,
            $integration->last_test_status
        );
    }

    public function test_edit_page_does_not_display_secret(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $integration = ExternalIntegration::create([
            'name' => 'Integração protegida',
            'type' => ExternalIntegration::TYPE_IRR,
            'endpoint' => 'https://93.184.216.34/api',
            'authentication_type' =>
                ExternalIntegration::AUTH_BEARER,
            'secret' => 'segredo-nao-exibir',
            'timeout_seconds' => 10,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route(
                'external-integrations.edit',
                $integration
            ))
            ->assertOk()
            ->assertDontSee('segredo-nao-exibir');
    }
}
