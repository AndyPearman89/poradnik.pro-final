#!/usr/bin/env bash

set -euo pipefail

SITE_URL="http://localhost:8080"
SITE_TITLE="Poradnik Pro Local"
ADMIN_USER="admin"
ADMIN_PASSWORD="admin123!"
ADMIN_EMAIL="admin@example.com"
THEME_SLUG="poradnik.pro"
PERMALINK_STRUCT='/%postname%/'
PLUGIN_SLUG="peartree-local-module/peartree-local-module.php"
MAX_RETRIES=30
RETRY_DELAY_SEC=2
DRY_RUN="0"

usage() {
  cat <<'EOF'
Usage: bash scripts/bootstrap-wp.sh [options]

Options:
  --site-url <url>
  --site-title <title>
  --admin-user <user>
  --admin-password <pass>
  --admin-email <email>
  --theme <slug>
  --plugin <plugin-path>
  --permalink <structure>
  --max-retries <n>
  --retry-delay <sec>
  --dry-run
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --site-url) SITE_URL="${2:-}"; shift 2 ;;
    --site-title) SITE_TITLE="${2:-}"; shift 2 ;;
    --admin-user) ADMIN_USER="${2:-}"; shift 2 ;;
    --admin-password) ADMIN_PASSWORD="${2:-}"; shift 2 ;;
    --admin-email) ADMIN_EMAIL="${2:-}"; shift 2 ;;
    --theme) THEME_SLUG="${2:-}"; shift 2 ;;
    --plugin) PLUGIN_SLUG="${2:-}"; shift 2 ;;
    --permalink) PERMALINK_STRUCT="${2:-}"; shift 2 ;;
    --max-retries) MAX_RETRIES="${2:-}"; shift 2 ;;
    --retry-delay) RETRY_DELAY_SEC="${2:-}"; shift 2 ;;
    --dry-run) DRY_RUN="1"; shift ;;
    -h|--help) usage; exit 0 ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 2
      ;;
  esac
done

run_wp() {
  if [[ "$DRY_RUN" == "1" ]]; then
    echo "[dry-run] docker compose --profile tools run --rm wpcli wp $* --allow-root"
    return 0
  fi

  docker compose --profile tools run --rm wpcli wp "$@" --allow-root
}

echo "bootstrap-wp: waiting for WP/DB readiness"
for attempt in $(seq 1 "$MAX_RETRIES"); do
  if run_wp core is-installed >/dev/null 2>&1; then
    INSTALLED="1"
    break
  fi

  # wp option get works even before install only when DB and WP are reachable.
  if run_wp option get siteurl >/dev/null 2>&1; then
    INSTALLED="0"
    break
  fi

  if [[ "$attempt" -eq "$MAX_RETRIES" ]]; then
    echo "bootstrap-wp: timeout waiting for wp-cli connectivity" >&2
    exit 1
  fi

  sleep "$RETRY_DELAY_SEC"
done

if [[ "${INSTALLED:-0}" == "1" ]]; then
  echo "bootstrap-wp: WP already installed"
else
  echo "bootstrap-wp: running core install"
  run_wp core install \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
fi

echo "bootstrap-wp: activating theme $THEME_SLUG"
run_wp theme activate "$THEME_SLUG"

echo "bootstrap-wp: ensuring plugin active $PLUGIN_SLUG"
if ! run_wp plugin is-active "$PLUGIN_SLUG" >/dev/null 2>&1; then
  run_wp plugin activate "$PLUGIN_SLUG"
fi

echo "bootstrap-wp: setting permalink structure"
run_wp option update permalink_structure "$PERMALINK_STRUCT"

echo "bootstrap-wp: flushing rewrite rules"
run_wp rewrite flush --hard

echo "bootstrap-wp: done"
