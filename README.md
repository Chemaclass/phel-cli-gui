# Phel Cli Gui

This library provides you with some [Phel](https://phel-lang.org/) functions to render in the terminal.
It uses the Cursor from the Symfony Command module.

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

### Examples

You can see some real examples using this library:

- https://github.com/Chemaclass/phel-snake
