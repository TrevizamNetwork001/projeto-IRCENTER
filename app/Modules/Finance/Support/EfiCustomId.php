<?php

namespace App\Modules\Finance\Support;

use InvalidArgumentException;

final class EfiCustomId
{
    public static function fromCorrelationId(
        string $correlationId,
    ): string {
        $correlationId = trim($correlationId);

        if (
            $correlationId === ''
            || strlen($correlationId) > 180
        ) {
            throw new InvalidArgumentException(
                'Correlação de cobrança Efí inválida.'
            );
        }

        $customId = str_replace(
            ':',
            '_',
            $correlationId
        );

        if (
            preg_match(
                '/^[A-Za-z0-9_-]+$/D',
                $customId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Correlação de cobrança Efí contém '
                .'caractere não suportado.'
            );
        }

        return $customId;
    }
}
