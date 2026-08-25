<?php

namespace App\Services\Identity;

use InvalidArgumentException;

class TotpService
{
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function uri(string $secret, string $email, string $issuer = 'IRCENTER'): string
    {
        $label = rawurlencode($issuer.':'.$email);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            $secret,
            rawurlencode($issuer),
        );
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): ?int
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $step = intdiv($timestamp ?? time(), 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidateStep = $step + $offset;

            if (hash_equals($this->codeAt($secret, $candidateStep), $code)) {
                return $candidateStep;
            }
        }

        return null;
    }

    public function codeAt(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        $counter = pack('N2', intdiv($step, 0x100000000), $step & 0xffffffff);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($value) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split(strtoupper(rtrim($value, '='))) as $character) {
            $position = strpos($alphabet, $character);
            if ($position === false) {
                throw new InvalidArgumentException('Segredo TOTP inválido.');
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
