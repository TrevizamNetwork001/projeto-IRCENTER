<?php

namespace Tests\Unit;

use App\Logging\SanitizeLogRecord;
use App\Logging\SensitiveDataRedactor;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SensitiveDataRedactorTest extends TestCase
{
    private const PASSWORD = 'H9_PASSWORD_MARKER_7c91';
    private const TOKEN = 'H9_TOKEN_MARKER_91af';
    private const AUTHORIZATION = 'H9_AUTH_MARKER_c42e';
    private const PIX = 'H9_PIX_MARKER_3bd0';

    public function test_nested_sensitive_context_is_redacted(): void
    {
        $sanitized = (new SensitiveDataRedactor())->context([
            'password' => self::PASSWORD,
            'headers' => [
                'Authorization' => 'Bearer '.self::AUTHORIZATION,
                'Cookie' => self::TOKEN,
            ],
            'provider_payload' => [
                'access_token' => self::TOKEN,
                'pix_copy_paste' => self::PIX,
                'status' => 'failed',
            ],
        ]);

        $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString(self::PASSWORD, $encoded);
        $this->assertStringNotContainsString(self::TOKEN, $encoded);
        $this->assertStringNotContainsString(self::AUTHORIZATION, $encoded);
        $this->assertStringNotContainsString(self::PIX, $encoded);
        $this->assertSame('failed', $sanitized['provider_payload']['status']);
    }

    public function test_message_credentials_and_private_key_are_redacted(): void
    {
        $message = 'password='.self::PASSWORD
            .' Authorization: Bearer '.self::AUTHORIZATION
            .' token='.self::TOKEN
            .' -----BEGIN PRIVATE KEY-----'.self::TOKEN
            .'-----END PRIVATE KEY-----';

        $sanitized = (new SensitiveDataRedactor())->text($message);

        $this->assertStringNotContainsString(self::PASSWORD, $sanitized);
        $this->assertStringNotContainsString(self::TOKEN, $sanitized);
        $this->assertStringNotContainsString(self::AUTHORIZATION, $sanitized);
        $this->assertStringContainsString('[REDACTED]', $sanitized);
    }

    public function test_exception_remains_observable_without_leaking_secret(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('h9-test', [$handler]);
        $logger->pushProcessor(new SanitizeLogRecord());

        $logger->error('Falha operacional.', [
            'operation' => 'finance.charge.submit',
            'exception' => new RuntimeException(
                'client_secret='.self::TOKEN
            ),
        ]);

        $record = $handler->getRecords()[0];
        $encoded = json_encode($record->context, JSON_THROW_ON_ERROR);

        $this->assertSame(Level::Error, $record->level);
        $this->assertSame(
            RuntimeException::class,
            $record->context['exception']['type']
        );
        $this->assertStringNotContainsString(self::TOKEN, $encoded);
        $this->assertStringContainsString('[REDACTED]', $encoded);
    }
}
