<?php

namespace App\Providers;

use App\Models\ApiClient;
use App\Models\Notification;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Finance\Infrastructure\FakePaymentProvider;
use App\Modules\Fiscal\Contracts\NfseProvider;
use App\Modules\Fiscal\Infrastructure\DisabledNfseProvider;
use App\Modules\Fiscal\Infrastructure\FakeNfseProvider;
use App\Modules\Shared\Contracts\ClientDirectory;
use App\Modules\Shared\Infrastructure\CoreClientDirectory;
use App\Support\BusinessClock;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            BusinessClock::class,
            function () {
                $timezone = (string) config('business.timezone');

                if (! in_array(
                    $timezone,
                    config('business.allowed_timezones', []),
                    true,
                )) {
                    throw new \InvalidArgumentException(
                        'BUSINESS_TIMEZONE não é suportado.'
                    );
                }

                return new BusinessClock($timezone);
            },
        );

        $this->app->singleton(
            ClientDirectory::class,
            CoreClientDirectory::class,
        );

        $this->app->singleton(
            PaymentProvider::class,
            function () {
                return match (
                    config(
                        'finance_fiscal.finance.payment_provider',
                        'fake'
                    )
                ) {
                    'fake' => new FakePaymentProvider,

                    'efi' => $this->app->make(
                        EfiPaymentProvider::class
                    ),
                    default => throw new \LogicException(
                        'Payment provider não suportado.'
                    ),
                };
            }
        );

        $this->app->singleton(
            NfseProvider::class,
            function () {
                return match (
                    config(
                        'finance_fiscal.fiscal.provider',
                        'fake'
                    )
                ) {
                    'fake' => new FakeNfseProvider,
                    'disabled' => new DisabledNfseProvider,
                    default => throw new \LogicException(
                        'NFS-e provider não suportado.'
                    ),
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(BusinessClock::class);

        App::setLocale('pt_BR');
        Carbon::setLocale('pt_BR');

        $jobStartedAt = [];

        Queue::before(function (JobProcessing $event) use (&$jobStartedAt): void {
            $key = $event->job->getJobId()
                ?? (string) spl_object_id($event->job);
            $jobStartedAt[$key] = hrtime(true);

            Log::debug('Job iniciado.', $this->jobContext($event));
        });

        Queue::after(function (JobProcessed $event) use (&$jobStartedAt): void {
            $key = $event->job->getJobId()
                ?? (string) spl_object_id($event->job);
            $startedAt = $jobStartedAt[$key] ?? null;
            unset($jobStartedAt[$key]);

            Log::debug('Job concluído.', [
                ...$this->jobContext($event),
                'status' => 'completed',
                'duration_ms' => $startedAt === null
                    ? null
                    : (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);
        });

        Queue::failing(function (JobFailed $event) use (&$jobStartedAt): void {
            $key = $event->job->getJobId()
                ?? (string) spl_object_id($event->job);
            $startedAt = $jobStartedAt[$key] ?? null;
            unset($jobStartedAt[$key]);

            Log::error('Job falhou definitivamente.', [
                ...$this->jobContext($event),
                'status' => 'failed',
                'duration_ms' => $startedAt === null
                    ? null
                    : (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'exception' => $event->exception,
            ]);
        });

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $notifications = Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('resolved_at')
                ->latest()
                ->limit(6)
                ->get();

            $view->with([
                'topbarNotifications' => $notifications,
                'topbarUnreadNotificationCount' => Notification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('resolved_at')
                    ->whereNull('read_at')
                    ->count(),
            ]);
        });

        RateLimiter::for(
            'documentation-api',
            fn (Request $request) => Limit::perMinute(
                $this->documentationRateLimit('rate_limit')
            )->by($request->ip())
        );

        RateLimiter::for(
            'documentation-api-client',
            function (Request $request) {
                $apiClient = $request->attributes->get('api_client');

                // LEGACY COMPATIBILITY: the legacy token has no ApiClient.
                if ($apiClient === 'legacy') {
                    return Limit::none();
                }

                if (! $apiClient instanceof ApiClient) {
                    throw new \LogicException(
                        'Contexto do ApiClient não disponível.'
                    );
                }

                return Limit::perMinute(
                    $this->documentationRateLimit('client_rate_limit')
                )->by('documentation-api-client:'.$apiClient->getKey());
            }
        );

        RateLimiter::for(
            'efi-payment-webhook',
            fn (Request $request) => Limit::perMinute(
                max(1, min(300, (int) config(
                    'finance_fiscal.providers.efi.webhook_rate_limit',
                    30
                )))
            )->by($request->ip())
        );

        RateLimiter::for('scheduling-availability', fn (Request $request) => Limit::perMinute((int) config('scheduling.public_rate_limit', 60))->by('scheduling-availability:'.$request->ip()));
        RateLimiter::for('scheduling-booking', fn (Request $request) => Limit::perMinute((int) config('scheduling.booking_rate_limit', 10))->by('scheduling-booking:'.$request->ip()));
        RateLimiter::for('scheduling-action', fn (Request $request) => Limit::perMinute(10)->by('scheduling-action:'.$request->ip()));
    }

    private function jobContext(object $event): array
    {
        return [
            'operation' => 'queue.job',
            'module' => 'queue',
            'queue' => $event->job->getQueue(),
            'job' => $event->job->resolveName(),
            'job_id' => $event->job->getJobId(),
            'attempt' => $event->job->attempts(),
            'connection' => $event->connectionName,
        ];
    }

    private function documentationRateLimit(string $key): int
    {
        $value = config("documentation.{$key}", 120);

        if (
            filter_var($value, FILTER_VALIDATE_INT) === false
            || (int) $value < 1
            || (int) $value > 10000
        ) {
            return 120;
        }

        return (int) $value;
    }
}
