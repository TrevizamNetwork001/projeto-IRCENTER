<?php

namespace App\Providers;

use App\Models\Notification;
use App\Support\BusinessClock;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
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
}
