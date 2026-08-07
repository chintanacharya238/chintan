#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
# shellcheck source=docker-lib.sh
source "${ROOT_DIR}/scripts/docker-lib.sh"

ensure_docker || { echo "Docker daemon is not available." >&2; exit 1; }

run_compose down --remove-orphans 2>/dev/null || true
run_compose pull db wordpress wpcli
"${ROOT_DIR}/scripts/bootstrap-wordpress.sh"
