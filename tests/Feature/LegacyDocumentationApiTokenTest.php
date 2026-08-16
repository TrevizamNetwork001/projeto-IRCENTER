<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Services\ApiCredentialService;
use App\Support\DocumentationApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyDocumentationApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_TOKEN = 'legacy-token-for-compatibility-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'documentation.legacy_token_enabled' => false,
            'documentation.api_token_hash' => hash(
                'sha256',
                self::LEGACY_TOKEN
            ),
        ]);
    }

    public function test_individual_client_works_with_legacy_disabled(): void
    {
        $credential = $this->credential();

        $this->withToken($credential['token'])
            ->getJson('/api/v1/documentation/clients')
            ->assertOk();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'api_client.legacy_token_used',
        ]);
    }

    public function test_legacy_token_fails_when_disabled_or_flag_is_absent(): void
    {
        $this->withToken(self::LEGACY_TOKEN)
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');

        $documentation = config('documentation');
        unset($documentation['legacy_token_enabled']);
        config(['documentation' => $documentation]);

        $this->withToken(self::LEGACY_TOKEN)
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_disabled_flag_ignores_configured_legacy_hash(): void
    {
        config([
            'documentation.legacy_token_enabled' => false,
            'documentation.api_token_hash' => new class
            {
                public function __toString(): string
                {
                    throw new \RuntimeException(
                        'Legacy hash must not be consulted.'
                    );
                }
            },
        ]);

        $this->withToken(self::LEGACY_TOKEN)
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_enabled_legacy_fails_closed_with_missing_or_invalid_hash(): void
    {
        config([
            'documentation.legacy_token_enabled' => true,
            'documentation.api_token_hash' => null,
        ]);
        $this->withToken(self::LEGACY_TOKEN)
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();

        config(['documentation.api_token_hash' => 'not-a-sha256-hash']);
        $this->withToken(self::LEGACY_TOKEN)
            ->getJson('/api/v1/documentation/clients')
            ->assertUnauthorized();
    }

    public function test_enabled_legacy_authenticates_and_records_sanitized_usage(): void
    {
        config(['documentation.legacy_token_enabled' => true]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.60',
            'HTTP_USER_AGENT' => "Consumer\nExample",
        ])->withToken(self::LEGACY_TOKEN)
            ->getJson('/api/v1/documentation/clients')
            ->assertOk()
            ->assertHeader('X-Request-ID');

        $audit = AuditLog::query()
            ->where('action', 'api_client.legacy_token_used')
            ->sole();
        $serialized = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);

        $this->assertSame(
            $response->headers->get('X-Request-ID'),
            $audit->new_values['request_id']
        );
        $this->assertSame('GET', $audit->new_values['method']);
        $this->assertSame(
            '/api/v1/documentation/clients',
            $audit->new_values['route']
        );
        $this->assertSame('203.0.113.60', $audit->ip_address);
        $this->assertStringNotContainsString("\n", $audit->user_agent);
        $this->assertStringNotContainsString(self::LEGACY_TOKEN, $serialized);
        $this->assertStringNotContainsString(
            hash('sha256', self::LEGACY_TOKEN),
            $serialized
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'authorization',
            $serialized
        );
        $this->assertDatabaseCount('audit_logs', 1);
    }

    /** @return array{client: ApiClient, token: string} */
    private function credential(): array
    {
        return app(ApiCredentialService::class)->create(
            'ApiClient sem legado',
            null,
            [DocumentationApiScope::CLIENTS_READ]
        );
    }
}
