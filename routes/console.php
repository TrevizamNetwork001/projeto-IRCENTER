<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command(
    'ircenter:sync-notifications --trigger=scheduled --isolated=12'
)
    ->name('ircenter-sync-operational-notifications')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
