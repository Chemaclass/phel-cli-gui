#!/usr/bin/env bash
#
# bashunit tests for the pure helpers in tools/release_lib.sh.

# shellcheck source=tools/release_lib.sh
source "$(dirname "${BASH_SOURCE[0]}")/../../tools/release_lib.sh"

UNREL_LINE="[Unreleased]: https://github.com/Chemaclass/phel-cli-gui/compare/0.11.0...HEAD"
BASE="https://github.com/Chemaclass/phel-cli-gui"

# A minimal Keep-a-Changelog fixture written to a temp file before each test.
set_up() {
  FIXTURE="$(mktemp)"
  cat > "$FIXTURE" <<'EOF'
# Changelog

## [Unreleased]

### Added
- A new thing.

### Fixed
- A bug.

## [0.11.0] - 2026-06-14

### Added
- An older thing.

[Unreleased]: https://github.com/Chemaclass/phel-cli-gui/compare/0.11.0...HEAD
[0.11.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.10.0...0.11.0
EOF
}

tear_down() {
  rm -f "$FIXTURE"
}

function test_validate_version_accepts_plain_semver() {
  release_validate_version 0.12.0
  assert_successful_code
  release_validate_version 1.2.3
  assert_successful_code
}

function test_validate_version_accepts_prerelease_suffix() {
  release_validate_version 0.12.0-rc.1
  assert_successful_code
}

function test_validate_version_rejects_v_prefix() {
  release_validate_version v1.2.3
  assert_general_error
}

function test_validate_version_rejects_too_few_segments() {
  release_validate_version 1.2
  assert_general_error
}

function test_validate_version_rejects_non_numeric() {
  release_validate_version abc
  assert_general_error
}

function test_validate_version_rejects_four_segments() {
  release_validate_version 1.2.3.4
  assert_general_error
}

function test_base_url_is_parsed_from_unreleased_link() {
  assert_equals "$BASE" "$(release_base_url "$UNREL_LINE")"
}

function test_prev_version_is_parsed_from_unreleased_link() {
  assert_equals "0.11.0" "$(release_prev_version "$UNREL_LINE")"
}

function test_roll_changelog_inserts_dated_version_header() {
  local out
  out="$(release_roll_changelog "$FIXTURE" 0.12.0 2026-01-31 0.11.0 "$BASE")"
  assert_contains "## [0.12.0] - 2026-01-31" "$out"
  # The empty Unreleased section is preserved above the new version.
  assert_contains "## [Unreleased]" "$out"
}

function test_roll_changelog_updates_link_references() {
  local out
  out="$(release_roll_changelog "$FIXTURE" 0.12.0 2026-01-31 0.11.0 "$BASE")"
  assert_contains "[Unreleased]: $BASE/compare/0.12.0...HEAD" "$out"
  assert_contains "[0.12.0]: $BASE/compare/0.11.0...0.12.0" "$out"
  # The previous version's link must remain untouched.
  assert_contains "[0.11.0]: $BASE/compare/0.10.0...0.11.0" "$out"
}

function test_extract_notes_returns_only_that_version_section() {
  local rolled notes
  rolled="$(mktemp)"
  release_roll_changelog "$FIXTURE" 0.12.0 2026-01-31 0.11.0 "$BASE" > "$rolled"
  notes="$(release_extract_notes "$rolled" 0.12.0)"
  rm -f "$rolled"

  assert_contains "A new thing." "$notes"
  assert_contains "A bug." "$notes"
  assert_not_contains "An older thing." "$notes"
}

function test_extract_notes_trims_leading_blank_lines() {
  local rolled notes first
  rolled="$(mktemp)"
  release_roll_changelog "$FIXTURE" 0.12.0 2026-01-31 0.11.0 "$BASE" > "$rolled"
  notes="$(release_extract_notes "$rolled" 0.12.0)"
  rm -f "$rolled"

  first="$(printf '%s\n' "$notes" | head -n 1)"
  assert_equals "### Added" "$first"
}

function test_unreleased_has_entries_true_for_populated_section() {
  release_unreleased_has_entries "$FIXTURE"
  assert_successful_code
}

function test_unreleased_has_entries_false_for_empty_section() {
  local empty
  empty="$(mktemp)"
  cat > "$empty" <<'EOF'
# Changelog

## [Unreleased]

## [0.11.0] - 2026-06-14

### Added
- An older thing.
EOF
  release_unreleased_has_entries "$empty"
  local code=$?
  rm -f "$empty"
  assert_general_error "" "" "$code"
}
