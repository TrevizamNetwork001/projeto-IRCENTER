<?php

namespace App\Http\Controllers;

use App\Models\AutomationRun;
use App\Models\ExternalIntegrationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

class SystemDiagnosticController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(
            auth()->user()?->isAdministrator() === true,
            403
        );

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
        ]);
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
