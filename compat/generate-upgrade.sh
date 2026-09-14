#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
case "${1:-5.7.17}" in
    5.7.17|5.6.35) export UPGRADE_MYSQL_VERSION="${1:-5.7.17}" ;;
    *) echo 'Usage: bash compat/generate-upgrade.sh [5.7.17|5.6.35]' >&2; exit 2 ;;
esac
mkdir -p test-results
UPGRADE_GENERATED=$(mktemp -d "$PWD/test-results/generate-upgrade-XXXXXXXX")
export UPGRADE_GENERATED UPGRADE_RESULTS="$UPGRADE_GENERATED"
export COMPOSE_PROJECT_NAME="megalopolis-$(basename "$UPGRADE_GENERATED" | tr '[:upper:]' '[:lower:]')"
compose=(docker compose -f compose.upgrade.yml)
cleanup() {
    local status=$?
    trap - EXIT
    "${compose[@]}" --profile '*' logs --no-color > "$UPGRADE_GENERATED/containers.log" 2>&1 || true
    "${compose[@]}" --profile '*' down --volumes --remove-orphans > "$UPGRADE_GENERATED/cleanup.log" 2>&1 || true
    echo "Generated files (never overwrites frozen inputs): $UPGRADE_GENERATED"
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
"${compose[@]}" build php52-http-base
"${compose[@]}" build legacy-base
"${compose[@]}" build sqlite2016
"${compose[@]}" up -d --wait --wait-timeout 240 mysql-generate
"${compose[@]}" run --rm --no-deps --user "$(id -u):$(id -g)" generate
"${compose[@]}" run --rm --no-deps --user "$(id -u):$(id -g)" -e COMPAT_DRIVER=mysql generate
"${compose[@]}" exec -T -e MYSQL_PWD=fixture mysql-generate \
    mysqldump -ufixture --single-transaction --skip-comments --hex-blob --set-gtid-purged=OFF \
    --default-character-set=utf8 megalopolis_r46 > "$UPGRADE_GENERATED/mysql.sql"
for file in data.sqlite search.sqlite mysql.sql; do
    gzip -n -c "$UPGRADE_GENERATED/$file" > "$UPGRADE_GENERATED/$file.gz"
done
