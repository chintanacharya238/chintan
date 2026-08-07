#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

ensure_docker() {
  if docker info >/dev/null 2>&1 || sg docker -c 'docker info' >/dev/null 2>&1; then
    return 0
  fi
  if [[ ! -f /etc/docker/daemon.json ]]; then
    sudo mkdir -p /etc/docker
    printf '%s\n' '{' '  "storage-driver": "fuse-overlayfs"' '}' | sudo tee /etc/docker/daemon.json >/dev/null
  fi
  if ! pgrep -x dockerd >/dev/null 2>&1; then
    sudo dockerd >/tmp/dockerd.log 2>&1 &
    for _ in $(seq 1 30); do
      if docker info >/dev/null 2>&1 || sg docker -c 'docker info' >/dev/null 2>&1; then
        return 0
      fi
      sleep 1
    done
  fi
  sg docker -c 'docker info' >/dev/null 2>&1
}

ensure_docker || { echo "Docker daemon is not available." >&2; exit 1; }

if sg docker -c 'docker compose version' >/dev/null 2>&1; then
  sg docker -c "docker compose up -d db wordpress"
else
  sudo docker compose up -d db wordpress
fi

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
