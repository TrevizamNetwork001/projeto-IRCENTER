#!/bin/sh

set -eu

cd /var/www/html

CACHE_FILE="bootstrap/cache/config.php"
CACHE_BACKUP="bootstrap/cache/config.php.before-tests"

restore_production_cache()
{
    rm -f "$CACHE_FILE"

    if [ -f "$CACHE_BACKUP" ]; then
        mv "$CACHE_BACKUP" "$CACHE_FILE"
        echo "Cache de produção restaurado."
    else
        php artisan config:cache >/dev/null
        echo "Cache de produção recriado."
    fi
}

trap restore_production_cache EXIT INT TERM

if [ -f "$CACHE_BACKUP" ]; then
    echo "Backup antigo de cache encontrado; execução bloqueada."
    exit 1
fi

if [ -f "$CACHE_FILE" ]; then
    mv "$CACHE_FILE" "$CACHE_BACKUP"
fi

rm -f bootstrap/cache/config.php

printf '%s\n' \
    "Ambiente de teste obrigatório:" \
    "  APP_ENV=testing" \
    "  DB_CONNECTION=sqlite" \
    "  DB_DATABASE=:memory:" \
    "  FINANCE_FISCAL_DB_CONNECTION=sqlite" \
    "  FINANCE_FISCAL_DB_DATABASE=:memory:"

APP_ENV=testing \
DB_CONNECTION=sqlite \
DB_DATABASE=:memory: \
DB_URL= \
FINANCE_FISCAL_DB_CONNECTION=sqlite \
FINANCE_FISCAL_DB_DATABASE=:memory: \
FINANCE_FISCAL_DB_URL= \
CACHE_STORE=array \
QUEUE_CONNECTION=sync \
SESSION_DRIVER=array \
php artisan test "$@"
