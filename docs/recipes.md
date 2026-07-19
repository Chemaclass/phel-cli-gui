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

## Query the rendered area

```phel
(let [{:keys [width height]} (max-bounds)]
  (render 0 (+ height 2) (format "Used %dx%d" (inc width) (inc height))))
```
