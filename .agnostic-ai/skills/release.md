---
name: release
description: Cut a new tagged release of phel-cli-gui with tools/release.sh — pick the SemVer bump, roll the CHANGELOG, tag, push, and publish the GitHub release. Use when asked to "release", "cut a release", "tag a version", or "publish".
---

# Release

Releases are fully automated by `tools/release.sh`. Do **not** hand-roll the
tag, CHANGELOG edits, or `gh release create` — run the script.

## 1. Pick the version

Read the `## [Unreleased]` section of `CHANGELOG.md` and choose the next
SemVer (unprefixed — tags here are `0.12.0`, not `v0.12.0`):

- Breaking `### Removed` / `### Changed` → **minor** while pre-1.0 (would be
  major after 1.0).
- New `### Added` → **minor**.
- Only `### Fixed` / `### Performance` → **patch**.

## 2. Run it

Preview first, then release for real:

```bash
tools/release.sh <version> --dry-run   # gate + CHANGELOG diff + notes, no writes
tools/release.sh <version>             # the real release
```

The script verifies a clean, in-sync `main`; runs the quality gate
(`composer build` / `test` / `validate` / `format`); rolls `[Unreleased]` into a
dated version section and updates the compare-link references; commits
`chore(release): <version>`; creates a signed (or annotated) tag; pushes `main`
and the tag; then runs `gh release create` with that version's CHANGELOG notes.

## Preconditions (the script enforces these — fix before rerunning)

- On `main`, clean working tree, in sync with `origin/main`.
- `gh` authenticated. A `user.signingkey` configured → the tag is GPG-signed.
- The `[Unreleased]` section is non-empty (nothing to release otherwise).

## 3. Verify the published artifact

```bash
gh release view <version> --web
git tag -v <version>          # confirm the signature
```

Packagist picks up the new git tag automatically — there is no phar/npm step.

## Notes

- The script's pure helpers live in `tools/release_lib.sh` and are covered by
  bashunit tests in `tests/bash/` (run in CI). If you change the release flow,
  update those tests.
- Conventional Commits, `ref:` not `refactor:`, no attribution trailers.
