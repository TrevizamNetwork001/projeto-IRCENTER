<?php

namespace App\Http\Controllers;

use App\Models\AutomationRun;
use App\Models\ExternalIntegrationRun;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

class SystemDiagnosticController extends Controller
{
    public function __construct(
        private readonly BusinessClock $businessClock,
    ) {
    }

    public function __invoke(): View
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );

        $backupStatus = $this->localizeTimestamps(
            $this->parseEnvFile('/var/www/ops-status/backup-status.env'),
            ['last_backup_at']
        );

        $offsiteStatus = $this->localizeTimestamps(
            $this->parseEnvFile('/var/www/ops-status/offsite-status.env'),
            ['last_offsite_sync_at']
        );

        $backupHistory = array_map(
            fn (array $entry) => $this->localizeTimestamps($entry, ['at']),
            $this->parseHistory('/var/www/ops-status/backup-history.log')
        );

        $retentionConfig = $this->parseEnvFile('/var/www/ops-config/backup-config.env')
            ?? [
                'RETENTION_DAILY_DAYS' => $backupStatus['retention_daily_days'] ?? 14,
                'RETENTION_WEEKLY_DAYS' => $backupStatus['retention_weekly_days'] ?? 90,
                'RETENTION_MONTHLY_DAYS' => $backupStatus['retention_monthly_days'] ?? 730,
            ];

        return view('system-diagnostic.index', [
            'checks' => [
                $this->databaseCheck(),
                $this->redisCheck(),
                $this->queueCheck(),
            ],
            'failedJobs' => DB::table('failed_jobs')->count(),
            'pendingJobs' => $this->pendingJobs(),
            'latestAutomationRun' => AutomationRun::query()
                ->latest()
                ->first(),
            'latestIntegrationRun' => ExternalIntegrationRun::query()
                ->with('integration')
                ->latest()
                ->first(),
            'environment' => app()->environment(),
            'debugEnabled' => config('app.debug'),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'backupStatus' => $backupStatus,
            'offsiteStatus' => $offsiteStatus,
            'backupHistory' => $backupHistory,
            'retentionConfig' => $retentionConfig,
        ]);
    }

    public function updateBackupRetention(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );

        $data = $request->validate([
            'retention_daily_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'retention_weekly_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'retention_monthly_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        if (
            $data['retention_weekly_days'] < $data['retention_daily_days']
            || $data['retention_monthly_days'] < $data['retention_weekly_days']
        ) {
            return back()->withErrors([
                'retention_daily_days' => 'A retenção deve crescer de diário '
                    .'para semanal e de semanal para mensal.',
            ]);
        }

        $configPath = '/var/www/ops-config/backup-config.env';

        if (! is_dir(dirname($configPath)) || ! is_writable(dirname($configPath))) {
            return back()->withErrors([
                'retention_daily_days' => 'Diretório de configuração do '
                    .'backup indisponível para escrita nesta instância.',
            ]);
        }

        $contents = sprintf(
            "RETENTION_DAILY_DAYS=%d\nRETENTION_WEEKLY_DAYS=%d\nRETENTION_MONTHLY_DAYS=%d\n",
            $data['retention_daily_days'],
            $data['retention_weekly_days'],
            $data['retention_monthly_days'],
        );

        $tmpPath = $configPath.'.tmp-'.bin2hex(random_bytes(4));
        file_put_contents($tmpPath, $contents);
        chmod($tmpPath, 0664);
        rename($tmpPath, $configPath);

        return redirect()
            ->route('system-diagnostic.index')
            ->with('status', 'Política de retenção de backup atualizada. '
                .'Vale a partir da próxima execução agendada.');
    }

    /**
     * @param  array<string, string>|null  $values
     * @param  array<int, string>  $keys
     * @return array<string, string>|null
     */
    private function localizeTimestamps(?array $values, array $keys): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($keys as $key) {
            if (! isset($values[$key])) {
                continue;
            }

            try {
                $values[$key] = $this->businessClock
                    ->toBusinessTimezone($values[$key])
                    ->format('d/m/Y H:i:s');
            } catch (Throwable) {
                // Mantém o valor original se nao for uma data valida.
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>|null
     */
    private function parseEnvFile(string $path): ?array
    {
        if (! is_readable($path)) {
            return null;
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$key, $value] = array_pad(explode('=', $line, 2), 2, null);

            if ($key !== null && $value !== null) {
                $values[$key] = $value;
            }
        }

        return $values === [] ? null : $values;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseHistory(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $entries = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = [];

            foreach (explode(' ', $line) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);

                if ($key !== null && $value !== null) {
                    $entry[$key] = $value;
                }
            }

            if ($entry !== []) {
                $entries[] = $entry;
            }
        }

        return array_slice($entries, 0, 15);
    }

    private function pendingJobs(): ?int
    {
        try {
            return Queue::size();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseCheck(): array
    {
        $startedAt = hrtime(true);

        try {
            DB::select('select 1');

            return $this->checkResult(
                'PostgreSQL',
                true,
                $startedAt
            );
        } catch (Throwable) {
            return $this->checkResult(
                'PostgreSQL',
                false,
                $startedAt
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function redisCheck(): array
    {
        $startedAt = hrtime(true);

        try {
            Redis::connection()->ping();

            return $this->checkResult(
                'Redis',
                true,
                $startedAt
            );
        } catch (Throwable) {
            return $this->checkResult(
                'Redis',
                false,
                $startedAt
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queueCheck(): array
    {
        $startedAt = hrtime(true);

        try {
            $size = Queue::size();

            return [
                ...$this->checkResult(
                    'Fila',
                    true,
                    $startedAt
                ),
                'detail' => $size.' job(s) aguardando',
            ];
        } catch (Throwable) {
            return $this->checkResult(
                'Fila',
                false,
                $startedAt
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkResult(
        string $name,
        bool $healthy,
        int $startedAt
    ): array {
        return [
            'name' => $name,
            'healthy' => $healthy,
            'duration_ms' => (int) round(
                (hrtime(true) - $startedAt) / 1_000_000
            ),
            'detail' => $healthy
                ? 'Serviço disponível'
                : 'Serviço indisponível',
        ];
    }
}
