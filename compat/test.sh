#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
case "${1:-quick}" in
    quick) export COMPAT_FULL=0 COMPAT_SUITES=unit,database,json,feeds ;;
    full) export COMPAT_FULL=1 COMPAT_SUITES=unit,database,json,feeds ;;
    db) export COMPAT_FULL=0 COMPAT_SUITES=unit,database ;;
    *) echo 'Usage: bash compat/test.sh [quick|full|db]' >&2; exit 2 ;;
esac
mkdir -p test-results
COMPAT_RESULTS=$(mktemp -d "$PWD/test-results/run-XXXXXXXX")
export COMPAT_RESULTS
export COMPOSE_PROJECT_NAME="megalopolis-test-$(basename "$COMPAT_RESULTS" | tr '[:upper:]' '[:lower:]')"
compose=(docker compose -f compose.test.yml)
git rev-parse HEAD > "$COMPAT_RESULTS/checkout.txt"
git status --short >> "$COMPAT_RESULTS/checkout.txt"
git diff -- req index.php > "$COMPAT_RESULTS/application.patch"
"${compose[@]}" config > "$COMPAT_RESULTS/compose.yml"
cleanup() {
    local status=$?
    trap - EXIT
    "${compose[@]}" logs --no-color > "$COMPAT_RESULTS/containers.log" 2>&1 || true
    "${compose[@]}" down --volumes --remove-orphans > "$COMPAT_RESULTS/cleanup.log" 2>&1 || true
    echo "Test artifacts: $COMPAT_RESULTS"
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# Explicit ordering: the legacy HTTP image extends our optional CGI build.
"${compose[@]}" build php52-http-base candidate-sqlite
"${compose[@]}" build baseline-sqlite
# Validate the independently recorded baseline hashes before importing SQL.
"${compose[@]}" run --rm --no-deps --user "$(id -u):$(id -g)" tests \
    vendor/bin/phpunit --testsuite unit --cache-directory /tmp/phpunit-cache
"${compose[@]}" up -d --wait --wait-timeout 240 \
    baseline-sqlite candidate-sqlite baseline-mysql candidate-mysql
"${compose[@]}" exec -T baseline-sqlite php /harness/runtime.php > "$COMPAT_RESULTS/r46-runtime.txt"
"${compose[@]}" exec -T candidate-sqlite php compat/http/runtime.php > "$COMPAT_RESULTS/current-runtime.txt"
"${compose[@]}" exec -T mysql-candidate mysql --version > "$COMPAT_RESULTS/mysql-runtime.txt"
"${compose[@]}" run --rm --no-deps --user "$(id -u):$(id -g)" tests
