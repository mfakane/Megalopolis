#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
# Use the same PHP/dependencies as the HTTP tests, but analyse the real config.php.
# No legacy runtime, database, network access at analysis time, or host PHP needed.
analysis_image_id=$(mktemp)
trap 'rm -f -- "$analysis_image_id"' EXIT
docker build --file compat/http/Dockerfile.current --iidfile "$analysis_image_id" .
docker run --rm --network none \
    --volume "$PWD/config.php:/app/config.php:ro" \
    "$(< "$analysis_image_id")" composer analyse
