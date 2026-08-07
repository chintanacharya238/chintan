#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
# shellcheck source=docker-lib.sh
source "${ROOT_DIR}/scripts/docker-lib.sh"

SITE_URL="${WP_SITE_URL:-http://localhost:8080}"

wait_for_wordpress() {
  local attempts=0
  until curl -fsS "$SITE_URL" >/dev/null 2>&1; do
    attempts=$((attempts + 1))
    if [[ $attempts -ge 60 ]]; then
      echo "WordPress did not become ready at $SITE_URL" >&2
      exit 1
    fi
    sleep 2
  done
}

run_compose up -d db

for _ in $(seq 1 60); do
  if run_compose ps --status running db 2>/dev/null | grep -q db; then
    health="$(run_compose ps --format json db 2>/dev/null | grep -o '"Health":"[^"]*"' | head -1 || true)"
    if [[ "$health" == *healthy* ]] || run_compose exec -T db mysqladmin ping -h localhost -uwordpress -pwordpress >/dev/null 2>&1; then
      break
    fi
  fi
  sleep 2
done

run_compose up -d wordpress
wait_for_wordpress

if ! wpcli core is-installed >/dev/null 2>&1; then
  wpcli core install \
    --url="$SITE_URL" \
    --title="CRH Allocated Areas Dev" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email
fi

wpcli option update blog_public 0
wpcli rewrite structure '/%postname%/' --hard
wpcli rewrite flush --hard

if ! wpcli plugin is-installed woocommerce >/dev/null 2>&1; then
  wpcli plugin install woocommerce --version=9.8.5 --activate
else
  wpcli plugin activate woocommerce || true
fi

wpcli plugin activate crh-areas

wpcli wc --user=admin tool run install_pages || true

PRODUCT_ID="$(wpcli post list --post_type=product --field=ID --format=ids 2>/dev/null | awk '{print $1}')"
if [[ -z "${PRODUCT_ID:-}" ]]; then
  PRODUCT_ID="$(wpcli wc product create \
    --user=admin \
    --name="Rental Chair" \
    --type=simple \
    --regular_price=25 \
    --status=publish \
    --porcelain)"
fi

echo "Bootstrap complete."
echo "Site URL: $SITE_URL"
echo "Admin: admin / admin"
echo "Sample product ID: ${PRODUCT_ID}"
