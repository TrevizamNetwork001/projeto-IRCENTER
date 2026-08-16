<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Services\ApiCredentialService;
use Illuminate\Console\Command;

class ReactivateApiClient extends Command
{
    protected $signature = 'api-client:reactivate
        {identifier : Identificador público da credencial}';

    protected $description =
        'Reativa uma credencial individual da API de documentação';

    public function handle(ApiCredentialService $credentialService): int
    {
        $client = ApiClient::query()
            ->where('identifier', (string) $this->argument('identifier'))
            ->first();

        if ($client === null) {
            $this->components->error('Credencial não encontrada.');

            return self::FAILURE;
        }

        $credentialService->reactivate($client);

        $this->components->info(
            "Credencial {$client->identifier} reativada."
        );

        return self::SUCCESS;
    }
}
