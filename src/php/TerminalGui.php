<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

final class TerminalGui
{
    private int $maxWidth = 0;
    private int $maxHeight = 0;

    public static function withStream($inputStream = STDIN): self
    {
        $output = new ConsoleOutput();
        $cursor = new Cursor($output);
        $cursor->hide();
        $cursor->moveToPosition(0, 0);

        stream_set_blocking($inputStream, false);
        $sttyMode = shell_exec('stty -g');
        shell_exec('stty -icanon -echo');

        $self = new self($inputStream, $output, $cursor, $sttyMode);

        pcntl_signal(SIGINT, static function () use ($self) {
            $self->cleanUp();
            exit;
        });

        return $self;
    }

    /**
     * @param resource $inputStream
     */
    private function __construct(
        private $inputStream,
        private ConsoleOutputInterface $output,
        private Cursor $cursor,
        private string|null|false $sttyMode
    ) {
    }

    public function __destruct()
    {
        $this->cleanUp();
    }

    private function cleanUp(): void
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

    public function renderBoard(
        int $width,
        int $height,
        ?BorderStyle $style = null
    ): void {
        if ($style === null) {
            $style = BorderStyle::withChars();
        }
        $this->maxWidth = $width;
        $this->maxHeight = $height;

        $horizontalLine = implode('', array_fill(0, $width - 2, $style->horizontal()));
        $emptyLine = implode('', array_fill(0, $width - 2, ' '));
        $horizontalBorderLine = sprintf("%s%s%s\n", $style->corner(), $horizontalLine, $style->corner());

        $out = $horizontalBorderLine;
        $out .= str_repeat(
            sprintf("%s%s%s\n", $style->vertical(), $emptyLine, $style->vertical()),
            $height - 2
        );
        $out .= $horizontalBorderLine;

        $this->cursor->moveToPosition(0, 0);
        $this->write($out);
    }

    public function clearScreen(): void
    {
        $this->cursor->clearScreen();
    }

    public function clearLine(int $line): void
    {
        $this->cursor->moveToPosition(0, $line);
        $this->cursor->clearLine();
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
