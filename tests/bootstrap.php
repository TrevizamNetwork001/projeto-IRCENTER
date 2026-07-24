<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

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
