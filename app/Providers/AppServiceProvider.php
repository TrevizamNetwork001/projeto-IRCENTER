<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\Notification;
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
    }
}
