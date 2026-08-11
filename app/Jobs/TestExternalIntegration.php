<?php

namespace App\Jobs;

use App\Models\ExternalIntegration;
use App\Models\ExternalIntegrationRun;
use App\Support\ExternalEndpointGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class TestExternalIntegration implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 45;

    public function __construct(
        public int $integrationId,
        public int $runId
    ) {
        $this->onQueue('default');
    }

    public function handle(
        ExternalEndpointGuard $guard
    ): void {
        $integration = ExternalIntegration::query()
            ->findOrFail($this->integrationId);

        $run = ExternalIntegrationRun::query()
            ->findOrFail($this->runId);

        $run->update([
            'status' => ExternalIntegration::TEST_RUNNING,
            'started_at' => now(),
        ]);

        $startedAt = hrtime(true);

        try {
            $target = $guard->validate($integration->endpoint);

            $request = Http::acceptJson()
                ->timeout($integration->timeout_seconds)
                ->connectTimeout(
                    min(5, $integration->timeout_seconds)
                )
                ->retry(0, 0, throw: false)
                ->withOptions($guard->connectionOptions($target));

            if (
                $integration->authentication_type
                === ExternalIntegration::AUTH_BEARER
            ) {
                $request = $request->withToken(
                    (string) $integration->secret
                );
            }

            if (
                $integration->authentication_type
                === ExternalIntegration::AUTH_BASIC
            ) {
                $request = $request->withBasicAuth(
                    (string) $integration->username,
                    (string) $integration->secret
                );
            }

            $response = $request->head(
                $integration->endpoint
            );

            if (in_array($response->status(), [405, 501], true)) {
                $response = $request->get(
                    $integration->endpoint
                );
            }

            $durationMs = (int) round(
                (hrtime(true) - $startedAt) / 1_000_000
            );

            $success = $response->successful();

            $run->update([
                'status' => $success
                    ? ExternalIntegration::TEST_SUCCESS
                    : ExternalIntegration::TEST_FAILED,
                'http_status' => $response->status(),
                'duration_ms' => $durationMs,
                'resolved_ip' => $target['ip'],
                'response_content_type' => mb_substr(
                    (string) $response->header('Content-Type'),
                    0,
                    150
                ) ?: null,
                'error_message' => $success
                    ? null
                    : 'O endpoint respondeu com HTTP '
                        .$response->status().'.',
                'finished_at' => now(),
            ]);

            $integration->update([
                'last_tested_at' => now(),
                'last_test_status' => $success
                    ? ExternalIntegration::TEST_SUCCESS
                    : ExternalIntegration::TEST_FAILED,
                'last_http_status' => $response->status(),
                'last_error' => $success
                    ? null
                    : 'O endpoint respondeu com HTTP '
                        .$response->status().'.',
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->finishWithError(
                $integration,
                $run,
                ExternalIntegration::TEST_BLOCKED,
                $exception->getMessage(),
                $startedAt
            );
        } catch (Throwable $exception) {
            $this->finishWithError(
                $integration,
                $run,
                ExternalIntegration::TEST_FAILED,
                $this->safeError($exception),
                $startedAt
            );
        }
    }

    private function finishWithError(
        ExternalIntegration $integration,
        ExternalIntegrationRun $run,
        string $status,
        string $message,
        int $startedAt
    ): void {
        $durationMs = (int) round(
            (hrtime(true) - $startedAt) / 1_000_000
        );

        $message = mb_substr($message, 0, 2000);

        $run->update([
            'status' => $status,
            'duration_ms' => $durationMs,
            'error_message' => $message,
            'finished_at' => now(),
        ]);

        $integration->update([
            'last_tested_at' => now(),
            'last_test_status' => $status,
            'last_http_status' => null,
            'last_error' => $message,
        ]);
    }

    private function safeError(Throwable $exception): string
    {
        $message = $exception->getMessage();

        $message = preg_replace(
            '/(authorization|bearer|token|password|secret)[=: ]+\S+/i',
            '$1=[REDACTED]',
            $message
        );

        return $message !== ''
            ? $message
            : 'Falha não identificada ao testar o endpoint.';
    }
}
