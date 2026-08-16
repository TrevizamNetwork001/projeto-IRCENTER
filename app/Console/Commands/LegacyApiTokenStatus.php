<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LegacyApiTokenStatus extends Command
{
    protected $signature = 'api-client:legacy-status';

    protected $description =
        'Exibe o estado do modo legado da API sem revelar segredos';

    public function handle(): int
    {
        if (! (bool) config('documentation.legacy_token_enabled', false)) {
            $this->components->info('Legacy API token: DISABLED');

            return self::SUCCESS;
        }

        $hash = trim((string) config('documentation.api_token_hash'));

        $this->components->warn('Legacy API token: ENABLED');
        $this->line('Status: DEPRECATED');
        $this->line(
            'Recommendation: migrate consumers to individual API clients.'
        );

        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            $this->components->error(
                'Configuration: INVALID (legacy authentication fails closed)'
            );

            return self::FAILURE;
        }

        $this->line('Configuration: VALID');

        return self::SUCCESS;
    }
}
