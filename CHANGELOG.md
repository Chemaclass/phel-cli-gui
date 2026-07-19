# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Performance
- Frame flushes (`end-frame`) and diff presents (`present`) are wrapped in DEC 2026 synchronized output, so terminals that support it repaint the whole frame atomically — no tearing on full-screen updates. Unsupported terminals ignore the sequences.
- Screen-buffer rows are stored as packed byte strings (glyphs + interned style ids, multibyte glyphs in a side table): unchanged rows are rejected at memcmp speed and changed rows are XOR-scanned to their changed span before any per-cell work. The 120×40 diff-session benchmark runs ~4.4x faster per frame (0.53 → 0.12 ms; paints ~13x, diffs ~1.9x) — full-screen sessions scale the same way. Style ids are one byte, so a process supports up to 255 distinct style names (far beyond practical use).
- Diff runs absorb tiny unchanged same-style gaps (up to 4 cells) instead of splitting: rewriting a few identical cells costs fewer bytes than the cursor escape a separate run needs, so dense frames emit fewer, longer writes.
- On full-terminal-width diff sessions, a trailing unstyled blank run collapses to erase-to-EOL (`\e[K`) — 3 bytes instead of one space per blanked cell. Narrower sessions keep writing spaces, since EL would wipe terminal content beyond the session's edge.
- Heavily fragmented rows (4+ runs) are rewritten as one span split only at style boundaries when that is fewer bytes than the per-run cursor escapes — the diff picks whichever variant is smaller, so sparse rows still emit individual runs.

### Added
- `on-resize` registers a `SIGWINCH` handler that receives the new `{:width w :height h}` — reopen a diff session from it (or compare sizes in the render loop) to adapt full-screen UIs to the new terminal dimensions.
- Key parsing recognises navigation and function keys: `:home` `:end` `:page-up` `:page-down` `:insert` `:delete` and `:f1`–`:f4` (`parse-keys` matches longest sequences first; 4-byte tilde sequences need `read-keys`, as `read-key` reads 3 bytes).

## [0.13.0] - 2026-07-19

### Added
- Input: `read-keys` drains all pending input into a vector of key events (a held arrow applies its motion several times per frame) with the pure, UTF-8-aware `parse-keys` underneath.
- `make-border-style` is now public: resolve a border spec once outside a render loop; resolved `BorderStyle` instances pass through `:border` unchanged.
- Coverage tooling: `composer test:coverage` runs both suites with line-coverage reports (94.7% lines, 6/8 PHP classes at 100%), backed by a `phpunit.xml.dist` with the suite + coverage source configured.

### Changed
- **Breaking:** requires `phel-lang/phel-lang` `^0.48` (was `^0.44`); the library now compiles at optimization level 2.
- **Breaking:** rendering with a style name that was never registered throws an `InvalidArgumentException` naming the style, instead of leaking literal markup tags to the screen.

### Fixed
- Styled text keeps literal `<...>` sequences: styles are applied directly instead of round-tripping through Symfony's markup parser, so markup-looking user text survives styling verbatim.

### Performance
- Rendering hot paths are ~1.5–1.9x faster per frame (120×40 diff-session benchmark): printable-ASCII fast paths and cached `mb_*` capability checks in `Text`, clip-once branch-free cell writes in `ScreenBuffer::paint`, and `present()` building the ANSI payload as a single string without per-frame `BufferedOutput`/`Cursor` allocations or formatter overhead.

## [0.12.0] - 2026-06-14

### Added
- Border presets & distinct corners: `draw-box`/`render-board` accept a preset keyword (`:ascii`, `:light`, `:rounded`, `:heavy`, `:double`) or a map with per-corner glyphs (`:top-left`/`:top-right`/`:bottom-left`/`:bottom-right`). `BorderStyle::withCorners()` plus the preset factories back it in PHP.
- Diff rendering (`begin-diff`, `clear-buffer`, `present`, `end-diff`, `with-diff`): a double-buffered virtual screen that writes only the cells that changed since the previous frame. Changing one HUD digit on a 100×40 screen drops a ~4.3 KB full repaint to a handful of bytes.
- Frame batching (`begin-frame`, `end-frame`, `with-frame`): buffer a frame's draws into a single write.
- 256-color & truecolor styles: `add-color` (`:fg-256`/`:bg-256`/`:fg-rgb`/`:bg-rgb`/`:options`) and the pure `color->sgr` helper.
- Alternate screen: `enter-alt-screen`, `leave-alt-screen`, `with-screen` macro (also left on cleanup).
- Cursor: `move-cursor`, `cursor-home` for flicker-free overwrite-in-place repaints.
- Input: `read-available` drains all pending bytes in one read for held-key responsiveness.

### Fixed
- Diff sessions no longer desync on clear/cursor calls: inside `begin-diff`, `clear-screen` blanks the back-buffer (like `clear-buffer`), `clear-line` blanks that row, and `clear-output`/`move-cursor`/`cursor-home` are no-ops — instead of punching escapes straight to the terminal and leaving the diff baseline out of step with the real screen.
- Unstyled text is now written raw, so literal `<...>` in rendered content is emitted verbatim instead of being parsed (and swallowed) as a Symfony markup tag.

### Performance
- `parse-key`: skip fallback-map allocation on known-key hits.
- Frames coalesce the trailing cursor "park" move: a buffered frame emits one cursor move at `end-frame` instead of one per draw, trimming the redundant escape sequences from the single flush (≈39 fewer per 40-row repaint).
- Diff `present` repositions runs with relative cursor moves: adjacent runs emit no move and same-row gaps a short `\e[nC`, falling back to an absolute jump only on a row change (a row of 40 adjacent styled runs drops from 40 cursor moves to 1).
- Unstyled draws skip the output formatter's tag parse (written raw), trimming per-write overhead on background fills and plain text.

## [0.11.0] - 2026-06-14

### Changed
- Modernized `phel-config.php` to the non-deprecated `withLayout`/`withBuildConfig` builder API.
- Adopted modern Phel 0.44 syntax: dot-separated namespaces (`phel.test`,
  `Symfony.Component.Console.Terminal`, `phel-cli-gui.terminal-gui`) and the
  `php/new` interop form.

### Removed
- **Breaking:** dropped support for `phel-lang/phel-lang` `^0.34` and `^0.36`;
  the library now requires `^0.44` and PHP `>=8.4`.

## [0.10.0] - 2026-05-08

### Changed

- Widen `phel-lang/phel-lang` requirement to `^0.34 || ^0.36`. Users on PHP `>=8.4` resolve to phel-lang `0.36.x`; PHP `8.3` users stay on `0.34.x`.

## [0.9.0] - 2026-04-21

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

[Unreleased]: https://github.com/Chemaclass/phel-cli-gui/compare/0.13.0...HEAD
[0.13.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.12.0...0.13.0
[0.12.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.11.0...0.12.0
[0.11.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.10.0...0.11.0
[0.10.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.9.0...0.10.0
[0.9.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.8.0...0.9.0
[0.8.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/Chemaclass/phel-cli-gui/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/Chemaclass/phel-cli-gui/releases/tag/0.1.0
