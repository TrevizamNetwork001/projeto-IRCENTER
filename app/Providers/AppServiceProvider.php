<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Services\NotificationService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        App::setLocale('pt_BR');
        Carbon::setLocale('pt_BR');

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $notifications = app(NotificationService::class)
                ->syncFor($user);

            $view->with([
                'topbarNotifications' => $notifications->take(6),
                'topbarUnreadNotificationCount' => $notifications
                    ->whereNull('read_at')
                    ->count(),
            ]);
        });
    }
}
