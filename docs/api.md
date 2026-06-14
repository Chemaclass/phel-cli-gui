# API reference

All public functions live in `phel-cli-gui.terminal-gui`.

## Input

| Function | Purpose |
|---|---|
| `(read-input length)` | Read up to `length` bytes from stdin. Returns `{:raw s :hex h}`. |
| `(read-key)` | Read one keypress. Returns a keyword or `{:char c}` or `nil` when no input. |
| `(parse-key input)` | Pure helper: map a `read-input` result to a keyword / `{:char c}`. |
| `(read-available)` / `(read-available max-bytes)` | Drain all pending input bytes in one read (raw string, `""` when idle). For held-key responsiveness. |

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
| `(move-cursor x y)` | Move the caret to an absolute `(x, y)` without drawing. |
| `(cursor-home)` | Move the caret to the origin `(0, 0)`. |

> Cursor is hidden automatically on init; call `(cleanup-gui)` (or let the
> shutdown handler fire) to restore it.

Pair `(cursor-home)` with overwriting renders to repaint a frame in place —
no flickering full `(clear-screen)` each tick.

## Full-screen

| Function | Effect |
|---|---|
| `(enter-alt-screen)` | Switch to the alternate screen buffer (scrollback preserved). Idempotent. |
| `(leave-alt-screen)` | Return to the normal screen, restoring prior content. |
| `(with-screen & body)` | Macro: run `body` on the alt screen with the cursor hidden, restoring both on exit (even on throw). |

The alternate screen is also left automatically on `(cleanup-gui)` / shutdown,
so a crash never strands the user on a blank page.

```phel
(with-screen
  (loop []
    (with-frame
      (cursor-home)
      (draw-box {:x 0 :y 0 :width 40 :height 12})
      (render 2 2 "Press q to quit"))
    (when-not (= {:char "q"} (read-key))
      (php/usleep 16000)
      (recur))))
```

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
| `(draw-box {:x :y :width :height :border <preset-or-map> :fill-char})` | Framed box with optional fill + border (preset keyword or char map). |

`:fill-char` and border chars default to single-byte ASCII (`" "`, `-`, `|`, `+`).
Multibyte characters (`─`, `│`, `┼`) are supported.

### Border presets & distinct corners

The `:border` of `draw-box`/`render-board` accepts more than a single shared
corner:

- A **preset keyword** — `:ascii` (default), `:light`, `:rounded`, `:heavy`,
  `:double`:

  ```phel
  (draw-box {:x 0 :y 0 :width 20 :height 6 :border :rounded})
  ```

  | Preset | Glyphs |
  |---|---|
  | `:ascii` | `- \| + + + +` |
  | `:light` | `─ │ ┌ ┐ └ ┘` |
  | `:rounded` | `─ │ ╭ ╮ ╰ ╯` |
  | `:heavy` | `━ ┃ ┏ ┓ ┗ ┛` |
  | `:double` | `═ ║ ╔ ╗ ╚ ╝` |

- A **map** — `{:horizontal :vertical :corner}` for a shared corner,
  `:top-left`/`:top-right`/`:bottom-left`/`:bottom-right` for distinct corners,
  or `:preset` to start from a named set.

## Frame batching

By default every draw call writes to stdout immediately — one `write` per call.
For draw-heavy redraws (e.g. a game loop repainting a board each tick), wrap the
draws in a frame so they accumulate and flush in a **single** write.

| Function | Effect |
|---|---|
| `(begin-frame)` | Start buffering draws. Nestable — only the outermost `end-frame` flushes. |
| `(end-frame)` | Flush the buffered frame in one write. No-op when no frame is open. |
| `(with-frame & body)` | Macro: run `body` inside a frame, flushing once on completion (even on throw). |

```phel
(with-frame
  (clear-screen)
  (draw-box {:x 0 :y 0 :width 20 :height 8})
  (render 2 2 "Score: 42"))
```

Output is byte-identical to immediate mode; only the number of writes changes.

## Diff rendering

A frame batches a repaint into one write, but still rewrites **every** cell.
A diff session keeps a virtual screen and writes only the cells that changed
since the previous frame — the minimal repaint a flicker-free TUI wants. Draw
with the normal verbs; they paint into a back-buffer while a session is open.

| Function | Effect |
|---|---|
| `(begin-diff {:width w :height h})` | Open a diff session sized to the screen. Draw verbs now paint into the back-buffer. |
| `(clear-buffer)` | Reset the back-buffer to blank. Run at the top of each frame. |
| `(present)` | Diff against the last frame and write only the changed runs, in one write. |
| `(end-diff)` | Close the session and release both buffers. |
| `(with-diff {:width w :height h} & body)` | Macro: run `body` inside a session, closing it on completion (even on throw). |

```phel
(with-screen
  (with-diff (terminal-size)
    (loop [score 0]
      (clear-buffer)
      (draw-box {:x 0 :y 0 :width 20 :height 8})
      (render 2 2 (str "Score: " score))
      (present)            ; only the digits that changed hit the terminal
      (recur (inc score)))))
```

Each `present` writes only the dirty runs: changing one HUD digit on a 100×40
screen drops a ~4.3 KB full repaint to a handful of bytes. Style boundaries and
unchanged gaps split runs, so colour stays correct. Pair with `with-screen` and
`clear-screen` once at startup so the first frame paints over a clean page.

Inside a session the back-buffer *is* the screen, so the clear/cursor verbs act
on it rather than the terminal: `clear-screen` blanks the back-buffer (same as
`clear-buffer`), `clear-line` blanks that row, and `clear-output`, `move-cursor`
and `cursor-home` are no-ops (`present` owns cursor placement). `hide-cursor` /
`show-cursor` still affect the real terminal.

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

### 256-color & truecolor

For the full xterm-256 palette or 24-bit RGB, register a style with
`add-color` and use it the same way:

```phel
(add-color {:style-name "lava"  :fg-256 196 :bg-256 52 :options ["bold"]})
(add-color {:style-name "ocean" :fg-rgb [120 180 255]})

(render 0 0 "molten" "lava")
(render 0 1 "wave"   "ocean")
```

| Key | Value |
|---|---|
| `:fg-256` / `:bg-256` | xterm-256 palette index, `0`–`255`. |
| `:fg-rgb` / `:bg-rgb` | `[r g b]`, each `0`–`255` (24-bit truecolor). |
| `:options` | `"bold"` `"dim"` `"italic"` `"underline"` `"blink"` `"reverse"` `"conceal"` `"strikethrough"`. |

`(color->sgr spec)` is the pure helper underneath — it returns the raw ANSI
SGR parameter string (e.g. `"38;5;196;1"`) if you want to build sequences
yourself.

## Lifecycle

| Function | Effect |
|---|---|
| `(cleanup-gui)` | Restore cursor, terminal mode, and reset the singleton. |

Shutdown and `SIGINT` handlers are registered automatically — manual
cleanup is only necessary to tear down mid-process.
