#!/bin/sh
set -eu

project_root=$(CDPATH= cd -- "$(dirname "$0")/../../.." && pwd)
compose="docker compose --project-name ircenter-webhook-lock-tests --file $project_root/compose.e2e.test.yaml"
hold_output=$(mktemp -t ircenter-lock-hold.XXXXXX)
delay_output=$(mktemp -t ircenter-lock-delay.XXXXXX)
before_containers=$(mktemp -t ircenter-lock-before.XXXXXX)
after_containers=$(mktemp -t ircenter-lock-after.XXXXXX)

snapshot_production() {
    output=$1
    : >"$output"

    for container in ircenter-app ircenter-queue ircenter-scheduler \
        ircenter-documentation-app ircenter-documentation-queue \
        ircenter-documentation-scheduler ircenter-web \
        ircenter-postgres ircenter-redis
    do
        docker inspect --format '{{.Name}}|{{.Id}}|{{.State.Status}}|{{.RestartCount}}' \
            "$container" >>"$output" 2>/dev/null \
            || printf '/%s|not-found\n' "$container" >>"$output"
    done
}

cleanup() {
    status=$?
    $compose down --volumes --remove-orphans >/dev/null 2>&1 || true
    snapshot_production "$after_containers"

    if ! cmp -s "$before_containers" "$after_containers"; then
        printf 'FALHA: containers produtivos foram alterados.\n' >&2
        diff -u "$before_containers" "$after_containers" >&2 || true
        status=65
    else
        printf 'OK: containers produtivos inalterados.\n'
    fi

    rm -f -- "$hold_output" "$delay_output" \
        "$before_containers" "$after_containers"
    exit "$status"
}
trap cleanup EXIT INT TERM

snapshot_production "$before_containers"
$compose build e2e-init
$compose up -d --wait e2e-redis

run_worker() {
    $compose run --rm --no-deps \
        -e APP_ENV=testing \
        e2e-init php tests/Concurrency/efi-webhook-lock-worker.php "$1"
}

run_worker reset
run_worker hold >"$hold_output" &
hold_pid=$!
sleep 1
run_worker contend >"$delay_output"
wait "$hold_pid"

grep -Fx 'entered' "$hold_output" >/dev/null
grep -Fx 'delayed' "$delay_output" >/dev/null
run_worker after | grep -Fx 'entered' >/dev/null
run_worker check

printf '%s\n' \
    'OK: dois processos usaram Redis efêmero isolado.' \
    'OK: máximo de um processamento simultâneo.' \
    'OK: job sobreposto executou após liberação.'
