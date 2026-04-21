# Phel CLI GUI

Build rich terminal interfaces in [Phel](https://phel-lang.org/) — render text
at arbitrary coordinates, draw framed boxes, paint regions, read raw keypresses,
and style output with named formatters.

Powered by Symfony's [Console Cursor](https://symfony.com/doc/current/console/coloring.html),
exposed behind a small, data-first Phel API.

- Works with any TTY (ANSI-capable).
- Zero globals — one managed `TerminalGui` singleton per process.
- Pure helpers (`parse-key`) are easy to test without a real terminal.

## Requirements

- PHP 8.3+
- `ext-pcntl`, `ext-posix`, `ext-readline`
- Phel `^0.34`

## Install

```bash
composer require chemaclass/phel-cli-gui
```

Require the namespace in your Phel file:

```phel
(ns my-app\main
  (:require phel-cli-gui\terminal-gui :refer [render read-key draw-box clear-screen]))
```

## Quick start

Draw a bordered box, render text inside it, wait for a key, quit.

```phel
(ns my-app\hello
  (:require phel-cli-gui\terminal-gui
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
- [Recipes](docs/recipes.md) — copy-paste patterns for common UIs.

## Example projects

- [phel-snake](https://github.com/Chemaclass/phel-snake) — classic Snake built with this library.

## Development

```bash
composer install
composer test          # Phel tests + PHPUnit
composer test:phel     # Phel tests only
composer test:php      # PHPUnit only
composer format        # phel format
```

Coverage:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit tests \
  --coverage-text --coverage-filter=src/php
```

Layout:

```
src/
  phel/terminal-gui.phel    Public Phel API
  php/
    TerminalGui.php         Symfony Console wrapper + singleton
    TerminalCanvas.php      Box / line geometry (pure)
    BorderStyle.php         Border-character value object (pure)
    Text.php                First-char / display-width helpers (pure)
tests/
  phel/                     Phel pure-logic tests
  php/                      PHPUnit tests exercising the rendering layer
```

## License

MIT © [Jose M Valera Reales](https://chemaclass.com)
