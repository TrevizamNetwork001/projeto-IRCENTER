<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_is_minimal_and_stateless(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->once()->andReturn('PONG');
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $response = $this->getJson(route('health.ready'));

        $response
            ->assertOk()
            ->assertExactJson(['status' => 'ready'])
            ->assertHeaderMissing('Set-Cookie');

        $this->assertStringNotContainsString(
            'XSRF-TOKEN',
            implode(';', $response->headers->getCookies())
        );
        $this->assertStringNotContainsString(
            'laravel_session',
            implode(';', $response->headers->getCookies())
        );
    }

    public function test_readiness_route_does_not_use_web_or_auth_middleware(): void
    {
        $middleware = app('router')
            ->getRoutes()
            ->getByName('health.ready')
            ?->gatherMiddleware() ?? [];

        $this->assertSame(['api'], $middleware);
    }

    public function test_readiness_is_unavailable_when_database_fails(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andThrow(new RuntimeException('database detail'));
        Redis::shouldReceive('connection')->never();

        $this->getJson(route('health.ready'))
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable'])
            ->assertHeaderMissing('Set-Cookie')
            ->assertDontSee('database detail');
    }

    public function test_readiness_is_unavailable_when_redis_fails(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping')
            ->once()
            ->andThrow(new RuntimeException('redis detail'));
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $this->getJson(route('health.ready'))
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable'])
            ->assertHeaderMissing('Set-Cookie')
            ->assertDontSee('redis detail');
    }

    public function test_readiness_only_accepts_get_and_head(): void
    {
        $this->postJson('/health/ready')->assertMethodNotAllowed();
    }

    public function test_liveness_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_guest_cannot_access_diagnostic(): void
    {
        $this->get(route('system-diagnostic.index'))
            ->assertRedirect(route('login'));
    }

    public function test_security_headers_are_applied(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            )
            ->assertHeader(
                'X-Frame-Options',
                'SAMEORIGIN'
            )
            ->assertHeader(
                'Referrer-Policy',
                'strict-origin-when-cross-origin'
            )
            ->assertHeader(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=()'
            )
            ->assertHeader(
                'Cross-Origin-Opener-Policy',
                'same-origin'
            );
    }

    public function test_non_administrator_cannot_access_diagnostic(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('system-diagnostic.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_access_diagnostic(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('system-diagnostic.index'))
            ->assertOk()
            ->assertSee('Diagnóstico do sistema')
            ->assertSee('PostgreSQL')
            ->assertSee('Redis')
            ->assertSee('Fila');
    }

    public function test_custom_forbidden_page_is_rendered(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('system-diagnostic.index'))
            ->assertForbidden()
            ->assertSee('Acesso negado')
            ->assertSee('IRCENTER');
    }

    public function test_custom_not_found_page_is_rendered(): void
    {
        $this->get('/endereco-que-nao-existe')
            ->assertNotFound()
            ->assertSee('Página não encontrada')
            ->assertSee('IRCENTER');
    }

    public function test_guest_cannot_update_backup_retention(): void
    {
        $this->put(route('system-diagnostic.backup-retention.update'), [
            'retention_daily_days' => 14,
            'retention_weekly_days' => 90,
            'retention_monthly_days' => 730,
        ])->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_update_backup_retention(): void
    {
        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)
            ->put(route('system-diagnostic.backup-retention.update'), [
                'retention_daily_days' => 14,
                'retention_weekly_days' => 90,
                'retention_monthly_days' => 730,
            ])
            ->assertForbidden();
    }

    public function test_administrator_backup_retention_update_rejects_invalid_order(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('system-diagnostic.index'))
            ->put(route('system-diagnostic.backup-retention.update'), [
                'retention_daily_days' => 90,
                'retention_weekly_days' => 14,
                'retention_monthly_days' => 730,
            ])
            ->assertRedirect(route('system-diagnostic.index'))
            ->assertSessionHasErrors('retention_daily_days');
    }

    public function test_administrator_backup_retention_update_rejects_out_of_range(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('system-diagnostic.backup-retention.update'), [
                'retention_daily_days' => 0,
                'retention_weekly_days' => 90,
                'retention_monthly_days' => 730,
            ])
            ->assertSessionHasErrors('retention_daily_days');
    }
}
