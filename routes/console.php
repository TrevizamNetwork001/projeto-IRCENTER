<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


$notificationSyncStartedAt = null;

Schedule::command(
    'ircenter:sync-notifications --trigger=scheduled --isolated=12'
)
    ->name('ircenter-sync-operational-notifications')
    ->everyFiveMinutes()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(10)
    ->onOneServer()
    ->before(function () use (&$notificationSyncStartedAt): void {
        $notificationSyncStartedAt = hrtime(true);
    })
    ->after(function () use (&$notificationSyncStartedAt): void {
        if ($notificationSyncStartedAt === null) {
            return;
        }

        $durationMs = (int) round(
            (hrtime(true) - $notificationSyncStartedAt) / 1_000_000
        );

        if ($durationMs >= 60_000) {
            Log::warning('Tarefa agendada excedeu o tempo esperado.', [
                'operation' => 'scheduler.task',
                'module' => 'operations',
                'task' => 'ircenter-sync-operational-notifications',
                'status' => 'slow',
                'duration_ms' => $durationMs,
            ]);
        }
    })
    ->onFailure(function (): void {
        Log::error('Tarefa agendada falhou.', [
            'operation' => 'scheduler.task',
            'module' => 'operations',
            'task' => 'ircenter-sync-operational-notifications',
            'status' => 'failed',
        ]);
    });
