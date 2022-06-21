<?php

declare(strict_types = 1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

final class TerminalGui
{
    /** @var resource */
    private $inputStream;

    private ConsoleOutput $output;
    private Cursor $cursor;
    private int $maxWidth = 0;
    private int $maxHeight = 0;
    private string|null|false $sttyMode = null;

    public function __construct($inputStream = STDIN)
    {
        $this->inputStream = $inputStream;
        $this->output = new ConsoleOutput();
        $this->cursor = new Cursor($this->output);
        $this->cursor->hide();
        $this->cursor->moveToPosition(0, 0);

        stream_set_blocking($this->inputStream, false);
        $this->sttyMode = shell_exec('stty -g');
        shell_exec('stty -icanon -echo');
    }

    public function __destruct()
    {
        $this->cursor->show();
        stream_set_blocking($this->inputStream, true);
        if ($this->sttyMode !== null) {
            shell_exec(sprintf('stty %s', $this->sttyMode));
        }
    }

    public function addOutputFormatter(string $name, OutputFormatterStyleInterface $style): self
    {
        $this->output->getFormatter()->setStyle($name, $style);

        return $this;
    }

    public function renderBoard(int $width, int $height): void
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;

        $borderLine = implode('', array_fill(0, $width - 2, '-'));
        $emptyLine = implode('', array_fill(0, $width - 2, ' '));

        $out = "+$borderLine+" . PHP_EOL;
        $out .= str_repeat("|$emptyLine|" . PHP_EOL, $height - 2);
        $out .= "+$borderLine+" . PHP_EOL;

        $this->cursor->moveToPosition(0, 0);
        $this->write($out);
    }

    public function clearScreen(): void
    {
        $this->cursor->clearScreen();
    }

    public function clearOutput(): void
    {
        $this->cursor->clearOutput();
    }

    public function render(int $column, int $row, string $text, ?string $style = ''): void
    {
        if ($column >= $this->maxWidth) {
            $this->maxWidth = $column;
        }
        if ($row > $this->maxHeight) {
            $this->maxHeight = $row;
        }
        $this->cursor->moveToPosition($column, $row);
        $this->write($text, $style);
        $this->cursor->moveToPosition($this->maxWidth, $this->maxHeight);
        $this->write(PHP_EOL);
    }

    private function write(string $text, ?string $style = ''): void
    {
        if (empty($style)) {
            $this->output->write($text);
        } else {
            $this->output->write("<$style>$text</$style>");
        }
    }
}
