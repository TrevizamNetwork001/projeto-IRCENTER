# IRCENTER — Operação em produção

## Serviços

A implantação utiliza Docker Compose com:

- Nginx
- PHP-FPM
- PostgreSQL
- Redis
- worker de fila
- scheduler
- Certbot

## Endpoints de saúde

### Liveness

`GET /up`

Confirma que o framework Laravel consegue inicializar.

### Readiness

`GET /health/ready`

Confirma:

- aplicação inicializada
- PostgreSQL disponível
- Redis disponível

Retorna HTTP 200 quando todos os serviços estão disponíveis e HTTP 503
quando algum serviço essencial está indisponível. A resposta pública é
agregada e contém somente `{"status":"ready"}` ou
`{"status":"unavailable"}`; detalhes das dependências ficam restritos ao
diagnóstico administrativo.

## Diagnóstico administrativo

`/system-diagnostic`

Acesso restrito a administradores. Exibe PostgreSQL, Redis, fila, jobs
pendentes, jobs falhos, última automação, última integração externa,
ambiente e versões.

## Configuração PHP recomendada

Arquivo usado no ambiente:

`docker/php/production.ini`

Configurações principais:

    expose_php = Off
    display_errors = Off
    display_startup_errors = Off
    log_errors = On
    memory_limit = 256M
    post_max_size = 20M
    upload_max_filesize = 20M
    session.cookie_httponly = 1
    session.cookie_secure = 1
    session.cookie_samesite = Lax
    opcache.enable = 1
    opcache.enable_cli = 1

O Dockerfile deve copiar o arquivo para:

`/usr/local/etc/php/conf.d/zz-ircenter-production.ini`

## Headers de segurança

O Nginx e o middleware da aplicação aplicam:

- X-Content-Type-Options: nosniff
- X-Frame-Options: SAMEORIGIN
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
- Cross-Origin-Opener-Policy: same-origin
- X-Permitted-Cross-Domain-Policies: none
- Strict-Transport-Security: max-age=31536000; includeSubDomains

O Nginx também deve usar:

    server_tokens off;
    fastcgi_hide_header X-Powered-By;

## Ambiente Laravel

Configuração recomendada:

    APP_ENV=production
    APP_DEBUG=false
    APP_LOCALE=pt_BR
    APP_FALLBACK_LOCALE=pt_BR

    LOG_CHANNEL=daily
    LOG_LEVEL=warning
    LOG_DAILY_DAYS=30

    SESSION_DRIVER=database
    SESSION_ENCRYPT=true
    SESSION_SECURE_COOKIE=true
    SESSION_HTTP_ONLY=true
    SESSION_SAME_SITE=lax

    CACHE_STORE=redis
    QUEUE_CONNECTION=redis
    QUEUE_FAILED_DRIVER=database-uuids

## Rebuild do ambiente PHP

Após alteração no Dockerfile ou no arquivo INI:

    docker compose build app queue scheduler
    docker compose up -d --no-deps --force-recreate app queue scheduler

Após alteração do Nginx:

    docker compose exec web nginx -t
    docker compose up -d --no-deps --force-recreate web

## Validações após deploy

    docker compose ps
    docker compose exec web nginx -t
    docker compose exec app php artisan migrate:status
    docker compose exec app php artisan queue:failed
    docker compose exec app php artisan schedule:list

    curl -fsS https://HOST/health/ready
    curl -I https://HOST/login
    curl -I https://HOST/up

O header X-Powered-By não deve ser retornado.

## Backup do PostgreSQL

Siga o procedimento obrigatório de dump custom, SHA-256 e restore isolado em [DATABASE_BACKUP_RESTORE.md](DATABASE_BACKUP_RESTORE.md). Backups ficam fora do checkout, em `/var/backups/ircenter`, e não são considerados válidos apenas por existirem.

## Restauração

A restauração em produção exige janela e autorização próprias. A validação pré-migration deve restaurar somente em database temporário isolado, aplicar os guardrails e comparações documentados e remover esse database após o aceite. Nunca restaure sobre o banco produtivo como teste.
