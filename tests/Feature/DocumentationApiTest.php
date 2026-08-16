<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use App\Services\ApiCredentialService;
use App\Support\DocumentationApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentationApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'documentation-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'documentation.legacy_token_enabled' => false,
            'documentation.api_token_hash' => hash('sha256', self::TOKEN),
        ]);
    }

    public function test_api_rejects_missing_token(): void
    {
        $this->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'authentication_required')
            ->assertJsonPath(
                'message',
                'Credencial de acesso não informada.'
            );
    }

    public function test_api_rejects_invalid_token(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();
    }

    public function test_legacy_documentation_token_still_works(): void
    {
        config(['documentation.legacy_token_enabled' => true]);

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();
    }

    public function test_individual_credential_can_access_api(): void
    {
        $credential = $this->createCredential('Integração válida');

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();
    }

    public function test_valid_credential_without_scopes_is_forbidden(): void
    {
        $credential = $this->createCredential('Sem scopes', []);

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertForbidden()
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('error.code', 'insufficient_scope')
            ->assertJsonPath(
                'message',
                'Acesso não autorizado para este recurso.'
            );
    }

    public function test_network_scope_accesses_network_but_not_clients(): void
    {
        $client = Client::factory()->create();
        AutonomousSystem::factory()->create(['client_id' => $client->id]);
        Prefix::factory()->create(['client_id' => $client->id]);
        $credential = $this->createCredential(
            'Somente rede',
            [DocumentationApiScope::NETWORK_READ]
        );

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/autonomous-systems')
            ->assertOk();

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/prefixes')
            ->assertOk();

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertForbidden();
    }

    public function test_clients_scope_does_not_access_users(): void
    {
        $credential = $this->createCredential('Somente clientes');

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/users')
            ->assertForbidden();
    }

    public function test_users_scope_accesses_users(): void
    {
        User::factory()->create();
        $credential = $this->createCredential(
            'Somente usuários',
            [DocumentationApiScope::USERS_READ]
        );

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/users')
            ->assertOk();
    }

    public function test_pii_scope_alone_does_not_access_clients(): void
    {
        $credential = $this->createCredential(
            'Somente PII',
            [DocumentationApiScope::PII_READ]
        );

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertForbidden();
    }

    public function test_clients_scope_omits_pii_and_internal_fields(): void
    {
        Client::factory()->create([
            'document' => '12345678901',
            'email' => 'pii@example.test',
            'phone' => '11999999999',
            'postal_code' => '01001000',
            'street' => 'Rua de Teste',
            'address_number' => '123',
            'address_complement' => 'Sala 1',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'notes' => 'Nota interna',
            'contract_number' => 'CONTRATO-INTERNO',
        ]);
        $credential = $this->createCredential('Clientes sem PII');

        $response = $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();

        foreach ([
            'document',
            'email',
            'phone',
            'postal_code',
            'street',
            'address_number',
            'address_complement',
            'district',
            'city',
            'state',
            'country',
            'notes',
            'contract_number',
        ] as $field) {
            $response->assertJsonMissingPath("data.0.{$field}");
        }

        $this->assertNoForbiddenKeys($response->json());
    }

    public function test_clients_and_pii_scopes_return_only_allowed_pii(): void
    {
        $client = Client::factory()->create([
            'document' => '12345678901',
            'email' => 'allowed@example.test',
            'phone' => '11999999999',
            'postal_code' => '01001000',
            'street' => 'Rua Permitida',
            'address_number' => '123',
            'address_complement' => 'Sala 1',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'country' => 'BR',
            'notes' => 'Nunca expor',
            'contract_number' => 'CONTRATO-INTERNO',
        ]);
        $credential = $this->createCredential(
            'Clientes com PII',
            [
                DocumentationApiScope::CLIENTS_READ,
                DocumentationApiScope::PII_READ,
            ]
        );

        $response = $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk()
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonPath('data.0.document', '12345678901')
            ->assertJsonPath('data.0.email', 'allowed@example.test')
            ->assertJsonPath('data.0.phone', '11999999999')
            ->assertJsonPath('data.0.street', 'Rua Permitida')
            ->assertJsonMissingPath('data.0.notes')
            ->assertJsonMissingPath('data.0.contract_number');

        $this->assertNoForbiddenKeys($response->json());
    }

    public function test_network_scope_omits_noc_pii_without_pii_scope(): void
    {
        $client = Client::factory()->create();
        AutonomousSystem::factory()->create([
            'client_id' => $client->id,
            'noc_contact' => 'Pessoa de Teste',
            'noc_email' => 'noc@example.test',
            'noc_phone' => '11999999999',
        ]);
        $credential = $this->createCredential(
            'Rede sem PII',
            [DocumentationApiScope::NETWORK_READ]
        );

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/autonomous-systems')
            ->assertOk()
            ->assertJsonMissingPath('data.0.noc_contact')
            ->assertJsonMissingPath('data.0.noc_email')
            ->assertJsonMissingPath('data.0.noc_phone');
    }

    public function test_no_scope_combination_exposes_user_secrets(): void
    {
        User::factory()->create([
            'email' => 'user@example.test',
            'password' => 'fake-password-hash-for-testing',
            'remember_token' => 'fake-remember-token-for-testing',
        ]);
        $credential = $this->createCredential(
            'Usuários com PII',
            [
                DocumentationApiScope::USERS_READ,
                DocumentationApiScope::PII_READ,
            ]
        );

        $response = $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/users')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'user@example.test');

        $this->assertNoForbiddenKeys($response->json());
    }

    public function test_inactive_individual_credential_is_rejected(): void
    {
        $credential = $this->createCredential('Integração inativa');
        $credential['client']->forceFill(['is_active' => false])->save();

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();
    }

    public function test_revoked_individual_credential_is_rejected(): void
    {
        $credential = $this->createCredential('Integração revogada');
        app(ApiCredentialService::class)->revoke($credential['client']);

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();
    }

    public function test_expired_individual_credential_is_rejected(): void
    {
        $credential = $this->createCredential('Integração expirada');
        $credential['client']->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();
    }

    public function test_credential_without_expiration_can_access_api(): void
    {
        $credential = $this->createCredential('Sem expiração');

        $this->assertNull($credential['client']->expires_at);

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();
    }

    public function test_valid_use_updates_timestamp_and_ip(): void
    {
        $credential = $this->createCredential('Uso rastreável');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();

        $credential['client']->refresh();

        $this->assertNotNull($credential['client']->last_used_at);
        $this->assertSame(
            '203.0.113.20',
            $credential['client']->last_used_ip
        );
    }

    public function test_only_sha256_hash_of_generated_token_is_stored(): void
    {
        $credential = $this->createCredential('Persistência segura');
        $stored = DB::table('api_clients')
            ->where('id', $credential['client']->id)
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame(
            hash('sha256', $credential['token']),
            $stored->token_hash
        );
        $this->assertStringNotContainsString(
            $credential['token'],
            json_encode($stored, JSON_THROW_ON_ERROR)
        );
        $this->assertArrayNotHasKey(
            'token_hash',
            $credential['client']->toArray()
        );
    }

    public function test_revoking_one_credential_does_not_affect_another(): void
    {
        $credentialA = $this->createCredential('Integração A');
        $credentialB = $this->createCredential('Integração B');

        app(ApiCredentialService::class)->revoke($credentialA['client']);

        $this->withToken($credentialA['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();

        $this->withToken($credentialB['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();
    }

    public function test_invalid_attempts_are_rate_limited_by_ip(): void
    {
        config(['documentation.rate_limit' => 2]);

        $server = ['REMOTE_ADDR' => '203.0.113.10'];

        $this->withServerVariables($server)
            ->withToken('invalid-token')
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();

        $this->withServerVariables($server)
            ->withToken('invalid-token')
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();

        $this->withServerVariables($server)
            ->withToken('invalid-token')
            ->getJson('/api/v1/documentation/clients')
            ->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', '2')
            ->assertHeader('Retry-After');
    }

    public function test_api_lists_clients_without_notes(): void
    {
        $client = Client::factory()->create([
            'legal_name' => 'Cliente Documentação Ltda.',
            'notes' => 'Anotação interna que não pode sair.',
        ]);

        $credential = $this->createCredential('Listagem sem notas');

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $client->id
            )
            ->assertJsonMissing([
                'notes' => 'Anotação interna que não pode sair.',
            ]);
    }

    public function test_client_endpoint_includes_asns_and_prefixes(): void
    {
        $client = Client::factory()->create();

        $asn = AutonomousSystem::factory()->create([
            'client_id' => $client->id,
        ]);

        $prefix = Prefix::factory()->create([
            'client_id' => $client->id,
            'autonomous_system_id' => $asn->id,
        ]);

        $credential = $this->createCredential('Detalhe de cliente');

        $this->withToken($credential['token'])
            ->getJson(
                "/api/v1/documentation/clients/{$client->id}"
            )
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath(
                'data.autonomous_systems.0.id',
                $asn->id
            )
            ->assertJsonPath(
                'data.prefixes.0.id',
                $prefix->id
            );
    }

    public function test_users_endpoint_never_exposes_secrets(): void
    {
        $user = User::factory()->create();

        $credential = $this->createCredential(
            'Listagem de usuários',
            [DocumentationApiScope::USERS_READ]
        );

        $response = $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/users')
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id);

        $content = $response->getContent();

        $this->assertStringNotContainsString(
            'password',
            $content
        );

        $this->assertStringNotContainsString(
            'remember_token',
            $content
        );
    }

    public function test_pagination_is_limited_to_one_hundred(): void
    {
        Client::factory()->count(101)->create();

        $credential = $this->createCredential('Paginação');

        $this->withToken($credential['token'])
            ->getJson(
                '/api/v1/documentation/clients?per_page=500'
            )
            ->assertOk()
            ->assertJsonPath('per_page', 100)
            ->assertJsonCount(100, 'data');
    }

    /**
     * @return array{client: ApiClient, token: string}
     */
    private function createCredential(
        string $name,
        array $scopes = [DocumentationApiScope::CLIENTS_READ]
    ): array {
        return app(ApiCredentialService::class)->create(
            $name,
            null,
            $scopes
        );
    }

    private function assertNoForbiddenKeys(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $forbidden = [
            'password',
            'password_hash',
            'remember_token',
            'token',
            'token_hash',
            'secret',
            'client_secret',
            'api_key',
            'app_key',
        ];

        foreach ($value as $key => $nestedValue) {
            if (is_string($key)) {
                $this->assertNotContains(strtolower($key), $forbidden);
            }

            $this->assertNoForbiddenKeys($nestedValue);
        }
    }
}
