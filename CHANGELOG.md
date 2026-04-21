# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Changed

- Bump `phel-lang/phel-lang` requirement to `^0.34`.
- Adopt phel 0.34 interop shorthands across the library.
- Simplify `phel-config.php`.
- Rewrite `README` as a DX-focused API reference.

### Fixed

- Prevent stray ANSI sequences (`\e[?25h\e[?0c`) from leaking to the real STDOUT during the singleton test. Some terminals replied with a DA response (`\e[?6c`) that surfaced as a `6c` fragment in the shell prompt after `composer test`.

### Tests

- Expand coverage for rendering, formatter, and `Text` helpers.

## [0.8.0] - 2026-04-13

> Helpful rendering primitives + phel 0.32 adoption + correctness fixes for bottom-row scroll and wide-char width.

### Added

- `(terminal-size)` — current terminal dimensions via `Symfony\Console\Terminal`.
- `(fill-region {:x :y :width :height :fill-char})` — paint an area without a border.
- `(max-bounds)` — expose the instance's render/draw extent.
- `(hide-cursor)` / `(show-cursor)` — toggle cursor visibility after init.
- `(read-key)` — read one key press; returns a semantic keyword or `{:char c}`.
- `(parse-key input-map)` — pure helper mapping a `read-input` result to `:up` `:down` `:left` `:right` `:enter` `:escape` `:tab` `:backspace` or `{:char c}`.

### Changed

- **Require `phel-lang/phel-lang: ^0.32`** (was `^0.29`).
- Adopt phel 0.32 syntax across the library: `:keys` + `:or` destructuring, `\space` literal, multi-arity `defn` in place of `& [style]`, `{:example …}` / `{:see-also …}` metadata on every public function.
- Internal `gui-call` macro removes 10× repetition of `(php/-> (get-gui) …)`.
- Extract shared `PhelCliGui\Text` helper (`firstChar` + `mb_strwidth`-based `displayWidth`); removes duplicated `firstCharacter` from `BorderStyle` and `TerminalCanvas`.
- `TerminalGui::withStream(...)` now accepts `OutputInterface` (was `ConsoleOutputInterface`, too narrow for `BufferedOutput`-based tests).
- Reorganise README function docs by concern (input / terminal info / cursor / clearing / rendering).

### Fixed

- Remove stray `PHP_EOL` after every draw that caused scroll artifacts at the bottom of the terminal.
- `drawVerticalLine` batches moves/writes: 1 finalize + 1 bounds update instead of N× through `render()`.
- `renderTextBlock` batched likewise.
- Display-width tracking now uses `mb_strwidth` — correct column count for CJK, emoji, and other wide characters (was `mb_strlen`).

### Tests

- 21 PHPUnit tests (11 new) using `BufferedOutput` to assert rendered ANSI sequences + text for `fillRegion`, cursor toggles, bounds tracking, `render`, `renderTextBlock`, `drawVerticalLine`, `renderBoard`.
- 29 phel tests (18 new), including 6 unit tests covering `parse-key` behaviour for every key in the set.

## [0.7.0] - 2026-02-01

### Changed

- Require `phel-lang/phel-lang: ^0.29`.

## [0.6.0] - 2025-10-05

### Changed

- Require `phel-lang/phel-lang: ^0.23`.

## [0.5.0] - 2025-09-29

### Added

- `(render-text-block [x y text & [style]])` — render multiline strings without managing line offsets manually.
- `(draw-horizontal-line [x y length char & [style]])` — quickly draw separators or rulers.
- `(draw-vertical-line [x y length char & [style]])` — draw vertical guides and columns.
- `(draw-box [{:x x :y y :width w :height h :border {:horizontal h :vertical v :corner c} :fill-char f}])` — draw framed areas with optional fill character.

### Changed

- Require `phel-lang/phel-lang: ^0.22` and `php: >=8.3`.

### Fixed

- `cleanUp()` no longer clears the entire terminal on `__destruct`.

## [0.4.0] - 2024-12-02

### Changed

- Support `phel-lang: ^0.16`.

## [0.3.0] - 2024-06-22

### Changed

- Support `phel-lang >= 0.15`.

## [0.2.0] - 2024-04-17

### Changed

- Support PHP `^8.2` and Phel `^0.13`.

## [0.1.0] - 2024-04-17

### Added

- Initial release. Support PHP `^8.0` and Phel `^0.10`.

[Unreleased]: https://github.com/Chemaclass/phel-cli-gui/compare/0.8.0...HEAD
[0.8.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/Chemaclass/phel-cli-gui/releases/tag/0.1.0
