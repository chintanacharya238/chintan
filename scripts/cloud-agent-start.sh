#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
# shellcheck source=docker-lib.sh
source "${ROOT_DIR}/scripts/docker-lib.sh"

ensure_docker || { echo "Docker daemon is not available." >&2; exit 1; }

run_compose up -d db wordpress

SITE_URL="${WP_SITE_URL:-http://localhost:8080}"
for _ in $(seq 1 60); do
  if curl -fsS "$SITE_URL" >/dev/null 2>&1; then
    echo "WordPress is ready at $SITE_URL"
    exit 0
  fi
  sleep 2
done

echo "Timed out waiting for WordPress at $SITE_URL" >&2
exit 1
