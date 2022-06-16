<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\BufferedOutput;

final class TerminalGui
{
    private BufferedOutput $output;

    private Cursor $cursor;
    private int $width = 0;
    private int $height = 0;

    public function __construct()
    {
        $this->output = new BufferedOutput();
        $this->cursor = new Cursor($this->output);
    }

    public function clearScreen(): void
    {
        $this->cursor->clearScreen();
    }

    public function render(int $column, int $row, string $text): void
    {
        if ($column >= $this->width) {
            $this->width = $column;
        }
        if ($row > $this->height) {
            $this->height = $row;
        }
        $this->cursor->moveToPosition($column, $row);
        $this->write($text);
        $this->cursor->moveToPosition(0, $this->height);
        $this->write(PHP_EOL);
    }

    public function board(int $width, int $height): void
    {
        $this->width = $width;
        $this->height = $height;

        $borderLine = implode('', array_fill(0, $width - 2, '─'));
        $emptyLine = implode('', array_fill(0, $width - 2, ' '));

        $out = "┌{$borderLine}┐" . PHP_EOL;
        for ($i = 0; $i < $height - 2; ++$i) {
            $out .= "│{$emptyLine}│" . PHP_EOL;
        }
        $out .= "└{$borderLine}┘" . PHP_EOL;

        $this->write($out);
    }

    private function write(string $text): void
    {
        $this->output->write($text);
        echo $this->output->fetch();
    }
}
