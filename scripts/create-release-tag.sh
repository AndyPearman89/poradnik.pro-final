#!/usr/bin/env bash
# ============================================================
# TASK-H05 – Create Release Tag & Freeze Critical Changes
#
# Usage:
#   bash scripts/create-release-tag.sh [OPTIONS]
#
# Options:
#   --version VERSION   Explicit version tag (e.g. v1.0.0).
#                       If omitted, auto-increments the latest tag.
#   --dry-run           Print actions without executing them.
#   --freeze            After tagging, write a FREEZE file to
#                       signal that critical changes are locked.
#   --unfreeze          Remove the FREEZE file (unlock changes).
#   --check-freeze      Exit 0 if NOT frozen, exit 1 if frozen.
#   --notes TEXT        Custom release notes string.
#   --skip-checks       Skip preflight quality checks (not recommended).
#
# Examples:
#   bash scripts/create-release-tag.sh --version v1.0.0 --freeze
#   bash scripts/create-release-tag.sh --dry-run
#   bash scripts/create-release-tag.sh --check-freeze
#   bash scripts/create-release-tag.sh --unfreeze
#
# Exit codes:
#   0 – success
#   1 – frozen (--check-freeze) or preflight failure
#   2 – usage error
# ============================================================

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FREEZE_FILE="${REPO_ROOT}/.freeze"
TAG_PREFIX="v"
DRY_RUN=false
DO_FREEZE=false
DO_UNFREEZE=false
CHECK_FREEZE=false
SKIP_CHECKS=false
EXPLICIT_VERSION=""
RELEASE_NOTES=""

# ── Parse arguments ──────────────────────────────────────────

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)      EXPLICIT_VERSION="$2"; shift 2 ;;
    --dry-run)      DRY_RUN=true; shift ;;
    --freeze)       DO_FREEZE=true; shift ;;
    --unfreeze)     DO_UNFREEZE=true; shift ;;
    --check-freeze) CHECK_FREEZE=true; shift ;;
    --skip-checks)  SKIP_CHECKS=true; shift ;;
    --notes)        RELEASE_NOTES="$2"; shift 2 ;;
    *)
      echo "ERROR: Unknown option: $1" >&2
      echo "Usage: bash scripts/create-release-tag.sh [--version VERSION] [--dry-run] [--freeze] [--unfreeze] [--check-freeze] [--notes TEXT] [--skip-checks]" >&2
      exit 2
      ;;
  esac
done

# ── Helper functions ─────────────────────────────────────────

log()  { echo "[release-tag] $*"; }
warn() { echo "[release-tag] WARN: $*" >&2; }
err()  { echo "[release-tag] ERROR: $*" >&2; }

run() {
  if [[ "$DRY_RUN" == "true" ]]; then
    echo "[DRY-RUN] $*"
  else
    "$@"
  fi
}

# ── --check-freeze ───────────────────────────────────────────

if [[ "$CHECK_FREEZE" == "true" ]]; then
  if [[ -f "$FREEZE_FILE" ]]; then
    log "Repository is FROZEN. Contents:"
    cat "$FREEZE_FILE"
    log "Critical changes are LOCKED. Unfreeze with: bash scripts/create-release-tag.sh --unfreeze"
    exit 1
  else
    log "Repository is NOT frozen. Critical changes are allowed."
    exit 0
  fi
fi

# ── --unfreeze ───────────────────────────────────────────────

if [[ "$DO_UNFREEZE" == "true" ]]; then
  if [[ -f "$FREEZE_FILE" ]]; then
    run rm -f "$FREEZE_FILE"
    log "Freeze file removed. Repository is now UNFROZEN."
  else
    log "No freeze file found – repository was already unfrozen."
  fi
  exit 0
fi

# ── Preflight checks ─────────────────────────────────────────

log "Starting release tag process..."
log "Repository root: ${REPO_ROOT}"

if [[ "$SKIP_CHECKS" == "false" ]]; then
  log "Running preflight checks..."

  # 1) Ensure working tree is clean
  cd "$REPO_ROOT"
  if [[ -n "$(git status --porcelain)" ]]; then
    err "Working tree is not clean. Commit or stash all changes before tagging."
    err "Dirty files:"
    git status --porcelain
    exit 1
  fi
  log "  ✅  Working tree is clean"

  # 2) Ensure we're on main (or a release branch)
  CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
  if [[ "$CURRENT_BRANCH" != "main" && "$CURRENT_BRANCH" != "master" && ! "$CURRENT_BRANCH" =~ ^release/ ]]; then
    warn "Current branch is '${CURRENT_BRANCH}' (not main/master/release/*). Proceed with care."
  else
    log "  ✅  On branch: ${CURRENT_BRANCH}"
  fi

  # 3) Check for FREEZE file – warn if already frozen
  if [[ -f "$FREEZE_FILE" ]]; then
    warn "Repository is already FROZEN. Re-tagging from a frozen state."
    cat "$FREEZE_FILE"
  fi

  log "Preflight checks passed."
else
  warn "Skipping preflight checks (--skip-checks)"
fi

# ── Determine version ─────────────────────────────────────────

cd "$REPO_ROOT"

if [[ -n "$EXPLICIT_VERSION" ]]; then
  NEW_TAG="$EXPLICIT_VERSION"
  # Ensure tag starts with prefix
  if [[ ! "$NEW_TAG" =~ ^v ]]; then
    NEW_TAG="${TAG_PREFIX}${NEW_TAG}"
  fi
else
  # Auto-increment latest semver tag
  LATEST_TAG="$(git tag --list "${TAG_PREFIX}*" --sort=-version:refname | head -n1 || true)"
  if [[ -z "$LATEST_TAG" ]]; then
    NEW_TAG="${TAG_PREFIX}1.0.0"
    log "No existing tags found. Starting at ${NEW_TAG}"
  else
    log "Latest tag: ${LATEST_TAG}"
    # Strip prefix and increment patch
    VERSION="${LATEST_TAG#${TAG_PREFIX}}"
    IFS='.' read -r MAJOR MINOR PATCH <<< "$VERSION"
    PATCH=$(( PATCH + 1 ))
    NEW_TAG="${TAG_PREFIX}${MAJOR}.${MINOR}.${PATCH}"
  fi
fi

log "New tag will be: ${NEW_TAG}"

# ── Check tag does not already exist ─────────────────────────

if git rev-parse "$NEW_TAG" >/dev/null 2>&1; then
  err "Tag '${NEW_TAG}' already exists!"
  err "Use --version to specify a different version or delete the existing tag."
  exit 1
fi

# ── Build tag message ─────────────────────────────────────────

COMMIT_SHA="$(git rev-parse --short HEAD)"
TIMESTAMP="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
BRANCH="$(git rev-parse --abbrev-ref HEAD)"

if [[ -z "$RELEASE_NOTES" ]]; then
  RELEASE_NOTES="Release ${NEW_TAG} – poradnik.pro-final"
fi

TAG_MESSAGE="$(cat <<EOF
${RELEASE_NOTES}

Tag:       ${NEW_TAG}
Commit:    ${COMMIT_SHA}
Branch:    ${BRANCH}
Timestamp: ${TIMESTAMP}

Release criteria:
  - All P0/P1/P2 tasks DONE
  - Nightly quality pipeline green
  - Smoke + integration + unit + load tests passing
  - Release runbook executed (docs/implementation/release-runbook.md)

EOF
)"

# ── Create the annotated tag ──────────────────────────────────

log "Creating annotated tag ${NEW_TAG} on commit ${COMMIT_SHA}..."
run git tag -a "$NEW_TAG" -m "$TAG_MESSAGE"
log "  ✅  Tag created: ${NEW_TAG}"

# ── Write FREEZE file ─────────────────────────────────────────

if [[ "$DO_FREEZE" == "true" ]]; then
  FREEZE_CONTENT="$(cat <<EOF
FREEZE
======
Tag:       ${NEW_TAG}
Commit:    ${COMMIT_SHA}
Branch:    ${BRANCH}
Frozen at: ${TIMESTAMP}

Critical changes are LOCKED as of tag ${NEW_TAG}.
To unfreeze: bash scripts/create-release-tag.sh --unfreeze

Frozen scope:
  - Core theme files (poradnik.pro/)
  - CI pipeline definitions (.github/workflows/)
  - Bootstrap and deploy scripts (scripts/)

Any change to these areas requires:
  1. Approval from release owner
  2. New release tag after validation
  3. Update to freeze timestamp

EOF
)"
  if [[ "$DRY_RUN" == "true" ]]; then
    echo "[DRY-RUN] Would write FREEZE file:"
    echo "$FREEZE_CONTENT"
  else
    echo "$FREEZE_CONTENT" > "$FREEZE_FILE"
    log "  ✅  FREEZE file written: ${FREEZE_FILE}"
  fi
fi

# ── Summary ───────────────────────────────────────────────────

log ""
log "═══════════════════════════════════════════════"
log "  Release Tag Created Successfully"
log "═══════════════════════════════════════════════"
log "  Tag     : ${NEW_TAG}"
log "  Commit  : ${COMMIT_SHA}"
log "  Branch  : ${BRANCH}"
log "  Frozen  : ${DO_FREEZE}"
log ""
if [[ "$DRY_RUN" == "true" ]]; then
  log "  [DRY RUN] No actual changes were made."
fi
log "  To push tag to remote (when ready):"
log "    git push origin ${NEW_TAG}"
log ""
log "  Next steps:"
log "    1. Verify tag: git show ${NEW_TAG}"
log "    2. Run post-deploy checks per release-runbook.md"
if [[ "$DO_FREEZE" == "true" ]]; then
  log "    3. Repository is FROZEN – no critical changes until unfreeze"
  log "    4. Unfreeze when ready: bash scripts/create-release-tag.sh --unfreeze"
fi
log "═══════════════════════════════════════════════"
