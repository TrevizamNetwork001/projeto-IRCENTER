<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Support\DocumentationApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApiClientCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_displays_token_once_without_hash(): void
    {
        $exitCode = Artisan::call('api-client:create', [
            'name' => 'Consumidor de teste',
        ]);
        $output = Artisan::output();
        $client = ApiClient::query()->sole();

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/irc_api_[a-f0-9]{64}/',
            $output
        );
        preg_match('/irc_api_[a-f0-9]{64}/', $output, $matches);
        $token = $matches[0];

        $this->assertSame(1, substr_count($output, $token));
        $this->assertStringContainsString(
            'não poderá ser exibida novamente',
            $output
        );
        $this->assertStringNotContainsString($client->token_hash, $output);
        $this->assertSame(hash('sha256', $token), $client->token_hash);

        $audit = AuditLog::query()
            ->where('action', 'api_client.created')
            ->sole();
        $serializedAudit = json_encode(
            [$audit->old_values, $audit->new_values],
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString($token, $serializedAudit);
        $this->assertStringNotContainsString(
            $client->token_hash,
            $serializedAudit
        );
    }

    public function test_list_never_displays_token_hash(): void
    {
        Artisan::call('api-client:create', ['name' => 'Para listagem']);
        $client = ApiClient::query()->sole();

        $exitCode = Artisan::call('api-client:list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString($client->identifier, $output);
        $this->assertStringContainsString($client->token_prefix, $output);
        $this->assertStringNotContainsString($client->token_hash, $output);
    }

    public function test_create_rejects_unknown_scope(): void
    {
        $exitCode = Artisan::call('api-client:create', [
            'name' => 'Scope inválido',
            '--scope' => ['documentation.unknown.read'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('api_clients', 0);
    }

    public function test_scopes_are_persisted_cast_and_listed(): void
    {
        $exitCode = Artisan::call('api-client:create', [
            'name' => 'Com scopes',
            '--scope' => [
                DocumentationApiScope::NETWORK_READ,
                DocumentationApiScope::CLIENTS_READ,
            ],
        ]);
        $client = ApiClient::query()->sole();

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            DocumentationApiScope::CLIENTS_READ,
            DocumentationApiScope::NETWORK_READ,
        ], $client->scopes);

        Artisan::call('api-client:list');
        $output = Artisan::output();

        $this->assertStringContainsString(
            DocumentationApiScope::CLIENTS_READ,
            $output
        );
        $this->assertStringContainsString(
            DocumentationApiScope::NETWORK_READ,
            $output
        );
        $this->assertStringNotContainsString($client->token_hash, $output);
    }

    public function test_set_scopes_rejects_unknown_scope(): void
    {
        Artisan::call('api-client:create', ['name' => 'Não alterar']);
        $client = ApiClient::query()->sole();

        $exitCode = Artisan::call('api-client:set-scopes', [
            'identifier' => $client->identifier,
            '--scope' => ['documentation.unknown.read'],
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame([], $client->fresh()->scopes);
    }

    public function test_scope_change_is_audited_without_hash(): void
    {
        Artisan::call('api-client:create', ['name' => 'Auditável']);
        $client = ApiClient::query()->sole();

        $exitCode = Artisan::call('api-client:set-scopes', [
            'identifier' => $client->identifier,
            '--scope' => [DocumentationApiScope::CLIENTS_READ],
        ]);

        $this->assertSame(0, $exitCode);
        $audit = AuditLog::query()
            ->where('action', 'api_client.scopes_updated')
            ->sole();
        $serialized = json_encode(
            [$audit->old_values, $audit->new_values],
            JSON_THROW_ON_ERROR
        );

        $this->assertSame(
            [DocumentationApiScope::CLIENTS_READ],
            $client->fresh()->scopes
        );
        $this->assertStringNotContainsString(
            $client->token_hash,
            $serialized
        );
        $this->assertSame([], $audit->old_values['scopes']);
        $this->assertSame(
            [DocumentationApiScope::CLIENTS_READ],
            $audit->new_values['scopes']
        );
    }

    public function test_revoke_and_reactivate_are_audited(): void
    {
        Artisan::call('api-client:create', ['name' => 'Revogável']);
        $client = ApiClient::query()->sole();

        $this->assertSame(0, Artisan::call('api-client:revoke', [
            'identifier' => $client->identifier,
        ]));

        $client->refresh();
        $this->assertFalse($client->is_active);
        $this->assertNotNull($client->revoked_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'api_client.revoked',
            'resource_id' => $client->id,
        ]);

        $this->assertSame(0, Artisan::call('api-client:reactivate', [
            'identifier' => $client->identifier,
        ]));

        $client->refresh();
        $this->assertTrue($client->is_active);
        $this->assertNull($client->revoked_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'api_client.reactivated',
            'resource_id' => $client->id,
        ]);
    }
}
