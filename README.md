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
