<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class TerminalGui
{
    private int $maxWidth = 0;
    private int $maxHeight = 0;
    private bool $cleanedUp = false;

    /**
     * When a frame is open, draws are accumulated here and flushed to the real
     * output in a single write by endFrame(). Null in immediate mode.
     */
    private ?BufferedOutput $frameBuffer = null;
    private ?Cursor $frameCursor = null;
    private int $frameDepth = 0;

    /**
     * Set by a draw inside an open frame to request the trailing
     * "park the cursor at max-bounds" move. Within a frame the move is
     * coalesced — emitted once by endFrame() instead of once per draw —
     * so the single flush carries one cursor move, not one per call.
     */
    private bool $framePendingFinalize = false;

    /** Whether the alternate screen buffer is currently active. */
    private bool $inAltScreen = false;

    /**
     * Double-buffered diff rendering. While a diff session is open, draw verbs
     * paint into $diffBack instead of emitting cursor moves; present() writes
     * only the cells that changed since $diffFront (the last presented frame).
     */
    private ?ScreenBuffer $diffBack = null;
    private ?ScreenBuffer $diffFront = null;

    private static ?self $instance = null;

    public static function getInstance(
        $inputStream = STDIN,
        ?OutputInterface $output = null,
        ?Cursor $cursor = null,
        bool $registerShutdownHandlers = true,
    ): self {
        return self::$instance ??= self::withStream(
            $inputStream,
            $output,
            $cursor,
            $registerShutdownHandlers,
        );
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

    /**
     * Registers a named style from a raw ANSI SGR parameter string, e.g.
     * "38;5;196" (xterm-256) or "38;2;255;0;0" (truecolor). Usable in any
     * render call exactly like a style added via addOutputFormatter().
     */
    public function addAnsiStyle(string $name, string $sgr): self
    {
        return $this->addOutputFormatter($name, new AnsiStyle($sgr));
    }

    /**
     * Switches to the terminal's alternate screen buffer, so a full-screen UI
     * draws on a fresh page and the user's scrollback is restored untouched on
     * leaveAltScreen() (or cleanUp()). Idempotent.
     */
    public function enterAltScreen(): void
    {
        if ($this->inAltScreen) {
            return;
        }

        $this->inAltScreen = true;
        $this->activeOutput()->write("\033[?1049h", false, OutputInterface::OUTPUT_RAW);
    }

    /** Leaves the alternate screen buffer, restoring prior terminal content. */
    public function leaveAltScreen(): void
    {
        if (!$this->inAltScreen) {
            return;
        }

        $this->inAltScreen = false;
        $this->activeOutput()->write("\033[?1049l", false, OutputInterface::OUTPUT_RAW);
    }

    /** Moves the cursor to an absolute (column, row) position. */
    public function moveCursor(int $column, int $row): void
    {
        $this->activeCursor()->moveToPosition($column, $row);
    }

    /** Moves the cursor to the top-left origin (0, 0). */
    public function cursorHome(): void
    {
        $this->activeCursor()->moveToPosition(0, 0);
    }

    /**
     * Opens a buffered frame: subsequent draw/cursor operations are accumulated
     * and emitted in a single write by endFrame(), instead of one write per call.
     * Nestable — only the outermost end flushes.
     */
    public function beginFrame(): void
    {
        if ($this->frameDepth++ > 0) {
            return;
        }

        $this->framePendingFinalize = false;
        $this->frameBuffer = new BufferedOutput(
            $this->output->getVerbosity(),
            $this->output->isDecorated(),
            $this->output->getFormatter(),
        );
        $this->frameCursor = new Cursor($this->frameBuffer);
    }

    /**
     * Flushes the current buffered frame to the terminal in a single raw write.
     * No-op when no frame is open; only the outermost call flushes.
     */
    public function endFrame(): void
    {
        if ($this->frameDepth === 0 || --$this->frameDepth > 0) {
            return;
        }

        if ($this->framePendingFinalize) {
            $this->framePendingFinalize = false;
            $this->frameCursor?->moveToPosition($this->maxWidth, $this->maxHeight);
        }

        $buffer = $this->frameBuffer?->fetch() ?? '';
        $this->frameBuffer = null;
        $this->frameCursor = null;

        if ($buffer !== '') {
            $this->output->write($buffer, false, OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Opens a double-buffered diff session sized to (width, height). While open,
     * draw verbs paint into a back-buffer instead of the terminal; present()
     * then writes only the cells that changed since the previous frame. Pair
     * with clearBuffer() at the top of each frame to repaint from blank.
     */
    public function beginDiff(int $width, int $height): void
    {
        $this->diffBack = new ScreenBuffer($width, $height);
        $this->diffFront = null;
    }

    /** Closes the diff session and releases both buffers. */
    public function endDiff(): void
    {
        $this->diffBack = null;
        $this->diffFront = null;
    }

    /** Resets the back-buffer to blank. No-op when no diff session is open. */
    public function clearBuffer(): void
    {
        $this->diffBack?->clear();
    }

    /**
     * Diffs the back-buffer against the previously presented frame and writes
     * only the changed runs in a single raw write, then snapshots the
     * back-buffer as the new previous frame. No-op when no diff session is open.
     */
    public function present(): void
    {
        $back = $this->diffBack;
        if ($back === null) {
            return;
        }

        $previous = $this->diffFront ?? new ScreenBuffer($back->width(), $back->height());
        $runs = $back->diff($previous);

        if ($runs !== []) {
            $buffer = new BufferedOutput(
                $this->output->getVerbosity(),
                $this->output->isDecorated(),
                $this->output->getFormatter(),
            );
            $cursor = new Cursor($buffer);

            // Track the cursor's logical (column, row) so same-row runs reposition
            // with a short relative move — or none at all when adjacent — instead
            // of a full absolute jump. Writing a run advances the cursor to the end
            // of its text, so the next same-row run starts from there. Runs arrive
            // left-to-right top-to-bottom, so same-row gaps are always forward;
            // only a row change falls back to an absolute move. (-1 = unknown: the
            // real cursor position at present()-time is not tracked, so the first
            // run is always absolute.)
            $curColumn = -1;
            $curRow = -1;

            foreach ($runs as $run) {
                $x = $run['x'];
                $y = $run['y'];

                if ($y === $curRow && $x > $curColumn) {
                    $cursor->moveRight($x - $curColumn);
                } elseif ($y !== $curRow || $x !== $curColumn) {
                    $cursor->moveToPosition($x, $y);
                }

                $buffer->write($this->decorate($run['text'], $run['style']));

                $curRow = $y;
                $curColumn = $x + self::runWidth($run['text']);
            }

            $out = $buffer->fetch();
            if ($out !== '') {
                $this->output->write($out, false, OutputInterface::OUTPUT_RAW);
            }
        }

        $this->diffFront = $back->snapshot();
    }

    private function activeCursor(): Cursor
    {
        return $this->frameCursor ?? $this->cursor;
    }

    private function activeOutput(): OutputInterface
    {
        return $this->frameBuffer ?? $this->output;
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
        $this->activeCursor()->clearScreen();
    }

    public function hideCursor(): void
    {
        $this->activeCursor()->hide();
    }

    public function showCursor(): void
    {
        $this->activeCursor()->show();
    }

    public function clearLine(int $line): void
    {
        $this->activeCursor()->moveToPosition(0, $line);
        $this->activeCursor()->clearLine();
    }

    public function clearOutput(): void
    {
        $this->activeCursor()->clearOutput();
    }

    public function render(int $column, int $row, string $text, ?string $style = ''): void
    {
        $this->paint($column, $row, $text, $style);
        $this->updateBoundsForArea($column, $row, Text::displayWidth($text), 1);
        $this->finalizeCursor();
    }

    public function renderTextBlock(int $column, int $row, string $text, ?string $style = ''): void
    {
        $lines = preg_split('/\R/', $text) ?: [''];
        foreach ($lines as $offset => $line) {
            $this->paint($column, $row + $offset, $line, $style);
            $this->updateBoundsForArea($column, $row + $offset, Text::displayWidth($line), 1);
        }
        $this->finalizeCursor();
    }

    public function drawHorizontalLine(int $column, int $row, int $length, string $char, ?string $style = ''): void
    {
        $line = TerminalCanvas::horizontalLine($length, $char);
        $this->paint($column, $row, $line, $style);
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
            $this->paint($column, $row + $offset, $segment, $style);
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
            $this->paint($column, $row + $offset, $line, null);
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
            $this->paint($column, $row + $offset, $line, null);
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

    /**
     * Places text at (column, row): into the diff back-buffer when a diff
     * session is open, otherwise straight to the active cursor/output. The one
     * funnel every draw verb routes glyph placement through.
     */
    private function paint(int $column, int $row, string $text, ?string $style): void
    {
        if ($this->diffBack !== null) {
            $this->diffBack->paint($column, $row, $text, $style);
            return;
        }

        $this->activeCursor()->moveToPosition($column, $row);
        $this->write($text, $style);
    }

    private function write(string $text, ?string $style = ''): void
    {
        $this->activeOutput()->write($this->decorate($text, $style));
    }

    private function decorate(string $text, ?string $style): string
    {
        return ($style === null || $style === '') ? $text : "<$style>$text</$style>";
    }

    /** Columns a diff run advances the cursor: one cell per codepoint. */
    private static function runWidth(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    private function updateBoundsForArea(int $column, int $row, int $width, int $height): void
    {
        $this->maxWidth = max($this->maxWidth, $column + max(0, $width - 1));
        $this->maxHeight = max($this->maxHeight, $row + max(0, $height - 1));
    }

    private function finalizeCursor(): void
    {
        // Diff sessions own cursor placement at present()-time; draws into the
        // back-buffer never move the real cursor.
        if ($this->diffBack !== null) {
            return;
        }

        // Inside a frame, defer to a single move emitted by endFrame() so the
        // flush carries one trailing cursor move rather than one per draw.
        if ($this->frameDepth > 0) {
            $this->framePendingFinalize = true;
            return;
        }

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

        if ($this->inAltScreen) {
            $this->inAltScreen = false;
            $this->output->write("\033[?1049l", false, OutputInterface::OUTPUT_RAW);
        }

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
