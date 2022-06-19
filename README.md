# Phel Cli Gui

This library provides you with some [Phel](https://phel-lang.org/) functions to render in the terminal.
It uses the Cursor from the Symfony Command module.

### Example

```phel
(ns your-namespace\example
  (:require phel-cli-gui\terminal-gui :refer [clear-screen render-board render]))

(clear-screen)
(render-board 40 20)

(render 1 1 "1,1")
(render 2 2 "2,2")
(render 3 3 "3,3")
```

### Examples

You can see some real examples using this library:

- https://github.com/Chemaclass/phel-snake
