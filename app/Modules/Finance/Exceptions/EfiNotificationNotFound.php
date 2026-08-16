<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

final class EfiNotificationNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Notificação não encontrada no provedor.');
    }
}
