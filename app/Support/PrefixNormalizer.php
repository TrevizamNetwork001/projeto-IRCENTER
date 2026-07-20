<?php

namespace App\Support;

final class PrefixNormalizer
{
    /**
     * @return array{prefix: string, version: int}|null
     */
    public static function normalize(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || substr_count($value, '/') !== 1) {
            return null;
        }

        [$address, $length] = explode('/', $value, 2);

        if ($address === '' || ! ctype_digit($length)) {
            return null;
        }

        $binary = @inet_pton($address);

        if ($binary === false) {
            return null;
        }

        $version = strlen($binary) === 4 ? 4 : 6;
        $maximumLength = $version === 4 ? 32 : 128;
        $prefixLength = (int) $length;

        if ($prefixLength < 0 || $prefixLength > $maximumLength) {
            return null;
        }

        $networkBinary = self::applyMask($binary, $prefixLength);
        $networkAddress = inet_ntop($networkBinary);

        if ($networkAddress === false) {
            return null;
        }

        return [
            'prefix' => strtolower($networkAddress).'/'.$prefixLength,
            'version' => $version,
        ];
    }

    private static function applyMask(string $binary, int $prefixLength): string
    {
        $bytes = array_values(unpack('C*', $binary));
        $remainingBits = $prefixLength;

        foreach ($bytes as $index => $byte) {
            if ($remainingBits >= 8) {
                $remainingBits -= 8;

                continue;
            }

            if ($remainingBits <= 0) {
                $bytes[$index] = 0;

                continue;
            }

            $mask = (0xff << (8 - $remainingBits)) & 0xff;
            $bytes[$index] = $byte & $mask;
            $remainingBits = 0;
        }

        return pack('C*', ...$bytes);
    }
}
