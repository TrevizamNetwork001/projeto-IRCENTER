<?php

namespace Tests\Feature;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'documentation-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'documentation.api_token_hash' =>
                hash('sha256', self::TOKEN),
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
                'notes' =>
                    'Anotação interna que não pode sair.',
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
}
