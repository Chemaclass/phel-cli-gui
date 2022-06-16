# Phel Cli Gui

This library provides you with some [Phel](https://phel-lang.org/) functions to paint in the terminal.
It uses the Cursor from the Symfony Command module.

### Example

```phel
(ns phel-cli-gui\example
  (:require phel-cli-gui\terminal-gui :refer [paint]))

(paint 1 1 "1,1")
(paint 2 2 "2,2")
(paint 3 3 "3,3")

(println)
```

## WIP
