<?php

namespace App\Services;

use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;

final class RpslTemplateService
{
    public function generate(
        string $type,
        string $key,
        ?Client $client,
        ?AutonomousSystem $autonomousSystem,
        ?Prefix $prefix,
        ?string $maintainer,
        string $source,
        ?string $description = null
    ): string {
        $description ??= $client?->displayName() ?? 'Objeto gerenciado pelo IRCENTER';
        $maintainer = $maintainer ?: 'MAINT-LOCAL';

        return match ($type) {
            'mntner' => $this->mntner(
                $key,
                $description,
                $maintainer,
                $source
            ),

            'aut-num' => $this->autNum(
                $key,
                $autonomousSystem,
                $description,
                $maintainer,
                $source
            ),

            'as-set' => $this->asSet(
                $key,
                $autonomousSystem,
                $description,
                $maintainer,
                $source
            ),

            'route' => $this->route(
                'route',
                $prefix,
                $autonomousSystem,
                $description,
                $maintainer,
                $source
            ),

            'route6' => $this->route(
                'route6',
                $prefix,
                $autonomousSystem,
                $description,
                $maintainer,
                $source
            ),

            default => '',
        };
    }

    private function mntner(
        string $key,
        string $description,
        string $maintainer,
        string $source
    ): string {
        return implode("\n", [
            "mntner: {$key}",
            "descr: {$description}",
            "mnt-by: {$maintainer}",
            "source: {$source}",
        ]);
    }

    private function autNum(
        string $key,
        ?AutonomousSystem $autonomousSystem,
        string $description,
        string $maintainer,
        string $source
    ): string {
        $asName = $autonomousSystem?->name
            ? $this->normalizeName($autonomousSystem->name)
            : $this->normalizeName($key);

        return implode("\n", [
            "aut-num: {$key}",
            "as-name: {$asName}",
            "descr: {$description}",
            "mnt-by: {$maintainer}",
            "source: {$source}",
        ]);
    }

    private function asSet(
        string $key,
        ?AutonomousSystem $autonomousSystem,
        string $description,
        string $maintainer,
        string $source
    ): string {
        $lines = [
            "as-set: {$key}",
            "descr: {$description}",
        ];

        if ($autonomousSystem !== null) {
            $lines[] = "members: {$autonomousSystem->formattedAsn()}";
        }

        $lines[] = "mnt-by: {$maintainer}";
        $lines[] = "source: {$source}";

        return implode("\n", $lines);
    }

    private function route(
        string $attribute,
        ?Prefix $prefix,
        ?AutonomousSystem $autonomousSystem,
        string $description,
        string $maintainer,
        string $source
    ): string {
        return implode("\n", [
            "{$attribute}: ".($prefix?->prefix ?? ''),
            "origin: ".($autonomousSystem?->formattedAsn() ?? ''),
            "descr: {$description}",
            "mnt-by: {$maintainer}",
            "source: {$source}",
        ]);
    }

    private function normalizeName(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?: 'AS-LOCAL';

        return trim($value, '-');
    }
}
