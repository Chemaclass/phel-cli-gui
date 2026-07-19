---
name: project-conventions
description: Build, test and language conventions for the phel-cli-gui library.
globs: "**/*"
alwaysApply: true
---

`phel-cli-gui` is a terminal-rendering library written in [Phel](https://phel-lang.org/)
(a Lisp that compiles to PHP) over Symfony Console's `Cursor`.

## Layout

- `src/phel/terminal-gui.phel` — the public Phel API.
- `src/php/` — PHP classes (`PhelCliGui\` PSR-4): `TerminalGui` (Symfony wrapper + singleton facade) delegating to the `FrameSession` (frame batching) and `DiffSession` (cell diffing over `ScreenBuffer`) collaborators, plus pure helpers `TerminalCanvas`, `BorderStyle`, `Text`, `AnsiStyle`.
- `tests/phel/` — pure-logic Phel tests; `tests/php/` — PHPUnit tests that exercise rendering via `BufferedOutput`.

## Toolchain

- PHP `>=8.4`, `phel-lang/phel-lang` `^0.48` only.
- Build: `composer build`. Test: `composer test` (Phel + PHPUnit). Format: `composer format`.
- Run all three plus `composer validate --strict` before opening a PR; CI gates pushes on PHP 8.4 and 8.5.

## Phel style (0.48)

- Dot-separated namespaces — `phel.test`, `Symfony.Component.Console.Terminal`, `phel-cli-gui.terminal-gui` (not backslash).
- Use the `php/new` interop form, not bare `new`.
- Keep GUI side effects in PHP; cover pure helpers (`parse-key`, `color->sgr`) with Phel unit tests.

## Commits

- Conventional Commits; use `ref:` (not `refactor:`). No tooling/attribution trailers in messages.
