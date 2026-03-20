#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Deploy WordPress theme poradnik.pro.

Usage:
  scripts/deploy-theme.sh [options]

Options:
  --source <path>        Theme source directory (default: poradnik.pro)
  --local-target <path>  Local wp-content/themes target directory
  --ssh <user@host>      SSH target for remote deployment
  --remote-path <path>   Remote wp-content/themes target directory (required with --ssh)
  --theme-slug <slug>    Theme directory name in target (default: poradnik.pro)
  --dry-run              Show rsync plan without changing files
  --backup               Create timestamped backup of existing target theme dir
  -h, --help             Show help

Examples:
  scripts/deploy-theme.sh --local-target /var/www/html/wp-content/themes
  scripts/deploy-theme.sh --ssh deploy@example.com --remote-path /var/www/html/wp-content/themes --backup
EOF
}

SOURCE_DIR="poradnik.pro"
LOCAL_TARGET=""
SSH_TARGET=""
REMOTE_PATH=""
THEME_SLUG="poradnik.pro"
DRY_RUN=false
BACKUP=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --source)
      SOURCE_DIR="${2:-}"
      shift 2
      ;;
    --local-target)
      LOCAL_TARGET="${2:-}"
      shift 2
      ;;
    --ssh)
      SSH_TARGET="${2:-}"
      shift 2
      ;;
    --remote-path)
      REMOTE_PATH="${2:-}"
      shift 2
      ;;
    --theme-slug)
      THEME_SLUG="${2:-}"
      shift 2
      ;;
    --dry-run)
      DRY_RUN=true
      shift
      ;;
    --backup)
      BACKUP=true
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Source directory not found: $SOURCE_DIR" >&2
  exit 1
fi

if [[ -n "$LOCAL_TARGET" && -n "$SSH_TARGET" ]]; then
  echo "Choose either --local-target or --ssh (not both)." >&2
  exit 1
fi

if [[ -z "$LOCAL_TARGET" && -z "$SSH_TARGET" ]]; then
  echo "Provide deployment target: --local-target or --ssh." >&2
  exit 1
fi

RSYNC_COMMON=(
  --archive
  --compress
  --delete
  --human-readable
  --exclude=.git/
  --exclude=.DS_Store
  --exclude=node_modules/
)

if [[ "$DRY_RUN" == true ]]; then
  RSYNC_COMMON+=(--dry-run --itemize-changes)
fi

TIMESTAMP="$(date -u +%Y%m%d-%H%M%S)"

if [[ -n "$LOCAL_TARGET" ]]; then
  TARGET_DIR="${LOCAL_TARGET%/}/${THEME_SLUG}"
  mkdir -p "$TARGET_DIR"

  if [[ "$BACKUP" == true && -d "$TARGET_DIR" ]]; then
    BACKUP_DIR="${LOCAL_TARGET%/}/${THEME_SLUG}.backup-${TIMESTAMP}"
    cp -a "$TARGET_DIR" "$BACKUP_DIR"
    echo "Backup created: $BACKUP_DIR"
  fi

  echo "Deploying local theme to: $TARGET_DIR"
  rsync "${RSYNC_COMMON[@]}" "$SOURCE_DIR/" "$TARGET_DIR/"
  echo "Deployment complete (local)."
  exit 0
fi

if [[ -z "$REMOTE_PATH" ]]; then
  echo "When using --ssh you must provide --remote-path." >&2
  exit 1
fi

REMOTE_THEME_DIR="${REMOTE_PATH%/}/${THEME_SLUG}"

if [[ "$BACKUP" == true ]]; then
  ssh "$SSH_TARGET" "if [ -d '$REMOTE_THEME_DIR' ]; then cp -a '$REMOTE_THEME_DIR' '${REMOTE_THEME_DIR}.backup-${TIMESTAMP}'; fi"
  echo "Remote backup step executed."
fi

echo "Deploying remote theme to: ${SSH_TARGET}:${REMOTE_THEME_DIR}"
ssh "$SSH_TARGET" "mkdir -p '$REMOTE_THEME_DIR'"
rsync "${RSYNC_COMMON[@]}" -e ssh "$SOURCE_DIR/" "${SSH_TARGET}:${REMOTE_THEME_DIR}/"
echo "Deployment complete (remote)."
