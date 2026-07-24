<?php

namespace App\Services;

use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationAutomationService
{
    /**
     * @return array{
     *     run: AutomationRun,
     *     processed_users: int,
     *     active_notifications: int,
     *     failed_users: int
     * }
     */
    public function run(
        NotificationService $notificationService,
        string $trigger = 'manual'
    ): array {
        $startedAt = now();
        $startedTimestamp = hrtime(true);

        $run = AutomationRun::create([
            'automation' => 'sync-operational-notifications',
            'trigger' => $trigger,
            'status' => AutomationRun::STATUS_RUNNING,
            'started_at' => $startedAt,
        ]);

        $processedUsers = 0;
        $activeNotifications = 0;
        $failedUsers = 0;
        $errors = [];

        try {
            User::query()
                ->where('active', true)
                ->orderBy('id')
                ->chunkById(
                    100,
                    function ($users) use (
                        $notificationService,
                        &$processedUsers,
                        &$activeNotifications,
                        &$failedUsers,
                        &$errors
                    ): void {
                        foreach ($users as $user) {
                            try {
                                $notifications =
                                    $notificationService->syncFor($user);

                                $processedUsers++;
                                $activeNotifications +=
                                    $notifications->count();
                            } catch (Throwable $exception) {
                                $processedUsers++;
                                $failedUsers++;

                                $errors[] = [
                                    'user_id' => $user->id,
                                    'error' => mb_substr(
                                        $exception->getMessage(),
                                        0,
                                        500
                                    ),
                                ];

                                Log::error(
                                    'Falha ao sincronizar notificações.',
                                    [
                                        'user_id' => $user->id,
                                        'exception' => $exception,
                                    ]
                                );
                            }
                        }
                    }
                );

            $durationMs = (int) round(
                (hrtime(true) - $startedTimestamp) / 1_000_000
            );

            $status = $failedUsers > 0
                ? AutomationRun::STATUS_FAILED
                : AutomationRun::STATUS_COMPLETED;

            $run->update([
                'status' => $status,
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'processed_items' => $processedUsers,
                'result_items' => $activeNotifications,
                'metadata' => [
                    'processed_users' => $processedUsers,
                    'active_notifications' => $activeNotifications,
                    'failed_users' => $failedUsers,
                    'errors' => $errors,
                ],
                'error_message' => $failedUsers > 0
                    ? sprintf(
                        '%d usuário(s) apresentaram falha.',
                        $failedUsers
                    )
                    : null,
            ]);

            return [
                'run' => $run->fresh(),
                'processed_users' => $processedUsers,
                'active_notifications' => $activeNotifications,
                'failed_users' => $failedUsers,
            ];
        } catch (Throwable $exception) {
            $durationMs = (int) round(
                (hrtime(true) - $startedTimestamp) / 1_000_000
            );

            $run->update([
                'status' => AutomationRun::STATUS_FAILED,
                'finished_at' => now(),
                'duration_ms' => $durationMs,
                'processed_items' => $processedUsers,
                'result_items' => $activeNotifications,
                'metadata' => [
                    'processed_users' => $processedUsers,
                    'active_notifications' => $activeNotifications,
                    'failed_users' => $failedUsers,
                    'errors' => $errors,
                ],
                'error_message' => mb_substr(
                    $exception->getMessage(),
                    0,
                    2000
                ),
            ]);

            throw $exception;
        }
    }
}
