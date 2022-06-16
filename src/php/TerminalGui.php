<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\BufferedOutput;

final class TerminalGui
{
    private BufferedOutput $output;

    private Cursor $cursor;

    public function __construct()
    {
        $this->output = new BufferedOutput();
        $this->cursor = new Cursor($this->output);
    }

    public function render(int $column, int $row, string $text): void
    {
        $this->cursor->moveToPosition($column, $row);
        $this->output->write($text);

        echo $this->output->fetch();
    }
}
