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
    private static ?self $instance = null;

    public static function getInstance($inputStream = STDIN): self
    {
        if (self::$instance === null) {
            self::$instance = self::withStream($inputStream);
        }

        return self::$instance;
    }

    public static function withStream($inputStream = STDIN): self
    {
        $output = new ConsoleOutput();
        $cursor = new Cursor($output);
        $cursor->hide();
        $cursor->moveToPosition(0, 0);

        stream_set_blocking($inputStream, false);
        $sttyMode = null;

        // Only modify terminal settings if we're in an actual terminal
        if (posix_isatty($inputStream)) {
            $sttyMode = shell_exec('stty -g');
            // Only change terminal mode if we successfully captured the current mode
            if ($sttyMode !== null && $sttyMode !== false && trim($sttyMode) !== '') {
                shell_exec('stty -icanon -echo');
            }
        }

        $self = new self($inputStream, $output, $cursor, $sttyMode);

        // Register cleanup for unexpected exits
        register_shutdown_function(static function () use ($self) {
            $self->cleanUp();
        });

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

    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            self::$instance->cleanUp();
            self::$instance = null;
        }
    }

    private function cleanUp(): void
    {
        // Reset cursor to normal state and clear any pending queries
        $this->cursor->show();
        $this->cursor->moveToPosition(0, 0);

        // Clear any pending terminal queries/responses
        $this->output->write("\033[0m"); // Reset all formatting

        stream_set_blocking($this->inputStream, true);

        // Restore terminal mode if we're in a terminal and have a valid saved mode
        if (posix_isatty($this->inputStream)) {
            if ($this->sttyMode !== null && $this->sttyMode !== false && trim($this->sttyMode) !== '') {
                shell_exec(sprintf('stty %s', escapeshellarg(trim($this->sttyMode))));
            } else {
                // Fallback: restore to sane defaults if we don't have the original mode
                shell_exec('stty icanon echo');
            }
        }

        // Flush any remaining output
        $this->output->write('');

        // Clear the singleton instance
        self::$instance = null;
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
