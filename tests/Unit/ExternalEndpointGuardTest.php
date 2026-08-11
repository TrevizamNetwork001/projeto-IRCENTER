<?php

namespace Tests\Unit;

use App\Support\ExternalEndpointGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExternalEndpointGuardTest extends TestCase
{
    public function test_public_resolution_is_approved_deterministically(): void
    {
        $guard = $this->guardWith(['93.184.216.34']);

        $this->assertSame(
            ['host' => 'example.test', 'ip' => '93.184.216.34'],
            $guard->validate('https://example.test/status')
        );
    }

    public function test_connection_is_pinned_without_second_resolution_and_preserves_hostname(): void
    {
        $guard = new class extends ExternalEndpointGuard {
            public int $calls = 0;
            protected function resolve(string $host): array
            {
                $this->calls++;
                return $this->calls === 1
                    ? ['93.184.216.34']
                    : ['127.0.0.1'];
            }
        };

        $target = $guard->validate('https://service.example.test/status');
        $options = $guard->connectionOptions($target);

        $this->assertSame(1, $guard->calls);
        $this->assertSame('93.184.216.34', $target['ip']);
        $this->assertSame(
            ['service.example.test:443:93.184.216.34'],
            $options['curl'][CURLOPT_RESOLVE]
        );
        $this->assertFalse($options['allow_redirects']);
        $this->assertTrue($options['verify']);
    }

    #[DataProvider('blockedAddresses')]
    public function test_non_public_addresses_are_blocked(string $ip): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->guardWith([$ip])->validate('https://example.test/status');
    }

    public static function blockedAddresses(): array
    {
        return [
            ['127.0.0.1'], ['10.0.0.1'], ['172.16.0.1'], ['192.168.1.1'],
            ['169.254.1.1'], ['::1'], ['fc00::1'], ['fe80::1'], ['::'],
            ['::ffff:127.0.0.1'], ['::ffff:10.0.0.1'],
        ];
    }

    private function guardWith(array $ips): ExternalEndpointGuard
    {
        return new class($ips) extends ExternalEndpointGuard {
            public function __construct(private array $ips) {}
            protected function resolve(string $host): array { return $this->ips; }
        };
    }
}
