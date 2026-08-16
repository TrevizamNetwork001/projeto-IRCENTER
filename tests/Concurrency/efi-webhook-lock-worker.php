<?php

use App\Jobs\ProcessEfiPaymentWebhook;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (
    app()->environment() !== 'testing'
    || config('cache.default') !== 'redis'
    || config('database.redis.default.host') !== 'e2e-redis'
) {
    fwrite(STDERR, "Ambiente isolado de concorrência inválido.\n");
    exit(64);
}

$action = $argv[1] ?? '';
$prefix = 'ircenter:test:efi:webhook:concurrency:';

if ($action === 'reset') {
    foreach (['active', 'external', 'overlap'] as $name) {
        Cache::forget($prefix.$name);
    }

    exit(0);
}

if ($action === 'check') {
    $valid = (int) Cache::get($prefix.'active', 0) === 0
        && (int) Cache::get($prefix.'external', 0) === 2
        && (int) Cache::get($prefix.'overlap', 0) === 0;

    exit($valid ? 0 : 1);
}

$job = new ProcessEfiPaymentWebhook(4242);
$middleware = $job->middleware()[0];
$entered = false;

$middleware->handle($job, function () use ($prefix, &$entered): void {
    $entered = true;
    Http::preventStrayRequests();
    Http::fake([
        'https://efi-concurrency.invalid/*' => function () use ($prefix) {
            $active = Cache::increment($prefix.'active');

            if ($active > 1) {
                Cache::increment($prefix.'overlap');
            }

            usleep(2_000_000);
            Cache::increment($prefix.'external');
            Cache::decrement($prefix.'active');

            return Http::response(['ok' => true]);
        },
    ]);

    Http::get('https://efi-concurrency.invalid/notification');
});

fwrite(STDOUT, $entered ? "entered\n" : "delayed\n");
