<?php

namespace App\Support;

final class PrefixMath
{
    /**
     * @return array{
     *     prefix: string,
     *     address: string,
     *     binary: string,
     *     length: int,
     *     version: int,
     *     maximum_length: int
     * }|null
     */
    public static function parse(mixed $value): ?array
    {
        $normalized = PrefixNormalizer::normalize($value);

        if ($normalized === null) {
            return null;
        }

        [$address, $length] = explode('/', $normalized['prefix'], 2);

        $binary = inet_pton($address);

        if ($binary === false) {
            return null;
        }

        return [
            'prefix' => $normalized['prefix'],
            'address' => $address,
            'binary' => $binary,
            'length' => (int) $length,
            'version' => $normalized['version'],
            'maximum_length' => $normalized['version'] === 4 ? 32 : 128,
        ];
    }

    public static function contains(
        string $coveringPrefix,
        string $candidatePrefix
    ): bool {
        $covering = self::parse($coveringPrefix);
        $candidate = self::parse($candidatePrefix);

        if ($covering === null || $candidate === null) {
            return false;
        }

        if ($covering['version'] !== $candidate['version']) {
            return false;
        }

        if ($candidate['length'] < $covering['length']) {
            return false;
        }

        return self::matchesBits(
            $covering['binary'],
            $candidate['binary'],
            $covering['length']
        );
    }

    private static function matchesBits(
        string $left,
        string $right,
        int $length
    ): bool {
        $fullBytes = intdiv($length, 8);
        $remainingBits = $length % 8;

        if (
            $fullBytes > 0
            && substr($left, 0, $fullBytes) !== substr($right, 0, $fullBytes)
        ) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (
            ord($left[$fullBytes]) & $mask
        ) === (
            ord($right[$fullBytes]) & $mask
        );
    }
}
