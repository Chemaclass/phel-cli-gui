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
    private bool $cleanedUp = false;

    public static function getInstance($inputStream = STDIN): self
    {
        if (self::$instance === null) {
            self::$instance = self::withStream($inputStream);
        }

        return self::$instance;
    }

    public static function withStream(
        $inputStream = STDIN,
        ?ConsoleOutputInterface $output = null,
        ?Cursor $cursor = null,
        bool $registerShutdownHandlers = true
    ): self
    {
        $output ??= new ConsoleOutput();
        $cursor ??= new Cursor($output);
        $cursor->hide();
        $cursor->moveToPosition(0, 0);

        self::setBlockingIfPossible($inputStream, false);
        $sttyMode = null;

        // Only modify terminal settings if we're in an actual terminal
        if (self::isStreamResource($inputStream) && function_exists('posix_isatty') && @posix_isatty($inputStream)) {
            $sttyMode = shell_exec('stty -g');
            // Only change terminal mode if we successfully captured the current mode
            if ($sttyMode !== null && $sttyMode !== false && trim($sttyMode) !== '') {
                shell_exec('stty -icanon -echo');
            }
        }

        $self = new self($inputStream, $output, $cursor, $sttyMode);

        if ($registerShutdownHandlers) {
            // Register cleanup for unexpected exits
            register_shutdown_function(static function () use ($self) {
                $self->cleanUp();
            });

            pcntl_signal(SIGINT, static function () use ($self) {
                $self->cleanUp();
                exit;
            });
        }

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
        $this->cleanedUp = false;
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
        if ($this->cleanedUp) {
            return;
        }

        $this->cleanedUp = true;

        // Reset cursor to normal state and clear any pending queries
        $this->cursor->show();

        // Clear any pending terminal queries/responses
        $this->output->write("\033[0m"); // Reset all formatting

        if (self::isStreamResource($this->inputStream)) {
            self::setBlockingIfPossible($this->inputStream, true);

            // Restore terminal mode if we're in a terminal and have a valid saved mode
            if (function_exists('posix_isatty') && @posix_isatty($this->inputStream)) {
                if ($this->sttyMode !== null && $this->sttyMode !== false && trim($this->sttyMode) !== '') {
                    shell_exec(sprintf('stty %s', escapeshellarg(trim($this->sttyMode))));
                } else {
                    // Fallback: restore to sane defaults if we don't have the original mode
                    shell_exec('stty icanon echo');
                }
            }
        }

        // Flush any remaining output
        $this->output->write('');

        // Clear the singleton instance
        self::$instance = null;
    }

    /**
     * @param resource|mixed $stream
     */
    private static function isStreamResource($stream): bool
    {
        return is_resource($stream) && get_resource_type($stream) === 'stream';
    }

    /**
     * @param resource|mixed $stream
     */
    private static function setBlockingIfPossible($stream, bool $shouldBlock): bool
    {
        if (!self::isStreamResource($stream)) {
            return false;
        }

        try {
            return stream_set_blocking($stream, $shouldBlock);
        } catch (\Throwable $exception) {
            return false;
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
        $this->drawBox(0, 0, $width, $height, $style ?? BorderStyle::withChars());
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
        $this->cursor->moveToPosition($column, $row);
        $this->write($text, $style);
        $this->write(PHP_EOL);
        $this->updateBoundsForText($column, $row, $text);
        $this->finalizeCursor();
    }

    public function renderTextBlock(int $column, int $row, string $text, ?string $style = ''): void
    {
        $lines = preg_split('/\R/', $text) ?: [''];
        foreach ($lines as $offset => $line) {
            $this->render($column, $row + $offset, $line, $style);
        }
    }

    public function drawHorizontalLine(int $column, int $row, int $length, string $char, ?string $style = ''): void
    {
        $line = TerminalCanvas::horizontalLine($length, $char);
        $this->cursor->moveToPosition($column, $row);
        $this->write($line, $style);
        $this->write(PHP_EOL);
        $this->updateBoundsForArea($column, $row, $length, 1);
        $this->finalizeCursor();
    }

    public function drawVerticalLine(int $column, int $row, int $length, string $char, ?string $style = ''): void
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Vertical line length must be at least 1.');
        }

        $segment = TerminalCanvas::horizontalLine(1, $char !== '' ? $char : '|');
        for ($offset = 0; $offset < $length; $offset++) {
            $this->render($column, $row + $offset, $segment, $style);
        }
    }

    public function drawBox(
        int $column,
        int $row,
        int $width,
        int $height,
        ?BorderStyle $style = null,
        string $fillChar = ' '
    ): void {
        $style ??= BorderStyle::withChars();
        $lines = TerminalCanvas::boxLines($width, $height, $style, $fillChar);

        foreach ($lines as $offset => $line) {
            $this->cursor->moveToPosition($column, $row + $offset);
            $this->write($line);
            $this->write(PHP_EOL);
        }

        $this->updateBoundsForArea($column, $row, $width, $height);
        $this->finalizeCursor();
    }

    public function getMaxWidth(): int
    {
        return $this->maxWidth;
    }

    public function getMaxHeight(): int
    {
        return $this->maxHeight;
    }

    private function write(string $text, ?string $style = ''): void
    {
        if (empty($style)) {
            $this->output->write($text);
        } else {
            $this->output->write("<$style>$text</$style>");
        }
    }

    private function updateBoundsForText(int $column, int $row, string $text): void
    {
        $width = $this->stringWidth($text);
        $this->updateBoundsForArea($column, $row, $width === 0 ? 1 : $width, 1);
    }

    private function updateBoundsForArea(int $column, int $row, int $width, int $height): void
    {
        $this->maxWidth = max($this->maxWidth, $column + max(0, $width - 1));
        $this->maxHeight = max($this->maxHeight, $row + max(0, $height - 1));
    }

    private function finalizeCursor(): void
    {
        $this->cursor->moveToPosition($this->maxWidth, $this->maxHeight);
    }

    private function stringWidth(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (function_exists('mb_strlen')) {
            return mb_strlen($text);
        }

        return strlen($text);
    }
}
