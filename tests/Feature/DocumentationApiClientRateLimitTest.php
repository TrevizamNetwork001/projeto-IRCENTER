<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleDocumentationApiClient;
use App\Models\ApiClient;
use App\Services\ApiCredentialService;
use App\Support\DocumentationApiScope;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocumentationApiClientRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'documentation.rate_limit' => 100,
            'documentation.client_rate_limit' => 2,
            'documentation.legacy_token_enabled' => false,
        ]);
    }

    public function test_effective_middleware_order_is_ip_auth_client_scope(): void
    {
        $route = Route::getRoutes()->getByName(
            'api.documentation.clients.index'
        );
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $expected = [
            'throttle:documentation-api',
            'documentation.api',
            'documentation.client.throttle',
            'documentation.scope:'.DocumentationApiScope::CLIENTS_READ,
        ];
        $positions = array_map(
            fn (string $entry): int => array_search(
                $entry,
                $middleware,
                true
            ),
            $expected
        );

        $this->assertSame(
            range($positions[0], $positions[0] + count($positions) - 1),
            $positions
        );
        $this->assertNotContains(
            ThrottleDocumentationApiClient::class,
            app(Kernel::class)->getMiddlewarePriority()
        );
    }

    public function test_valid_client_is_limited_with_contract_and_no_identity_leak(): void
    {
        config(['documentation.client_rate_limit' => 1]);
        $credential = $this->credential('Contrato do limiter');

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();

        $response = $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'rate_limit_exceeded')
            ->assertJsonPath('error.message', 'Limite de requisições excedido.')
            ->assertJsonPath('message', 'Limite de requisições excedido.');

        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->json('error.request_id')
        );
        $content = $response->getContent();
        $this->assertStringNotContainsString('api_client', $content);
        $this->assertStringNotContainsString(
            $credential['client']->token_prefix,
            $content
        );
        $this->assertStringNotContainsString(
            $credential['client']->token_hash,
            $content
        );
    }

    public function test_budget_is_global_across_documentation_endpoints(): void
    {
        config(['documentation.client_rate_limit' => 3]);
        $credential = $this->credential(
            'Orçamento global',
            DocumentationApiScope::all()
        );

        foreach ([
            '/api/v1/documentation/clients',
            '/api/v1/documentation/users',
            '/api/v1/documentation/prefixes',
        ] as $uri) {
            $this->withToken($credential['token'])->getJson($uri)->assertOk();
        }

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/autonomous-systems')
            ->assertTooManyRequests();
    }

    public function test_same_client_shares_budget_across_ips(): void
    {
        $credential = $this->credential('Multi-IP');

        foreach (['203.0.113.1', '203.0.113.2'] as $ip) {
            $this->fromIp($ip, $credential['token'])->assertOk();
        }

        $this->fromIp('203.0.113.3', $credential['token'])
            ->assertTooManyRequests();
    }

    public function test_clients_have_independent_budgets_on_same_ip(): void
    {
        $clientA = $this->credential('Cliente A');
        $clientB = $this->credential('Cliente B');
        $ip = '203.0.113.10';

        $this->fromIp($ip, $clientA['token'])->assertOk();
        $this->fromIp($ip, $clientA['token'])->assertOk();
        $this->fromIp($ip, $clientA['token'])->assertTooManyRequests();
        $this->fromIp($ip, $clientB['token'])->assertOk();
    }

    public function test_scope_check_runs_after_client_budget_is_consumed(): void
    {
        config(['documentation.client_rate_limit' => 1]);
        $credential = $this->credential('Sem scope', []);

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertForbidden();
        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertTooManyRequests();
    }

    public function test_authentication_failures_do_not_consume_client_budget(): void
    {
        config(['documentation.client_rate_limit' => 1]);

        $this->getJson('/api/v1/documentation/clients')->assertUnauthorized();
        $this->withToken('invalid-token')
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();

        foreach (['inactive', 'revoked', 'expired'] as $state) {
            $credential = $this->credential("Cliente {$state}");
            $attributes = match ($state) {
                'inactive' => ['is_active' => false],
                'revoked' => ['revoked_at' => now()],
                'expired' => ['expires_at' => now()->subMinute()],
            };
            $credential['client']->forceFill($attributes)->save();

            $this->withToken($credential['token'])
                ->getJson('/api/v1/documentation/clients')
                ->assertUnauthorized();
        }

        $valid = $this->credential('Cliente válido');
        $this->withToken($valid['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();
    }

    public function test_invalid_client_limit_values_fall_back_to_safe_default(): void
    {
        config(['documentation.rate_limit' => 1000]);

        foreach ([0, -1, 'invalid', 10001] as $invalid) {
            config(['documentation.client_rate_limit' => $invalid]);
            $credential = $this->credential('Config '.md5((string) $invalid));

            for ($attempt = 1; $attempt <= 120; $attempt++) {
                $this->withToken($credential['token'])
                    ->getJson('/api/v1/documentation/clients')
                    ->assertOk();
            }

            $this->withToken($credential['token'])
                ->getJson('/api/v1/documentation/clients')
                ->assertTooManyRequests();
        }
    }

    public function test_legacy_token_skips_client_budget_but_keeps_ip_budget(): void
    {
        $token = 'legacy-rate-limit-test';
        config([
            'documentation.legacy_token_enabled' => true,
            'documentation.api_token_hash' => hash('sha256', $token),
            'documentation.rate_limit' => 2,
            'documentation.client_rate_limit' => 1,
        ]);
        $ip = '203.0.113.50';

        $this->fromIp($ip, $token)->assertOk();
        $this->fromIp($ip, $token)->assertOk();
        $this->fromIp($ip, $token)->assertTooManyRequests();
    }

    /** @return array{client: ApiClient, token: string} */
    private function credential(
        string $name,
        array $scopes = [DocumentationApiScope::CLIENTS_READ]
    ): array {
        return app(ApiCredentialService::class)->create(
            $name,
            null,
            $scopes
        );
    }

    private function fromIp(string $ip, string $token)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withToken($token)
            ->getJson('/api/v1/documentation/clients');
    }
}
