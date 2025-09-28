# Phel Cli Gui

This library provides you with some [Phel](https://phel-lang.org/) functions to render in the terminal.
It uses the Cursor from the Symfony Command module.

### Functions

- `(read-input [length])`: reads the input stream and returns it in different formats; `:raw` and `:hex`.
- `(clear-screen)`: clears the entire screen.
- `(clear-output)`: clears all the output from the cursors' current position to the end of the screen.
- `(clear-line [line])`: clears the output from the line.
- `(render-board [{:width w :height h}])`: renders the borders of a board.
- `(render [x y text & [style]])`: render any text to a concrete position (x,y) in the terminal.
- `(render-text-block [x y text & [style]])`: render multiline strings without managing line offsets manually.
- `(draw-horizontal-line [x y length char & [style]])`: quickly draw separators or rulers.
- `(draw-vertical-line [x y length char & [style]])`: draw vertical guides and columns.
- `(draw-box [{:x x :y y :width w :height h :border {:horizontal h :vertical v :corner c} :fill-char f}])`: draw framed areas with optional fill character.

### Example

This example will read the input from the keyboard and display the char and its hexadecimal value on the terminal.
You can run it locally using: `vendor/bin/phel run src/phel/test-keyboard.phel`

Source:

```phel
(ns phel-cli-gui\test-keyboard
  (:require phel-cli-gui\terminal-gui :refer [read-input render]))

(defn render-input [{:raw raw :hex hex}]
  (if (> (php/strlen hex) 0)
    (println (format "# Raw input: `%s`, hex: `%s`" raw hex))))

(defn main
  "Display the key and its hexadecimal value on the fly"
  []
  (println "Type something...")
  (loop []
    (php/usleep 1000)
    (let [input (read-input 3)]
      (render-input input)
      (recur))))

(main)
```

#### Examples

You can see some real examples using this library:

- https://github.com/Chemaclass/phel-snake

### Development

- Install dependencies with `composer install`.
- Run the full test-suite with `composer test` (runs both the Phel and PHP checks).
