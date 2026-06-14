#!/usr/bin/env bash
#
# Release helper for chemaclass/phel-cli-gui.
#
# Fully automates a release so no manual steps (or AI) are needed:
#   preconditions -> quality gate -> roll the CHANGELOG -> commit
#   -> annotated/signed tag -> push -> GitHub release -> verify.
#
# Usage:
#   tools/release.sh <version> [--dry-run]
#
#   <version>   semantic version WITHOUT a leading 'v' — tags here are
#               unprefixed, e.g. 0.12.0  (also accepts pre-release suffixes
#               like 0.12.0-rc.1)
#   --dry-run   run every check and show the CHANGELOG diff + release notes,
#               but make no commit, tag, push or GitHub release
#
# Requires: git, composer, gh (authenticated), awk. Run it from a clean,
# up-to-date local `main`.

set -euo pipefail

usage() { echo "Usage: tools/release.sh <version> [--dry-run]"; }

die() { echo "error: $*" >&2; exit 1; }

DRY_RUN=0
VERSION=""
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    -h|--help) usage; exit 0 ;;
    -*) die "unknown option: $arg" ;;
    *) [ -z "$VERSION" ] || die "unexpected argument: $arg"; VERSION="$arg" ;;
  esac
done

[ -n "$VERSION" ] || { usage; exit 1; }

case "$VERSION" in
  v*) die "use an unprefixed version (e.g. 0.12.0), not '$VERSION'" ;;
esac
echo "$VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.]+)?$' \
  || die "not a valid version: $VERSION"

cd "$(git rev-parse --show-toplevel)"

# --- Preconditions -----------------------------------------------------------
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
[ "$BRANCH" = "main" ] || die "releases run from 'main' (currently on '$BRANCH')"
[ -z "$(git status --porcelain)" ] || die "working tree is not clean — commit or stash first"

git fetch --quiet origin
[ "$(git rev-parse @)" = "$(git rev-parse '@{u}')" ] \
  || die "local main is not in sync with origin/main — pull/push first"

if git rev-parse -q --verify "refs/tags/$VERSION" >/dev/null; then
  die "tag $VERSION already exists"
fi

CHANGELOG="CHANGELOG.md"
[ -f "$CHANGELOG" ] || die "$CHANGELOG not found"
grep -q '^## \[Unreleased\]$' "$CHANGELOG" || die "no '## [Unreleased]' section in $CHANGELOG"

# Derive the repo compare-URL base and the previous version from the existing
# [Unreleased] link reference, so nothing is hardcoded.
UNREL_LINE="$(grep -E '^\[Unreleased\]:' "$CHANGELOG" || true)"
[ -n "$UNREL_LINE" ] || die "no '[Unreleased]:' link reference at the bottom of $CHANGELOG"
COMPARE_URL="${UNREL_LINE#*: }"          # https://…/compare/0.11.0...HEAD
BASE_URL="${COMPARE_URL%/compare/*}"     # https://…/phel-cli-gui
PREV="${COMPARE_URL##*/compare/}"; PREV="${PREV%...HEAD}"
[ -n "$BASE_URL" ] && [ -n "$PREV" ] || die "could not parse base URL / previous version from $CHANGELOG"

# Refuse an empty release: there must be bullets under [Unreleased].
UNREL_BODY="$(awk '/^## \[Unreleased\]$/{f=1;next} /^## \[/{f=0} f' "$CHANGELOG" | grep -E '^(- |### )' || true)"
[ -n "$UNREL_BODY" ] || die "the [Unreleased] section is empty — nothing to release"

DATE="$(date +%F)"

echo "Releasing $VERSION (previous: $PREV) dated $DATE"
[ "$DRY_RUN" = 1 ] && echo "(dry run — no commit/tag/push/release)"

# --- Quality gate ------------------------------------------------------------
echo "==> composer build";    composer build
echo "==> composer test";     composer test
echo "==> composer validate"; composer validate --strict
echo "==> composer format (must be a no-op for a release)"; composer format
if [ -n "$(git status --porcelain)" ]; then
  git checkout -- .
  die "formatting changed files — run 'composer format' and commit it first"
fi

# --- Roll the CHANGELOG ------------------------------------------------------
# Insert a dated version header right after [Unreleased] (the existing bullets
# become that version's notes), and update the link references.
TMP="$(mktemp)"
awk -v ver="$VERSION" -v date="$DATE" -v prev="$PREV" -v base="$BASE_URL" '
  /^## \[Unreleased\]$/ {
    print
    print ""
    print "## [" ver "] - " date
    next
  }
  /^\[Unreleased\]:/ {
    print "[Unreleased]: " base "/compare/" ver "...HEAD"
    print "[" ver "]: " base "/compare/" prev "..." ver
    next
  }
  { print }
' "$CHANGELOG" > "$TMP"
mv "$TMP" "$CHANGELOG"

# Extract just this version's notes for the GitHub release body.
NOTES="$(awk -v ver="$VERSION" '
  $0 ~ ("^## \\[" ver "\\]") {f=1; next}
  f && /^## \[/ {f=0}
  f {print}
' "$CHANGELOG" | awk 'NF{p=1} p' )"

if [ "$DRY_RUN" = 1 ]; then
  echo "----- CHANGELOG diff -----"
  git --no-pager diff -- "$CHANGELOG" || true
  echo "----- GitHub release notes -----"
  printf '%s\n' "$NOTES"
  git checkout -- "$CHANGELOG"
  echo "Dry run complete — nothing committed."
  exit 0
fi

# --- Commit, tag, push, release ---------------------------------------------
git add "$CHANGELOG"
git commit -m "chore(release): $VERSION"

# Sign the tag when a signing key is configured; fall back to a plain
# annotated tag otherwise (matches the existing annotated tags either way).
if [ -n "$(git config --get user.signingkey || true)" ]; then
  git tag -s "$VERSION" -m "$VERSION"
else
  git tag -a "$VERSION" -m "$VERSION"
fi

git push origin "$BRANCH"
git push origin "$VERSION"

printf '%s\n' "$NOTES" | gh release create "$VERSION" --title "$VERSION" --notes-file -

echo "==> Verifying"
gh release view "$VERSION" --json tagName,name,url \
  --jq '"Released " + .name + " -> " + .url'
echo "Done."
