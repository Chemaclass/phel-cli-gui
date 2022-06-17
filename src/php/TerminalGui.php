<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TerminalGui
{
    private BufferedOutput $output;

    private Cursor $cursor;
    private int $width = 0;
    private int $height = 0;

    public function __construct(?OutputFormatter $formatter = null)
    {
        $this->output = new BufferedOutput();
        $this->output->setFormatter($formatter ?? new OutputFormatter());

        $this->cursor = new Cursor($this->output);
        $this->cursor->hide();
    }

    public function __destruct()
    {
        $this->cursor->show();
    }

    public function board(int $width, int $height, $inputStream = STDIN): void
    {
        $this->width = $width;
        $this->height = $height;

        stream_set_blocking($inputStream, false);
        $sttyMode = shell_exec('stty -g');
        shell_exec('stty -icanon -echo');

        $borderLine = implode('', array_fill(0, $width - 2, '─'));
        $emptyLine = implode('', array_fill(0, $width - 2, ' '));

        $out = "┌{$borderLine}┐" . PHP_EOL;
        $out .= str_repeat("│{$emptyLine}│" . PHP_EOL, $height - 2);
        $out .= "└{$borderLine}┘" . PHP_EOL;

        $this->cursor->moveToPosition(0, 0);
        $this->write($out);

        stream_set_blocking($inputStream, true);
        shell_exec(sprintf('stty %s', $sttyMode));
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

    private function write(string $text): void
    {
        $this->output->write($text);
        echo $this->output->fetch();
    }
}
