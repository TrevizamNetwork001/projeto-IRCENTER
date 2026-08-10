<?php

use App\Http\Controllers\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/health/ready', ReadinessController::class)
    ->name('health.ready');
