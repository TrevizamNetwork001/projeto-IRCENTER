<?php

namespace App\Modules\Finance\Support;

use InvalidArgumentException;

final class Decimal
{
    public static function money(mixed $value): string
    {
        return self::normalize(
            $value,
            scale: 2,
            mustBePositive: false,
        );
    }

    public static function quantity(mixed $value): string
    {
        return self::normalize(
            $value,
            scale: 4,
            mustBePositive: true,
        );
    }

    public static function multiplyQuantityByMoney(
        string $quantity,
        string $money,
    ): string {
        $quantity = self::quantity($quantity);
        $money = self::money($money);

        $raw = bcmul($quantity, $money, 6);

        /*
         * Valores deste domínio são não-negativos.
         * Arredondamento HALF-UP para centavos.
         */
        $adjusted = bcadd(
            $raw,
            '0.005',
            6
        );

        return bcadd($adjusted, '0', 2);
    }

    public static function addMoney(
        string $left,
        string $right,
    ): string {
        return bcadd(
            self::money($left),
            self::money($right),
            2
        );
    }

    public static function moneyToCents(
        string|int $value,
    ): int {
        $normalized = self::money($value);

        $digits = str_replace(
            '.',
            '',
            $normalized
        );

        $digits = ltrim($digits, '0');

        return (int) (
            $digits === ''
                ? '0'
                : $digits
        );
    }

    private static function normalize(
        mixed $value,
        int $scale,
        bool $mustBePositive,
    ): string {
        if (
            is_float($value)
            || (! is_string($value) && ! is_int($value))
        ) {
            throw new InvalidArgumentException(
                'Valor decimal deve ser informado como string ou inteiro.'
            );
        }

        $value = trim((string) $value);

        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(
                'Formato decimal inválido.'
            );
        }

        [$integer, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException(
                'Valor possui casas decimais demais.'
            );
        }

        $integer = ltrim($integer, '0');

        if ($integer === '') {
            $integer = '0';
        }

        $normalized = $integer.'.'.str_pad(
            $fraction,
            $scale,
            '0'
        );

        $comparison = bccomp(
            $normalized,
            '0',
            $scale
        );

        if ($mustBePositive && $comparison <= 0) {
            throw new InvalidArgumentException(
                'Valor deve ser maior que zero.'
            );
        }

        if (! $mustBePositive && $comparison < 0) {
            throw new InvalidArgumentException(
                'Valor não pode ser negativo.'
            );
        }

        return $normalized;
    }
}
