# Phel Cli Gui

This library provides you with some [Phel](https://phel-lang.org/) functions to render in the terminal.
It uses the Cursor from the Symfony Command module.

### Example

```phel
(ns phel-cli-gui\example
  (:require phel-cli-gui\terminal-gui :refer [clear-screen board render]))

(clear-screen)
(board 40 20)

(render 1 1 "1,1")
(render 2 2 "2,2")
(render 3 3 "3,3")
```

## WIP
