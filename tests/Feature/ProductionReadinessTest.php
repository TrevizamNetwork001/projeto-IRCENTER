<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_is_available(): void
    {
        $this->getJson(route('health.ready'))
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.application', true)
            ->assertJsonPath('checks.database', true)
            ->assertJsonPath('checks.redis', true);
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
}
