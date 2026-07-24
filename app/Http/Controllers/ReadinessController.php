<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'application' => true,
            'database' => $this->databaseReady(),
            'redis' => $this->redisReady(),
        ];

        $ready = ! in_array(false, $checks, true);

        return response()->json(
            [
                'status' => $ready ? 'ready' : 'unavailable',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            $ready ? 200 : 503
        );
    }

    private function databaseReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function redisReady(): bool
    {
        try {
            $response = Redis::connection()->ping();

            return in_array(
                strtoupper((string) $response),
                ['PONG', '1'],
                true
            );
        } catch (Throwable) {
            return false;
        }
    }
}
