<?php

namespace App\Providers;

use App\Models\Notification;
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
            \App\Modules\Shared\Contracts\ClientDirectory::class,
            \App\Modules\Shared\Infrastructure\CoreClientDirectory::class,
        );

        $this->app->singleton(
            \App\Modules\Finance\Contracts\PaymentProvider::class,
            function () {
                return match (
                    config(
                        'finance_fiscal.finance.payment_provider',
                        'fake'
                    )
                ) {
                    'fake' => new \App\Modules\Finance\Infrastructure\FakePaymentProvider(),

                    'efi' => $this->app->make(
                        \App\Modules\Finance\Infrastructure\EfiPaymentProvider::class
                    ),
                    default => throw new \LogicException(
                        'Payment provider não suportado.'
                    ),
                };
            }
        );

        $this->app->singleton(
            \App\Modules\Fiscal\Contracts\NfseProvider::class,
            function () {
                return match (
                    config(
                        'finance_fiscal.fiscal.nfse_provider',
                        'fake'
                    )
                ) {
                    'fake' => new \App\Modules\Fiscal\Infrastructure\FakeNfseProvider(),
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
                'topbarUnreadNotificationCount' =>
                    Notification::query()
                        ->where('user_id', $user->id)
                        ->whereNull('resolved_at')
                        ->whereNull('read_at')
                        ->count(),
            ]);
        });

        RateLimiter::for(
            'documentation-api',
            fn (Request $request) => Limit::perMinute(
                (int) config(
                    'documentation.rate_limit',
                    120
                )
            )->by($request->ip())
        );
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
}
