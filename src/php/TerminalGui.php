<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

final class TerminalGui
{
    /**
     * DEC private mode 2026 (synchronized output): the terminal holds the
     * repaint until the closing sequence arrives, so a batched frame appears
     * atomically instead of tearing mid-write. Terminals without support
     * ignore both sequences.
     */
    private const SYNC_BEGIN = "\x1b[?2026h";
    private const SYNC_END = "\x1b[?2026l";

    private int $maxWidth = 0;
    private int $maxHeight = 0;
    private bool $cleanedUp = false;

    /** Whether the alternate screen buffer is currently active. */
    private bool $inAltScreen = false;

    /**
     * Whether the open diff session spans the terminal's full width. Only
     * then may present() collapse a trailing blank run into erase-to-EOL
     * (\e[K), which wipes to the end of the *terminal* row — on a narrower
     * session it would destroy content beyond the session's edge.
     */
    private bool $diffCoversTerminalWidth = false;

    /** Batches a frame's draws into one write (see FrameSession). */
    private readonly FrameSession $frame;

    /** Double-buffered cell diffing (see DiffSession). */
    private readonly DiffSession $diff;

    private static ?self $instance = null;

    /** @param resource $inputStream */
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

    /** @param resource $inputStream */
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
        $this->frame = new FrameSession();
        $this->diff = new DiffSession();
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

    /**
     * Moves the cursor to an absolute (column, row) position. No-op during a
     * diff session — present() owns cursor placement there.
     */
    public function moveCursor(int $column, int $row): void
    {
        if ($this->diff->isActive()) {
            return;
        }

        $this->activeCursor()->moveToPosition($column, $row);
    }

    /**
     * Moves the cursor to the top-left origin (0, 0). No-op during a diff
     * session — present() owns cursor placement there.
     */
    public function cursorHome(): void
    {
        if ($this->diff->isActive()) {
            return;
        }

        $this->activeCursor()->moveToPosition(0, 0);
    }

    /**
     * Opens a buffered frame: subsequent draw/cursor operations are accumulated
     * and emitted in a single write by endFrame(), instead of one write per call.
     * Nestable — only the outermost end flushes.
     */
    public function beginFrame(): void
    {
        $this->frame->begin($this->output);
    }

    /**
     * Flushes the current buffered frame to the terminal in a single raw write.
     * No-op when no frame is open; only the outermost call flushes.
     */
    public function endFrame(): void
    {
        $payload = $this->frame->end($this->maxWidth, $this->maxHeight);

        if ($payload !== null) {
            $this->output->write(
                self::SYNC_BEGIN . $payload . self::SYNC_END,
                false,
                OutputInterface::OUTPUT_RAW,
            );
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
        $this->diff->begin($width, $height);
        $this->diffCoversTerminalWidth = $width >= (new Terminal())->getWidth();
    }

    /** Closes the diff session and releases both buffers. */
    public function endDiff(): void
    {
        $this->diff->end();
    }

    /** Resets the back-buffer to blank. No-op when no diff session is open. */
    public function clearBuffer(): void
    {
        $this->diff->clear();
    }

    /**
     * Diffs the back-buffer against the previously presented frame and writes
     * only the changed runs in a single raw write, then snapshots the
     * back-buffer as the new previous frame. No-op when no diff session is open.
     */
    public function present(): void
    {
        $runs = $this->diff->collectRuns();
        if ($runs === []) {
            return;
        }

        // Track the cursor's logical (column, row) so same-row runs reposition
        // with a short relative move — or none at all when adjacent — instead
        // of a full absolute jump. Writing a run advances the cursor to the end
        // of its text, so the next same-row run starts from there. Runs arrive
        // left-to-right top-to-bottom, so same-row gaps are always forward;
        // only a row change falls back to an absolute move. (-1 = unknown: the
        // real cursor position at present()-time is not tracked, so the first
        // run is always absolute.)
        // The escape sequences match Symfony Cursor's moveRight()/
        // moveToPosition() byte-for-byte; building the payload as one string
        // avoids a BufferedOutput + Cursor allocation per frame.
        $curColumn = -1;
        $curRow = -1;
        $out = '';
        $screenWidth = $this->diff->width();

        foreach ($runs as $run) {
            $x = $run['x'];
            $y = $run['y'];

            if ($y === $curRow && $x > $curColumn) {
                $out .= "\x1b[" . ($x - $curColumn) . 'C';
            } elseif ($y !== $curRow || $x !== $curColumn) {
                $out .= "\x1b[" . ($y + 1) . ';' . $x . 'H';
            }

            $text = $run['text'];

            // A trailing unstyled blank run collapses to erase-to-EOL: 3
            // bytes instead of one space per cell. Only on full-width
            // sessions (see $diffCoversTerminalWidth) and only unstyled —
            // erased cells take the default background, not a style's.
            if ($this->diffCoversTerminalWidth
                && $run['style'] === null
                && strspn($text, ' ') === strlen($text)
                && $x + strlen($text) === $screenWidth
            ) {
                $out .= "\x1b[K"; // cursor does not move
                $curRow = $y;
                $curColumn = $x;
                continue;
            }

            $out .= $this->applyStyle($text, $run['style']);

            $curRow = $y;
            $curColumn = $x + Text::codepointCount($text);
        }

        $this->output->write(
            self::SYNC_BEGIN . $out . self::SYNC_END,
            false,
            OutputInterface::OUTPUT_RAW,
        );
    }

    private function activeCursor(): Cursor
    {
        return $this->frame->cursor() ?? $this->cursor;
    }

    private function activeOutput(): OutputInterface
    {
        return $this->frame->output() ?? $this->output;
    }

    public function renderBoard(
        int $width,
        int $height,
        ?BorderStyle $style = null,
    ): void {
        $this->drawBox(0, 0, $width, $height, $style);
    }

    /**
     * Clears the screen. During a diff session the back-buffer is the screen,
     * so this blanks it (like clearBuffer()) rather than punching an escape to
     * the terminal — which would desync the diff baseline.
     */
    public function clearScreen(): void
    {
        if ($this->diff->isActive()) {
            $this->diff->clear();
            return;
        }

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

    /**
     * Clears one line. During a diff session this blanks that row of the
     * back-buffer instead of writing to the terminal.
     */
    public function clearLine(int $line): void
    {
        if ($this->diff->isActive()) {
            $this->diff->clearLine($line);
            return;
        }

        $this->activeCursor()->moveToPosition(0, $line);
        $this->activeCursor()->clearLine();
    }

    /**
     * Clears from the cursor to the end of the screen. This is cursor-relative
     * and has no diff-buffer equivalent (a diff session has no live cursor), so
     * it is a no-op during one — clear the area with clearScreen()/clearBuffer()
     * or paint blanks instead.
     */
    public function clearOutput(): void
    {
        if ($this->diff->isActive()) {
            return;
        }

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
        if ($this->diff->isActive()) {
            $this->diff->paint($column, $row, $text, $style);
            return;
        }

        $this->activeCursor()->moveToPosition($column, $row);
        $this->write($text, $style);
    }

    private function write(string $text, ?string $style = ''): void
    {
        $this->writeStyled($this->activeOutput(), $text, $style);
    }

    /**
     * Writes text to an output with its style already applied, always raw.
     * Bypassing the formatter's tag parse is faster and keeps literal '<...>'
     * in user text intact — styled or not — instead of having it swallowed
     * as a markup tag.
     */
    private function writeStyled(OutputInterface $output, string $text, ?string $style): void
    {
        $output->write($this->applyStyle($text, $style), false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Resolves a named style against the registered formatter styles and wraps
     * the text in its ANSI sequences (skipped when the output is undecorated).
     * An unknown name fails fast instead of leaking markup to the screen.
     */
    private function applyStyle(string $text, ?string $style): string
    {
        if ($style === null || $style === '') {
            return $text;
        }

        $formatter = $this->output->getFormatter();
        if (!$formatter->hasStyle($style)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown style "%s". Register it first with add-color or add-output-formatter.',
                $style,
            ));
        }

        return $this->output->isDecorated()
            ? $formatter->getStyle($style)->apply($text)
            : $text;
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
        if ($this->diff->isActive()) {
            return;
        }

        // Inside a frame, defer to a single move emitted by endFrame() so the
        // flush carries one trailing cursor move rather than one per draw.
        if ($this->frame->isActive()) {
            $this->frame->requestFinalize();
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

        // Guards against an already-closed stream: at shutdown the input may be
        // a closed resource (is_resource() === false), which restoreSttyMode's
        // posix_isatty() would choke on.
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
     *
     * @phpstan-assert-if-true resource $stream
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
