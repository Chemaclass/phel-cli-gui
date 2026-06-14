#!/usr/bin/env bash
#
# Pure, side-effect-free helpers for tools/release.sh — split out so they can be
# unit-tested with bashunit. Nothing here touches git, composer, gh or the
# network: each function takes its inputs and emits to stdout / returns a status.

# Validate an unprefixed semantic version, e.g. 0.12.0 or 0.12.0-rc.1.
# Returns 0 when valid, 1 otherwise (a leading 'v' is rejected — tags here are
# unprefixed).
release_validate_version() {
  local version="$1"
  case "$version" in
    v*) return 1 ;;
  esac
  printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$'
}

# Extract the compare-URL base from an
# `[Unreleased]: https://…/compare/PREV...HEAD` link line.
release_base_url() {
  local url="${1#*: }"
  printf '%s' "${url%/compare/*}"
}

# Extract the previous version (PREV) from the same link line.
release_prev_version() {
  local url="${1#*: }" tail
  tail="${url##*/compare/}"
  printf '%s' "${tail%...HEAD}"
}

# Emit the CHANGELOG (read from file $1) with a dated version header inserted
# right after `## [Unreleased]` and the link references updated.
release_roll_changelog() {
  local file="$1" ver="$2" date="$3" prev="$4" base="$5"
  awk -v ver="$ver" -v date="$date" -v prev="$prev" -v base="$base" '
    /^## \[Unreleased\]$/ { print; print ""; print "## [" ver "] - " date; next }
    /^\[Unreleased\]:/ {
      print "[Unreleased]: " base "/compare/" ver "...HEAD"
      print "[" ver "]: " base "/compare/" prev "..." ver
      next
    }
    { print }
  ' "$file"
}

# Emit just the notes under the `## [VER]` header of file $1, with leading blank
# lines trimmed (used as the GitHub release body).
release_extract_notes() {
  local file="$1" ver="$2"
  awk -v ver="$ver" '
    $0 ~ ("^## \\[" ver "\\]") {f=1; next}
    f && /^## \[/ {f=0}
    f {print}
  ' "$file" | awk 'NF{p=1} p'
}

# Return 0 when the [Unreleased] section of file $1 has at least one bullet or
# `###` subsection (i.e. there is something to release).
release_unreleased_has_entries() {
  awk '/^## \[Unreleased\]$/{f=1;next} /^## \[/{f=0} f' "$1" \
    | grep -Eq '^(- |### )'
}
