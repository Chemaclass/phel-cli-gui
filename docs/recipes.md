# Recipes

Short patterns for common tasks. See [api.md](api.md) for the full reference.

## Non-blocking key loop

```phel
(defn run []
  (loop [state (initial-state)]
    (php/usleep 16000)                   ; ~60 fps
    (recur (step state (read-key)))))
```

## Bordered UI with colors

```phel
(add-output-formatter {:style-name "title"  :foreground "cyan"  :options ["bold"]})
(add-output-formatter {:style-name "accent" :foreground "green"})

(draw-box {:x 0 :y 0 :width 40 :height 10
           :border {:horizontal "─" :vertical "│" :corner "┼"}})
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
