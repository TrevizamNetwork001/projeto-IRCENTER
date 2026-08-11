<?php

namespace App\Support;

final class CsvCellSanitizer
{
    public function sanitize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    /** @param array<int, mixed> $row */
    public function sanitizeRow(array $row): array
    {
        return array_map($this->sanitize(...), $row);
    }
}
