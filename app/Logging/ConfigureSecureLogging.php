<?php

namespace App\Logging;

use Illuminate\Log\Logger;

final class ConfigureSecureLogging
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new SanitizeLogRecord());
    }
}
