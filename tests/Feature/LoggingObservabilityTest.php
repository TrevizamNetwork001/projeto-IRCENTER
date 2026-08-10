<?php

namespace Tests\Feature;

use App\Logging\ConfigureSecureLogging;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingObservabilityTest extends TestCase
{
    public function test_testing_channel_is_ephemeral_and_separate(): void
    {
        $this->assertSame('testing', config('logging.default'));
        $this->assertSame(
            'php://stderr',
            config('logging.channels.testing.handler_with.stream')
        );
        $this->assertNotContains(
            storage_path('logs/laravel.log'),
            config('logging.channels.testing.handler_with')
        );
    }

    public function test_production_rotation_policy_is_explicit(): void
    {
        $this->assertSame('daily', config('logging.channels.daily.driver'));
        $this->assertSame(30, config('logging.channels.daily.days'));
        $this->assertContains(
            ConfigureSecureLogging::class,
            config('logging.channels.daily.tap')
        );
        $this->assertContains(
            ConfigureSecureLogging::class,
            config('logging.channels.stderr.tap')
        );
    }

    public function test_configured_channel_removes_sensitive_markers(): void
    {
        $path = storage_path('logs/h9-secure-test.log');
        @unlink($path);

        config()->set('logging.channels.h9_secure_test', [
            'driver' => 'single',
            'path' => $path,
            'level' => 'debug',
            'replace_placeholders' => true,
            'tap' => [ConfigureSecureLogging::class],
        ]);

        Log::channel('h9_secure_test')->warning(
            'authorization=Bearer H9_FILE_AUTH_MARKER_8ad1',
            [
                'password' => 'H9_FILE_PASSWORD_MARKER_4cc2',
                'provider_payload' => [
                    'access_token' => 'H9_FILE_TOKEN_MARKER_f192',
                    'pix_copy_paste' => 'H9_FILE_PIX_MARKER_6e31',
                ],
                'operation' => 'finance.charge.submit',
            ]
        );

        $contents = (string) file_get_contents($path);

        $this->assertStringNotContainsString('H9_FILE_AUTH_MARKER_8ad1', $contents);
        $this->assertStringNotContainsString('H9_FILE_PASSWORD_MARKER_4cc2', $contents);
        $this->assertStringNotContainsString('H9_FILE_TOKEN_MARKER_f192', $contents);
        $this->assertStringNotContainsString('H9_FILE_PIX_MARKER_6e31', $contents);
        $this->assertStringContainsString('[REDACTED]', $contents);
        $this->assertStringContainsString('finance.charge.submit', $contents);

        @unlink($path);
        Log::forgetChannel('h9_secure_test');
    }

    public function test_request_id_is_opaque_unique_and_available_to_logs(): void
    {
        Log::spy();

        $first = $this->get('/login');
        $second = $this->get('/login');

        $firstId = $first->headers->get('X-Request-ID');
        $secondId = $second->headers->get('X-Request-ID');

        $first->assertOk();
        $second->assertOk();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $firstId
        );
        $this->assertNotSame($firstId, $secondId);

        Log::shouldHaveReceived('withContext')
            ->twice()
            ->withArgs(
                fn (array $context) => isset($context['request_id'])
                    && is_string($context['request_id'])
            );
    }

    public function test_health_response_remains_stateless_with_request_id(): void
    {
        $response = $this->get('/up');

        $response->assertOk()
            ->assertHeader('X-Request-ID')
            ->assertCookieMissing(config('session.cookie'));
    }

    public function test_error_response_keeps_request_id(): void
    {
        $this->get('/h9-not-found-probe')
            ->assertNotFound()
            ->assertHeader('X-Request-ID');
    }
}
