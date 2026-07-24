<?php

namespace App\Console\Commands;

use App\Models\AutomationRun;
use App\Services\NotificationAutomationService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Throwable;

class SyncOperationalNotifications extends Command implements Isolatable
{
    protected $signature = 'ircenter:sync-notifications
        {--trigger=manual : Origem da execução: manual ou scheduled}';

    protected $description =
        'Sincroniza notificações operacionais de todos os usuários ativos';

    public function handle(
        NotificationAutomationService $automationService,
        NotificationService $notificationService
    ): int {
        $trigger = (string) $this->option('trigger');

        if (! in_array($trigger, ['manual', 'scheduled'], true)) {
            $this->error(
                'O parâmetro --trigger deve ser manual ou scheduled.'
            );

            return self::INVALID;
        }

        $this->components->info(
            'Sincronizando notificações operacionais...'
        );

        try {
            $result = $automationService->run(
                $notificationService,
                $trigger
            );
        } catch (Throwable $exception) {
            $this->components->error(
                'A automação falhou: '.$exception->getMessage()
            );

            return self::FAILURE;
        }

        $run = $result['run'];

        $this->table(
            ['Campo', 'Resultado'],
            [
                ['Execução', '#'.$run->id],
                ['Status', $run->status],
                ['Origem', $run->trigger],
                ['Usuários processados', $result['processed_users']],
                [
                    'Notificações ativas',
                    $result['active_notifications'],
                ],
                ['Usuários com falha', $result['failed_users']],
                ['Duração', $run->duration_ms.' ms'],
            ]
        );

        return $run->status === AutomationRun::STATUS_COMPLETED
            ? self::SUCCESS
            : self::FAILURE;
    }

    public function isolatableId(): string
    {
        return 'sync-operational-notifications';
    }

    public function isolationLockExpiresAt(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }
}
