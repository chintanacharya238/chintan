#!/usr/bin/env bash
set -euo pipefail

docker_available() {
  docker info >/dev/null 2>&1 || sudo docker info >/dev/null 2>&1
}

ensure_docker() {
  if docker_available; then
    return 0
  fi

  if [[ ! -f /etc/docker/daemon.json ]]; then
    sudo mkdir -p /etc/docker
    printf '%s\n' '{' '  "storage-driver": "fuse-overlayfs"' '}' | sudo tee /etc/docker/daemon.json >/dev/null
  fi

  if command -v update-alternatives >/dev/null 2>&1; then
    sudo update-alternatives --set iptables /usr/sbin/iptables-legacy >/dev/null 2>&1 || true
    sudo update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy >/dev/null 2>&1 || true
  fi

  if ! pgrep -x dockerd >/dev/null 2>&1; then
    sudo dockerd >/tmp/dockerd.log 2>&1 &
  fi

  for _ in $(seq 1 60); do
    if docker_available; then
      return 0
    fi
    sleep 2
  done

  return 1
}

run_compose() {
  if docker compose "$@" 2>/dev/null; then
    return 0
  fi
  sudo docker compose "$@"
}

wpcli() {
  if docker compose run --rm --entrypoint wp wpcli "$@" 2>/dev/null; then
    return 0
  fi
  sudo docker compose run --rm --entrypoint wp wpcli "$@"
}
