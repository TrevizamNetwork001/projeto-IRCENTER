<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class LegacyApiTokenUsage extends Command
{
    protected $signature = 'api-client:legacy-usage';

    protected $description =
        'Resume o uso auditado do token legado sem exibir segredos';

    public function handle(): int
    {
        $query = AuditLog::query()
            ->where('action', 'api_client.legacy_token_used');
        $lastUsedAt = (clone $query)->max('created_at');
        $last24Hours = (clone $query)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $last7Days = (clone $query)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $distinctIps = (clone $query)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('ip_address')
            ->distinct()
            ->count('ip_address');

        $this->table(['Métrica', 'Valor'], [
            ['Última utilização', $lastUsedAt ?? 'Nenhum uso auditado'],
            ['Utilizações nas últimas 24h', $last24Hours],
            ['Utilizações nos últimos 7 dias', $last7Days],
            ['IPs distintos nos últimos 7 dias', $distinctIps],
        ]);

        return self::SUCCESS;
    }
}
