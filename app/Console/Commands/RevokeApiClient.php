<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Services\ApiCredentialService;
use Illuminate\Console\Command;

class RevokeApiClient extends Command
{
    protected $signature = 'api-client:revoke
        {identifier : Identificador público da credencial}';

    protected $description =
        'Revoga uma credencial individual da API de documentação';

    public function handle(ApiCredentialService $credentialService): int
    {
        $client = ApiClient::query()
            ->where('identifier', (string) $this->argument('identifier'))
            ->first();

        if ($client === null) {
            $this->components->error('Credencial não encontrada.');

            return self::FAILURE;
        }

        $credentialService->revoke($client);

        $this->components->info(
            "Credencial {$client->identifier} revogada."
        );

        return self::SUCCESS;
    }
}
