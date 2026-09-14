#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
case "${1:-5.7.17}" in
    5.7.17|5.6.35) export UPGRADE_MYSQL_VERSION=${1:-5.7.17} ;;
    *) echo 'Usage: bash compat/upgrade.sh [5.7.17|5.6.35]' >&2; exit 2 ;;
esac
mkdir -p test-results
UPGRADE_RESULTS=$(mktemp -d "$PWD/test-results/upgrade-XXXXXXXX")
export UPGRADE_RESULTS
export COMPOSE_PROJECT_NAME="megalopolis-$(basename "$UPGRADE_RESULTS" | tr '[:upper:]' '[:lower:]')"
compose=(docker compose -f compose.upgrade.yml)
git rev-parse HEAD > "$UPGRADE_RESULTS/checkout.txt"
git status --short >> "$UPGRADE_RESULTS/checkout.txt"
git diff -- req index.php > "$UPGRADE_RESULTS/application.patch"
"${compose[@]}" config > "$UPGRADE_RESULTS/compose.yml"
cleanup() {
    local status=$?
    trap - EXIT
    "${compose[@]}" --profile '*' logs --no-color > "$UPGRADE_RESULTS/containers.log" 2>&1 || true
    "${compose[@]}" --profile '*' down --volumes --remove-orphans > "$UPGRADE_RESULTS/cleanup.log" 2>&1 || true
    echo "Upgrade artifacts: $UPGRADE_RESULTS"
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
"${compose[@]}" build php52-http-base candidate-sqlite
"${compose[@]}" build legacy-base
"${compose[@]}" build sqlite2016
"${compose[@]}" run --rm --no-deps --user "$(id -u):$(id -g)" tests \
    vendor/bin/phpunit --testsuite unit --cache-directory /tmp/phpunit-cache
"${compose[@]}" run --rm --no-deps seed
"${compose[@]}" up -d --wait --wait-timeout 240 mysql-baseline mysql-candidate
# Take a real backup before either runtime can migrate or increment a counter.
"${compose[@]}" run --rm --no-deps seed php compat/http/upgrade/stores.php snapshot
"${compose[@]}" exec -T mysql-baseline sh -c \
    'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump -ufixture --single-transaction --skip-comments --hex-blob --set-gtid-purged=OFF --default-character-set=utf8 megalopolis_r46' \
    | gzip -n > "$UPGRADE_RESULTS/pre-upgrade.sql.gz"
sha256sum "$UPGRADE_RESULTS"/pre-upgrade* > "$UPGRADE_RESULTS/backup.sha256"
"${compose[@]}" up -d --wait --wait-timeout 240 \
    baseline-sqlite baseline-mysql candidate-sqlite candidate-mysql
status=0
"${compose[@]}" run --rm --no-deps --user "$(id -u):$(id -g)" tests || status=1
# Restoration remains independently testable even if an operation regresses.
"${compose[@]}" run --rm --no-deps seed php compat/http/upgrade/stores.php restore
"${compose[@]}" up -d --wait --wait-timeout 240 mysql-restored restored-sqlite restored-mysql
"${compose[@]}" run --rm --no-deps --user "$(id -u):$(id -g)" tests \
    vendor/bin/phpunit --testsuite restore --cache-directory /tmp/phpunit-cache \
    --log-junit /results/restore.xml || status=1
sha256sum --check "$UPGRADE_RESULTS/backup.sha256"
exit "$status"
