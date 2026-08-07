<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$productionConfigCache = dirname(__DIR__).'/bootstrap/cache/config.php';

if (is_file($productionConfigCache)) {
    fwrite(
        STDERR,
        "\nTESTES BLOQUEADOS POR SEGURANÇA.\n".
        "Foi detectado bootstrap/cache/config.php.\n".
        "Isso pode fazer a suíte reutilizar a configuração cacheada de produção.\n\n".
        "Execute os testes pelo wrapper seguro:\n".
        "  sh scripts/test-safe.sh\n".
        "ou:\n".
        "  composer test\n\n"
    );

    exit(78);
}

$environment = getenv('APP_ENV');
$connection = getenv('DB_CONNECTION');
$database = getenv('DB_DATABASE');

if (
    $environment !== 'testing'
    || $connection !== 'sqlite'
    || $database !== ':memory:'
) {
    fwrite(
        STDERR,
        sprintf(
            "\nTESTES BLOQUEADOS POR SEGURANÇA.\n".
            "APP_ENV=%s\n".
            "DB_CONNECTION=%s\n".
            "DB_DATABASE=%s\n\n",
            $environment === false ? '[ausente]' : $environment,
            $connection === false ? '[ausente]' : $connection,
            $database === false ? '[ausente]' : $database,
        )
    );

    exit(78);
}
