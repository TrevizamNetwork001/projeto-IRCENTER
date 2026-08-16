<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\DocumentationResourceController;
use App\Models\ApiClient;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use App\Services\ApiCredentialService;
use App\Support\DocumentationApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentationApiContractTest extends TestCase
{
    use RefreshDatabase;

    private const SPEC = 'docs/openapi/ircenter-api-v1.yaml';

    /** @var array<string, mixed> */
    private array $openApi;

    protected function setUp(): void
    {
        parent::setUp();

        $contents = file_get_contents(base_path(self::SPEC));
        $this->assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->openApi = $decoded;
    }

    public function test_openapi_31_exists_is_valid_and_has_no_broken_refs(): void
    {
        $this->assertFileExists(base_path(self::SPEC));
        $this->assertSame('3.1.0', $this->openApi['openapi']);
        $this->assertSame('IRCENTER API', $this->openApi['info']['title']);
        $this->assertSame('1.0.0', $this->openApi['info']['version']);

        $this->walkRefs($this->openApi);
    }

    public function test_route_collection_and_openapi_have_identical_operations(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with(
                $route->uri(),
                'api/v1/documentation'
            ))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn (string $method) => in_array(
                    $method,
                    ['HEAD', 'OPTIONS'],
                    true
                ))
                ->map(fn (string $method) => strtolower($method).' /'.$route->uri()))
            ->sort()
            ->values()
            ->all();

        $documented = collect($this->openApi['paths'])
            ->flatMap(fn (array $path, string $uri) => collect($path)
                ->keys()
                ->filter(fn (string $method) => in_array(
                    $method,
                    ['get', 'post', 'put', 'patch', 'delete'],
                    true
                ))
                ->map(fn (string $method) => $method.' '.$uri))
            ->sort()
            ->values()
            ->all();

        $this->assertSame($routes, $documented);
    }

    public function test_bearer_auth_scopes_and_operation_authorization_are_documented(): void
    {
        $scheme = $this->openApi['components']['securitySchemes']['bearerAuth'];
        $this->assertSame('http', $scheme['type']);
        $this->assertSame('bearer', $scheme['scheme']);
        $this->assertStringNotContainsStringIgnoringCase(
            'jwt',
            json_encode($this->openApi, JSON_THROW_ON_ERROR)
        );

        $this->assertSame(
            DocumentationApiScope::all(),
            array_keys($this->openApi['components']['x-scopes'])
        );

        $expected = [
            '/api/v1/documentation/clients' => DocumentationApiScope::CLIENTS_READ,
            '/api/v1/documentation/clients/{client}' => DocumentationApiScope::CLIENTS_READ,
            '/api/v1/documentation/users' => DocumentationApiScope::USERS_READ,
            '/api/v1/documentation/autonomous-systems' => DocumentationApiScope::NETWORK_READ,
            '/api/v1/documentation/prefixes' => DocumentationApiScope::NETWORK_READ,
        ];

        foreach ($expected as $path => $scope) {
            $operation = $this->openApi['paths'][$path]['get'];
            $this->assertSame($scope, $operation['x-required-scope']);
            $this->assertContains(
                DocumentationApiScope::PII_READ,
                $operation['x-additional-scopes']
            );
        }
    }

    public function test_pagination_and_all_implemented_filters_are_documented(): void
    {
        $this->assertSame(
            100,
            $this->openApi['components']['parameters']['PerPage']['x-maximum-applied']
        );

        $expected = [
            '/api/v1/documentation/clients' => ['page', 'per_page', 'active_only'],
            '/api/v1/documentation/users' => ['page', 'per_page', 'active_only'],
            '/api/v1/documentation/autonomous-systems' => ['page', 'per_page', 'active_only', 'client_id'],
            '/api/v1/documentation/prefixes' => ['page', 'per_page', 'active_only', 'client_id', 'ip_version'],
        ];

        foreach ($expected as $path => $names) {
            $actual = collect($this->openApi['paths'][$path]['get']['parameters'])
                ->map(fn (array $parameter) => $this->resolveRef($parameter)['name'])
                ->all();
            $this->assertSame($names, $actual);
        }
    }

    public function test_error_responses_follow_envelope_and_correlate_request_id(): void
    {
        $missing = $this->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'authentication_required')
            ->assertHeader('X-Request-ID');
        $this->assertSame(
            $missing->headers->get('X-Request-ID'),
            $missing->json('error.request_id')
        );

        $invalid = $this->withToken('irc_api_invalid_for_test')
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
        $this->assertSame(
            $invalid->headers->get('X-Request-ID'),
            $invalid->json('error.request_id')
        );

        $credential = $this->credential([]);
        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope');
    }

    public function test_not_found_and_validation_errors_follow_contract(): void
    {
        $credential = $this->credential([
            DocumentationApiScope::CLIENTS_READ,
            DocumentationApiScope::NETWORK_READ,
        ]);

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource_not_found');

        foreach ([
            '/api/v1/documentation/clients?page=zero',
            '/api/v1/documentation/clients?page=0',
            '/api/v1/documentation/clients?per_page=zero',
            '/api/v1/documentation/clients?per_page=0',
            '/api/v1/documentation/clients?active_only=unknown',
            '/api/v1/documentation/autonomous-systems?client_id=invalid',
            '/api/v1/documentation/prefixes?ip_version=5',
        ] as $uri) {
            $this->withToken($credential['token'])
                ->getJson($uri)
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'validation_error')
                ->assertJsonStructure(['error' => ['details']]);
        }
    }

    public function test_per_page_above_limit_is_clamped_for_compatibility(): void
    {
        Client::factory()->count(101)->create();
        $credential = $this->credential([DocumentationApiScope::CLIENTS_READ]);

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients?per_page=101')
            ->assertOk()
            ->assertJsonPath('per_page', 100)
            ->assertJsonCount(100, 'data');
    }

    public function test_public_filters_change_results(): void
    {
        $activeClient = Client::factory()->create(['active' => true]);
        Client::factory()->create(['active' => false]);
        AutonomousSystem::factory()->create(['client_id' => $activeClient->id]);
        $other = Client::factory()->create();
        AutonomousSystem::factory()->create(['client_id' => $other->id]);
        Prefix::factory()->create(['client_id' => $activeClient->id, 'ip_version' => 4]);
        Prefix::factory()->create(['client_id' => $other->id, 'ip_version' => 6]);
        $credential = $this->credential(DocumentationApiScope::all());

        $activeResponse = $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients?active_only=true')
            ->assertOk();
        $this->assertNotEmpty($activeResponse->json('data'));
        $this->assertTrue(collect($activeResponse->json('data'))
            ->every(fn (array $client) => $client['active'] === true));
        $this->withToken($credential['token'])
            ->getJson("/api/v1/documentation/autonomous-systems?client_id={$activeClient->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/prefixes?ip_version=6')
            ->assertOk()
            ->assertJsonPath('data.0.ip_version', 6)
            ->assertJsonCount(1, 'data');
    }

    public function test_request_id_is_server_generated_and_present_on_success(): void
    {
        $credential = $this->credential([DocumentationApiScope::CLIENTS_READ]);
        $response = $this->withHeader(
            'X-Request-ID',
            'client-supplied-id'
        )->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk()
            ->assertHeader('X-Request-ID');

        $this->assertNotSame(
            'client-supplied-id',
            $response->headers->get('X-Request-ID')
        );
    }

    public function test_rate_limit_error_preserves_retry_headers(): void
    {
        config(['documentation.rate_limit' => 1]);
        $server = ['REMOTE_ADDR' => '203.0.113.42'];

        $this->withServerVariables($server)
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();
        $response = $this->withServerVariables($server)
            ->getJson('/api/v1/documentation/clients')
            ->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'rate_limit_exceeded');
        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $response->json('error.request_id')
        );
    }

    public function test_internal_error_is_generic_and_does_not_leak_details(): void
    {
        $credential = $this->credential([DocumentationApiScope::CLIENTS_READ]);
        $this->mock(
            DocumentationResourceController::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('clients')->andThrow(
                    new \RuntimeException(
                        'SQLSTATE secret at /opt/ircenter/app/private.php'
                    )
                );
            }
        );

        $response = $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertInternalServerError()
            ->assertJsonPath('error.code', 'internal_error');

        $content = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('/opt/ircenter', $content);
        $this->assertStringNotContainsString('secret', $content);
    }

    public function test_pii_shapes_and_forbidden_keys_match_contract(): void
    {
        Client::factory()->create(['email' => 'client@example.test']);
        User::factory()->create(['email' => 'user@example.test']);
        $basic = $this->credential([
            DocumentationApiScope::CLIENTS_READ,
            DocumentationApiScope::USERS_READ,
        ]);
        $pii = $this->credential(DocumentationApiScope::all());

        $basicClient = $this->withToken($basic['token'])
            ->getJson('/api/v1/documentation/clients')->assertOk();
        $basicClient->assertJsonMissingPath('data.0.email');
        $withPii = $this->withToken($pii['token'])
            ->getJson('/api/v1/documentation/clients')->assertOk();
        $withPii->assertJsonPath('data.0.email', 'client@example.test');
        $basicUser = $this->withToken($basic['token'])
            ->getJson('/api/v1/documentation/users')->assertOk();
        $basicUser->assertJsonMissingPath('data.0.email');

        foreach ([$basicClient->json(), $withPii->json(), $basicUser->json()] as $payload) {
            $this->assertNoForbiddenKeys($payload);
        }

        $this->assertNoForbiddenKeys($this->openApi);
    }

    /** @return array{client: ApiClient, token: string} */
    private function credential(array $scopes): array
    {
        return app(ApiCredentialService::class)->create(
            'Contrato '.uniqid(),
            null,
            $scopes
        );
    }

    /** @param array<string, mixed> $node */
    private function walkRefs(array $node): void
    {
        foreach ($node as $key => $value) {
            if ($key === '$ref') {
                $this->assertIsString($value);
                $this->resolvePointer($value);
            } elseif (is_array($value)) {
                $this->walkRefs($value);
            }
        }
    }

    /** @param array<string, mixed> $reference */
    private function resolveRef(array $reference): array
    {
        return $this->resolvePointer($reference['$ref']);
    }

    /** @return array<string, mixed> */
    private function resolvePointer(string $pointer): array
    {
        $this->assertStringStartsWith('#/', $pointer);
        $value = $this->openApi;

        foreach (explode('/', substr($pointer, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            $this->assertArrayHasKey($segment, $value, "Broken ref: {$pointer}");
            $value = $value[$segment];
        }

        $this->assertIsArray($value);

        return $value;
    }

    private function assertNoForbiddenKeys(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $forbidden = [
            'password', 'password_hash', 'remember_token', 'token',
            'token_hash', 'app_key', 'client_secret',
        ];

        foreach ($value as $key => $nested) {
            if (is_string($key)) {
                $this->assertNotContains(strtolower($key), $forbidden);
            }
            $this->assertNoForbiddenKeys($nested);
        }
    }
}
