<?php

namespace App\Support;

use InvalidArgumentException;

class ExternalEndpointGuard
{
    /**
     * @return array{host: string, ip: string}
     */
    public function validate(string $endpoint): array
    {
        $parts = parse_url($endpoint);

        if (! is_array($parts)) {
            throw new InvalidArgumentException(
                'O endpoint informado é inválido.'
            );
        }

        if (($parts['scheme'] ?? null) !== 'https') {
            throw new InvalidArgumentException(
                'Somente endpoints HTTPS são permitidos.'
            );
        }

        if (
            isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException(
                'O endpoint não pode conter credenciais ou fragmentos.'
            );
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));

        if ($host === '') {
            throw new InvalidArgumentException(
                'O endpoint deve possuir um hostname válido.'
            );
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : 443;

        if ($port !== 443) {
            throw new InvalidArgumentException(
                'Nesta fase somente a porta HTTPS padrão 443 é permitida.'
            );
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            throw new InvalidArgumentException(
                'Hosts locais ou internos não são permitidos.'
            );
        }

        $ips = $this->resolve($host);

        if ($ips === []) {
            throw new InvalidArgumentException(
                'Não foi possível resolver o hostname do endpoint.'
            );
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new InvalidArgumentException(
                    'O hostname resolve para uma rede privada ou reservada.'
                );
            }
        }

        return [
            'host' => $host,
            'ip' => $ips[0],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = dns_get_record(
            $host,
            DNS_A | DNS_AAAA
        );

        if (! is_array($records)) {
            return [];
        }

        $ips = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
                | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
