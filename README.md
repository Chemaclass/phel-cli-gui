# Phel CLI GUI

Build rich terminal interfaces in [Phel](https://phel-lang.org/) — render text
at arbitrary coordinates, draw framed boxes, paint regions, read raw keypresses,
and style output with named formatters.

Powered by Symfony's [Console Cursor](https://symfony.com/doc/current/console/coloring.html),
exposed behind a small, data-first Phel API.

- Works with any TTY (ANSI-capable).
- Zero globals — one managed `TerminalGui` singleton per process.
- Pure helpers (`parse-key`) are easy to test without a real terminal.

---

## Requirements

- PHP 8.3+
- `ext-pcntl`, `ext-posix`, `ext-readline`
- Phel `^0.34`

## Install

```bash
composer require chemaclass/phel-cli-gui
```

Then require the namespace in your Phel file:

```phel
(ns my-app\main
  (:require phel-cli-gui\terminal-gui :refer [render read-key draw-box clear-screen]))
```

---

## Quick start

Draw a bordered box, render some text inside it, wait for a key, then quit.

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

Run it:

```bash
vendor/bin/phel run src/phel/hello.phel
```

---

## API

All public functions live in `phel-cli-gui\terminal-gui`.

### Input

| Function | Purpose |
|---|---|
| `(read-input length)` | Read up to `length` bytes from stdin. Returns `{:raw s :hex h}`. |
| `(read-key)` | Read one keypress. Returns a keyword or `{:char c}` or `nil` when no input. |
| `(parse-key input)` | Pure helper: map a `read-input` result to a keyword / `{:char c}`. |

Recognised keys: `:up` `:down` `:left` `:right` `:enter` `:escape` `:tab`
`:backspace`. Anything else becomes `{:char raw}`.

```phel
(case (read-key)
  :up        (move-up)
  :down      (move-down)
  :escape    (quit)
  {:char "q"} (quit)
  nil        (idle)
  :default)
```

### Terminal info

| Function | Returns |
|---|---|
| `(terminal-size)` | `{:width w :height h}` of the current terminal. |
| `(max-bounds)` | `{:width w :height h}` — max extent reached by renders on this instance. |

### Cursor

| Function | Effect |
|---|---|
| `(hide-cursor)` | Hide the caret. |
| `(show-cursor)` | Show the caret. |

> The cursor is hidden automatically on init; call `(cleanup-gui)` (or let the
> shutdown handler fire) to restore it.

### Clearing

| Function | Effect |
|---|---|
| `(clear-screen)` | Clear everything. |
| `(clear-output)` | Clear from cursor to end of screen. |
| `(clear-line line)` | Clear one row (0-indexed). |

### Rendering

| Function | Effect |
|---|---|
| `(render x y text)` / `(render x y text style)` | Write `text` at `(x, y)`. Optional named style. |
| `(render-text-block x y text)` / `(render-text-block x y text style)` | Write multiline `text`, one row per line. |
| `(render-board {:width w :height h})` / `(render-board dims border)` | Draw a default-styled rectangular border. |
| `(draw-horizontal-line x y length char)` / `(...style)` | Repeat `char` horizontally. |
| `(draw-vertical-line x y length char)` / `(...style)` | Repeat `char` vertically. |
| `(fill-region {:x :y :width :height :fill-char})` | Paint a rectangle with a fill character. |
| `(draw-box {:x :y :width :height :border {:horizontal :vertical :corner} :fill-char})` | Framed box with optional fill + border chars. |

`:fill-char` and border chars default to single-byte ASCII (`" "`, `-`, `|`, `+`).
Multibyte characters (`─`, `│`, `┼`) are supported.

### Styling

Register a named style once, then pass the name to any rendering call that
takes a `style` argument.

```phel
(add-output-formatter
  {:style-name  "danger"
   :foreground  "red"
   :background  nil
   :options     ["bold"]})

(render 0 0 "!! boom !!" "danger")
```

`:options` accepts Symfony output options: `"bold"`, `"underscore"`,
`"blink"`, `"reverse"`, `"conceal"`.

### Lifecycle

| Function | Effect |
|---|---|
| `(cleanup-gui)` | Restore cursor, terminal mode, and reset the singleton. |

A shutdown handler and `SIGINT` handler are registered automatically — manual
cleanup is only necessary when you want to tear down mid-process.

---

## Recipes

### Non-blocking key loop

```phel
(defn run []
  (loop [state (initial-state)]
    (php/usleep 16000)                   ; ~60 fps
    (recur (step state (read-key)))))
```

### Bordered UI with colors

```phel
(add-output-formatter {:style-name "title"  :foreground "cyan"  :options ["bold"]})
(add-output-formatter {:style-name "accent" :foreground "green"})

(draw-box {:x 0 :y 0 :width 40 :height 10
           :border {:horizontal "─" :vertical "│" :corner "┼"}})
(render 2 1 "Dashboard" "title")
(render 2 3 "online"    "accent")
```

### Paint a heatmap cell

```phel
(fill-region {:x 10 :y 4 :width 6 :height 2 :fill-char "█"})
```

### Query the rendered area

```phel
(let [{:keys [width height]} (max-bounds)]
  (render 0 (+ height 2) (format "Used %dx%d" (inc width) (inc height))))
```

---

## Example projects

- [phel-snake](https://github.com/Chemaclass/phel-snake) — classic Snake,
  built with this library.

---

## Development

```bash
composer install
composer test          # runs Phel tests + PHPUnit
composer test:phel     # Phel tests only
composer test:php      # PHPUnit only
composer format        # phel format
```

### Coverage

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit tests \
  --coverage-text --coverage-filter=src/php
```

### Layout

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

---

## License

MIT © [Jose M Valera Reales](https://chemaclass.com)
