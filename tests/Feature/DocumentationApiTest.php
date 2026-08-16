<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use App\Services\ApiCredentialService;
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
            'documentation.api_token_hash' => hash('sha256', self::TOKEN),
        ]);
    }

    public function test_api_rejects_missing_token(): void
    {
        $this->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Credencial da API inválida.',
            ]);
    }

    public function test_api_rejects_invalid_token(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();
    }

    public function test_legacy_documentation_token_still_works(): void
    {
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

        $this->withToken(self::TOKEN)
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

        $this->withToken(self::TOKEN)
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

        $response = $this->withToken(self::TOKEN)
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

        $this->withToken(self::TOKEN)
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
    private function createCredential(string $name): array
    {
        return app(ApiCredentialService::class)->create($name);
    }
}
