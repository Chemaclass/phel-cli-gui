# Phel CLI GUI

[![CI](https://github.com/Chemaclass/phel-cli-gui/actions/workflows/ci.yml/badge.svg)](https://github.com/Chemaclass/phel-cli-gui/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/chemaclass/phel-cli-gui)](https://packagist.org/packages/chemaclass/phel-cli-gui)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Build rich terminal interfaces in [Phel](https://phel-lang.org/) — render text
at arbitrary coordinates, draw framed boxes, paint regions, read raw keypresses,
and style output with named formatters.

Powered by Symfony's [Console Cursor](https://symfony.com/doc/current/console/coloring.html),
exposed behind a small, data-first Phel API.

- Works with any TTY (ANSI-capable).
- Zero globals — one managed `TerminalGui` singleton per process.
- Raw key events, one per tick (`read-key`) or drained per frame (`read-keys`) — held keys stay responsive.
- Named styles from 4-bit colours up to xterm-256 and 24-bit truecolor.
- Frame batching flushes a whole repaint in one write; diff rendering writes only the cells that changed — see [docs/api.md](docs/api.md#diff-rendering).
- Pure helpers (`parse-keys`, `color->sgr`, `make-border-style`) are easy to test without a real terminal.

## Requirements

- PHP 8.4+
- `ext-pcntl`, `ext-posix`, `ext-readline`
- Phel `^0.48`

## Install

```bash
composer require chemaclass/phel-cli-gui
```

Require the namespace in your Phel file:

```phel
(ns my-app.main
  (:require phel-cli-gui.terminal-gui :refer [render read-key draw-box clear-screen]))
```

## Quick start

Draw a bordered box, render text inside it, wait for a key, quit.

```phel
(ns my-app.hello
  (:require phel-cli-gui.terminal-gui
            :refer [clear-screen draw-box render read-key cleanup-gui]))

(defn main []
  (clear-screen)
  (draw-box {:x 2 :y 1 :width 30 :height 5 :fill-char \space})
  (render 4 3 "Press any key to exit")
  (loop []
    (if (read-key)
      (cleanup-gui)
      (do (php/usleep 10000) (recur)))))

(main)
```

Run:

```bash
vendor/bin/phel run src/phel/hello.phel
```

## Docs

- [API reference](docs/api.md) — every public function, grouped by concern.
- [Recipes](docs/recipes.md) — copy-paste patterns (diff loop, bordered UI, …).

## Example projects

- [phel-snake](https://github.com/Chemaclass/phel-snake) — classic Snake built with this library.

## Development

```bash
composer install
composer test          # Phel tests + PHPUnit (test:phel / test:php to scope)
composer test:coverage # same suites with line-coverage reports (needs xdebug/pcov)
composer format        # phel format
```

Layout: `src/phel/` public API · `src/php/` rendering core (Symfony Console
wrapper + pure helpers) · `tests/` Phel, PHPUnit & bashunit suites · `tools/`
release automation.

Cut a release with `tools/release.sh <version>` (add `--dry-run` to preview) —
it gates, rolls the CHANGELOG, tags, pushes, and publishes the GitHub release.

AI-assistant config is managed with [agnostic-ai](https://github.com/Chemaclass/agnostic-ai):
edit the source under `.agnostic-ai/`, run `agnostic-ai sync`; the per-tool
files (`CLAUDE.md`, `.claude/…`) are generated and git-ignored.

## License

MIT © [Jose M Valera Reales](https://chemaclass.com)
