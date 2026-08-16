<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;

class ListApiClients extends Command
{
    protected $signature = 'api-client:list';

    protected $description =
        'Lista credenciais da API de documentação sem exibir segredos';

    public function handle(): int
    {
        $rows = ApiClient::query()
            ->orderBy('id')
            ->get()
            ->map(fn (ApiClient $client): array => [
                $client->id,
                $client->identifier,
                $client->name,
                $this->status($client),
                $client->expires_at?->toIso8601String() ?? '—',
                $client->last_used_at?->toIso8601String() ?? '—',
                $client->token_prefix,
            ])
            ->all();

        $this->table(
            [
                'ID',
                'Identifier',
                'Nome',
                'Status',
                'Expiração',
                'Último uso',
                'Token prefix',
            ],
            $rows
        );

        return self::SUCCESS;
    }

    private function status(ApiClient $client): string
    {
        if ($client->revoked_at !== null) {
            return 'revogada';
        }

        if (! $client->is_active) {
            return 'inativa';
        }

        if (
            $client->expires_at !== null
            && $client->expires_at->isPast()
        ) {
            return 'expirada';
        }

        return 'ativa';
    }
}
