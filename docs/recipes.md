# Recipes

Short patterns for common tasks. See [api.md](api.md) for the full reference.

## Non-blocking key loop

```phel
(defn run []
  (loop [state (initial-state)]
    (php/usleep 16000)                   ; ~60 fps
    (recur (step state (read-key)))))
```

## Held-key responsiveness

`read-key` yields at most one event per tick; a held arrow key floods stdin
faster than that. `read-keys` drains everything pending so each queued press
applies within the same frame.

```phel
(defn run []
  (loop [state (initial-state)]
    (php/usleep 16000)
    (recur (reduce step state (read-keys)))))
```

## Flicker-free full-screen loop (diff rendering)

Repaint the back-buffer each frame; `present` writes only the cells that
changed. See [api.md](api.md#diff-rendering).

```phel
(with-screen                             ; alt screen, cursor hidden
  (with-diff (terminal-size)
    (loop [n 0]
      (clear-buffer)
      (draw-box {:x 0 :y 0 :width 40 :height 12 :border :rounded})
      (render 2 2 (str "Frame " n))
      (present)                          ; minimal repaint
      (when-not (= {:char "q"} (read-key))
        (php/usleep 16000)
        (recur (inc n))))))
```

## Pre-resolve a border for a render loop

`make-border-style` returns the underlying `BorderStyle`, and resolved
instances pass through `:border` unchanged — hoist the lookup out of the loop:

```phel
(def rounded (make-border-style :rounded))

(loop []
  (draw-box {:x 0 :y 0 :width 40 :height 12 :border rounded})
  ...)
```

## Bordered UI with colors

```phel
(add-output-formatter {:style-name "title"  :foreground "cyan"  :options ["bold"]})
(add-output-formatter {:style-name "accent" :foreground "green"})

(draw-box {:x 0 :y 0 :width 40 :height 10 :border :double})
(render 2 1 "Dashboard" "title")
(render 2 3 "online"    "accent")
```

## Paint a heatmap cell

```phel
(fill-region {:x 10 :y 4 :width 6 :height 2 :fill-char "█"})
```

## React to terminal resizes

A diff session is sized once, so track resizes and reopen it when the
dimensions change:

```phel
(defn- resize-if-needed []
  (when (not= (diff-size) (terminal-size))
    (end-diff)
    (clear-screen)                       ; outside a session this wipes the
    (begin-diff)))                       ; terminal; inside it, only the buffer

(with-screen
  (begin-diff)                           ; full-screen session
  (loop []
    (resize-if-needed)                   ; repaints from blank after a resize
    (clear-buffer)
    (draw-box {:x 0 :y 0 :width 40 :height 12})
    (present)
    (php/usleep 16000)
    (recur)))
```

`(diff-size)` returns `nil` outside a session, so the check also opens the
first one. Reacting in the render loop rather than in `on-resize` keeps the
buffer swap off the signal handler, where it could land mid-frame.

Order matters: a new session assumes the screen is blank, and inside a session
`clear-screen` blanks the back-buffer instead of the terminal. Clearing after
`begin-diff` therefore leaves the old frame's cells on screen wherever the new
one draws nothing — the borders of the previous, smaller layout survive as
artifacts.

## Query the rendered area

```phel
(let [{:keys [width height]} (max-bounds)]
  (render 0 (+ height 2) (format "Used %dx%d" (inc width) (inc height))))
```
