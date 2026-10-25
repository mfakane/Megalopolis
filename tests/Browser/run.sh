#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PROJECT="megalopolis-browser-${RANDOM}-${RANDOM}"
PORT="${BROWSER_PORT:-18080}"
REWRITE_PORT="${BROWSER_REWRITE_PORT:-$((PORT + 1))}"
RESULTS="${ROOT_DIR}/test-results/browser-${PROJECT}"

mkdir -p "$RESULTS"

cleanup() {
	status=$?
	BROWSER_PORT="$PORT" BROWSER_REWRITE_PORT="$REWRITE_PORT" docker compose \
		--project-name "$PROJECT" \
		--file "$ROOT_DIR/tests/Browser/compose.yml" \
		down --volumes --remove-orphans >"$RESULTS/compose-down.log" 2>&1 || true
	exit "$status"
}
trap cleanup EXIT

cd "$ROOT_DIR"
BROWSER_PORT="$PORT" BROWSER_REWRITE_PORT="$REWRITE_PORT" docker compose \
	--project-name "$PROJECT" \
	--file tests/Browser/compose.yml \
	up --build --detach --wait --wait-timeout 180

# Query links (?path=) at the site root.
BASE_URL="http://127.0.0.1:${PORT}" \
LINK_MODE=query \
PLAYWRIGHT_OUTPUT_DIR="$RESULTS/query" \
npm --prefix tests/Browser test -- --config=playwright.config.ts

# mod_rewrite links with the application under /sub/.
BASE_URL="http://127.0.0.1:${REWRITE_PORT}/sub/" \
LINK_MODE=rewrite \
PLAYWRIGHT_OUTPUT_DIR="$RESULTS/rewrite" \
npm --prefix tests/Browser test -- --config=playwright.config.ts
