<?php

namespace App\Console\Commands;

use App\Services\ApiCredentialService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class CreateApiClient extends Command
{
    protected $signature = 'api-client:create
        {name : Nome amigável da integração}
        {--expires-at= : Data/hora de expiração}
        {--scope=* : Scope permitido; opção repetível}';

    protected $description =
        'Cria uma credencial individual para a API de documentação';

    public function handle(ApiCredentialService $credentialService): int
    {
        try {
            $expiresAt = $this->option('expires-at');
            $result = $credentialService->create(
                (string) $this->argument('name'),
                is_string($expiresAt) && trim($expiresAt) !== ''
                    ? CarbonImmutable::parse($expiresAt)
                    : null,
                (array) $this->option('scope')
            );
        } catch (Throwable) {
            $this->components->error(
                'Não foi possível criar a credencial.'
            );

            return self::FAILURE;
        }

        $client = $result['client'];

        $this->components->info('Credencial criada com sucesso.');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $client->id],
                ['Identifier', $client->identifier],
                ['Nome', $client->name],
                ['Token prefix', $client->token_prefix],
                ['Scopes', implode(', ', $client->scopes) ?: 'Nenhum'],
                [
                    'Expiração',
                    $client->expires_at?->toIso8601String() ?? 'Sem expiração',
                ],
            ]
        );

        $this->components->warn(
            'Guarde esta credencial agora. Ela não poderá ser exibida novamente.'
        );
        $this->line($result['token']);

        return self::SUCCESS;
    }
}
