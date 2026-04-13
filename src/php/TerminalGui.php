<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TerminalGui
{
    private int $maxWidth = 0;
    private int $maxHeight = 0;
    private bool $cleanedUp = false;
    private static ?self $instance = null;

    public static function getInstance($inputStream = STDIN): self
    {
        return self::$instance ??= self::withStream($inputStream);
    }

    public static function withStream(
        $inputStream = STDIN,
        ?OutputInterface $output = null,
        ?Cursor $cursor = null,
        bool $registerShutdownHandlers = true,
    ): self {
        $output ??= new ConsoleOutput();
        $cursor ??= new Cursor($output);
        $cursor->hide();
        $cursor->moveToPosition(0, 0);

        self::setBlockingIfPossible($inputStream, false);
        $sttyMode = self::captureSttyMode($inputStream);

        $self = new self($inputStream, $output, $cursor, $sttyMode);

        if ($registerShutdownHandlers) {
            register_shutdown_function(static fn () => $self->cleanUp());
            pcntl_signal(SIGINT, static function () use ($self): void {
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
        private readonly OutputInterface $output,
        private readonly Cursor $cursor,
        private string|null|false $sttyMode,
    ) {
    }

    public function __destruct()
    {
        $this->cleanUp();
    }

    public static function resetInstance(): void
    {
        self::$instance?->cleanUp();
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
        ?BorderStyle $style = null,
    ): void {
        $this->drawBox(0, 0, $width, $height, $style);
    }

    public function clearScreen(): void
    {
        $this->cursor->clearScreen();
    }

    public function hideCursor(): void
    {
        $this->cursor->hide();
    }

    public function showCursor(): void
    {
        $this->cursor->show();
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
        $this->updateBoundsForArea($column, $row, Text::displayWidth($text), 1);
        $this->finalizeCursor();
    }

    public function renderTextBlock(int $column, int $row, string $text, ?string $style = ''): void
    {
        $lines = preg_split('/\R/', $text) ?: [''];
        foreach ($lines as $offset => $line) {
            $this->cursor->moveToPosition($column, $row + $offset);
            $this->write($line, $style);
            $this->updateBoundsForArea($column, $row + $offset, Text::displayWidth($line), 1);
        }
        $this->finalizeCursor();
    }

    public function drawHorizontalLine(int $column, int $row, int $length, string $char, ?string $style = ''): void
    {
        $line = TerminalCanvas::horizontalLine($length, $char);
        $this->cursor->moveToPosition($column, $row);
        $this->write($line, $style);
        $this->updateBoundsForArea($column, $row, $length, 1);
        $this->finalizeCursor();
    }

    public function drawVerticalLine(int $column, int $row, int $length, string $char, ?string $style = ''): void
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Vertical line length must be at least 1.');
        }

        $segment = Text::firstChar($char !== '' ? $char : '|', '|');
        for ($offset = 0; $offset < $length; $offset++) {
            $this->cursor->moveToPosition($column, $row + $offset);
            $this->write($segment, $style);
        }
        $this->updateBoundsForArea($column, $row, 1, $length);
        $this->finalizeCursor();
    }

    public function fillRegion(
        int $column,
        int $row,
        int $width,
        int $height,
        string $fillChar = ' ',
    ): void {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException('Region width and height must be at least 1.');
        }

        $line = TerminalCanvas::horizontalLine($width, $fillChar);
        for ($offset = 0; $offset < $height; $offset++) {
            $this->cursor->moveToPosition($column, $row + $offset);
            $this->write($line);
        }
        $this->updateBoundsForArea($column, $row, $width, $height);
        $this->finalizeCursor();
    }

    public function drawBox(
        int $column,
        int $row,
        int $width,
        int $height,
        ?BorderStyle $style = null,
        string $fillChar = ' ',
    ): void {
        $lines = TerminalCanvas::boxLines($width, $height, $style ?? BorderStyle::withChars(), $fillChar);

        foreach ($lines as $offset => $line) {
            $this->cursor->moveToPosition($column, $row + $offset);
            $this->write($line);
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
        $this->output->write(empty($style) ? $text : "<$style>$text</$style>");
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

    private function cleanUp(): void
    {
        if ($this->cleanedUp) {
            return;
        }

        $this->cleanedUp = true;
        $this->cursor->show();
        $this->output->write("\033[0m");

        if (self::isStreamResource($this->inputStream)) {
            self::setBlockingIfPossible($this->inputStream, true);
            $this->restoreSttyMode();
        }

        self::$instance = null;
    }

    private function restoreSttyMode(): void
    {
        if (!function_exists('posix_isatty') || !@posix_isatty($this->inputStream)) {
            return;
        }

        if (is_string($this->sttyMode) && trim($this->sttyMode) !== '') {
            shell_exec(sprintf('stty %s', escapeshellarg(trim($this->sttyMode))));
            return;
        }

        shell_exec('stty icanon echo');
    }

    /**
     * @param resource|mixed $inputStream
     */
    private static function captureSttyMode($inputStream): string|null|false
    {
        if (!self::isStreamResource($inputStream)
            || !function_exists('posix_isatty')
            || !@posix_isatty($inputStream)
        ) {
            return null;
        }

        $sttyMode = shell_exec('stty -g');
        if (!is_string($sttyMode) || trim($sttyMode) === '') {
            return $sttyMode;
        }

        shell_exec('stty -icanon -echo');
        return $sttyMode;
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
        } catch (\Throwable) {
            return false;
        }
    }
}
