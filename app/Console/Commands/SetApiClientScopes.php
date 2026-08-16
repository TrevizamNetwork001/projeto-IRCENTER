<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Services\ApiCredentialService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SetApiClientScopes extends Command
{
    protected $signature = 'api-client:set-scopes
        {identifier : Identificador público da credencial}
        {--scope=* : Novo scope; opção repetível e substitutiva}';

    protected $description =
        'Substitui explicitamente os scopes de uma credencial da API';

    public function handle(ApiCredentialService $credentialService): int
    {
        $client = ApiClient::query()
            ->where('identifier', (string) $this->argument('identifier'))
            ->first();

        if ($client === null) {
            $this->components->error('Credencial não encontrada.');

            return self::FAILURE;
        }

        try {
            $credentialService->updateScopes(
                $client,
                (array) $this->option('scope')
            );
        } catch (InvalidArgumentException) {
            $this->components->error(
                'Um ou mais scopes informados são inválidos.'
            );

            return self::FAILURE;
        }

        $this->components->info(
            "Scopes da credencial {$client->identifier} atualizados."
        );

        return self::SUCCESS;
    }
}
