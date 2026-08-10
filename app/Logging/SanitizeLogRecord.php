<?php

namespace App\Logging;

use Monolog\LogRecord;

final class SanitizeLogRecord
{
    public function __construct(
        private readonly SensitiveDataRedactor $redactor = new SensitiveDataRedactor(),
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactor->text($record->message),
            context: $this->redactor->context($record->context),
            extra: $this->redactor->context($record->extra),
        );
    }
}
