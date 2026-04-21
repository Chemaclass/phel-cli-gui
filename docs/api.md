# API reference

All public functions live in `phel-cli-gui\terminal-gui`.

## Input

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

## Terminal info

| Function | Returns |
|---|---|
| `(terminal-size)` | `{:width w :height h}` of the current terminal. |
| `(max-bounds)` | `{:width w :height h}` — max extent reached by renders on this instance. |

## Cursor

| Function | Effect |
|---|---|
| `(hide-cursor)` | Hide the caret. |
| `(show-cursor)` | Show the caret. |

> Cursor is hidden automatically on init; call `(cleanup-gui)` (or let the
> shutdown handler fire) to restore it.

## Clearing

| Function | Effect |
|---|---|
| `(clear-screen)` | Clear everything. |
| `(clear-output)` | Clear from cursor to end of screen. |
| `(clear-line line)` | Clear one row (0-indexed). |

## Rendering

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

## Styling

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

## Lifecycle

| Function | Effect |
|---|---|
| `(cleanup-gui)` | Restore cursor, terminal mode, and reset the singleton. |

Shutdown and `SIGINT` handlers are registered automatically — manual
cleanup is only necessary to tear down mid-process.
