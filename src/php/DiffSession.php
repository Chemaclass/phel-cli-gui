<?php

declare(strict_types=1);

namespace PhelCliGui;

/**
 * Double-buffered diff rendering. While a session is open, draws paint into a
 * back-buffer instead of the terminal; collectRuns() diffs that against the
 * previously presented frame and yields only the cells that changed.
 *
 * Owns the cell buffers and the frame baseline; TerminalGui turns the returned
 * runs into the minimal cursor moves + writes.
 */
final class DiffSession
{
    private ?ScreenBuffer $back = null;
    private ?ScreenBuffer $front = null;

    public function isActive(): bool
    {
        return $this->back !== null;
    }

    /** Opens a session sized to (width, height) with a blank back-buffer. */
    public function begin(int $width, int $height): void
    {
        $this->back = new ScreenBuffer($width, $height);
        $this->front = null;
    }

    /** Closes the session and releases both buffers. */
    public function end(): void
    {
        $this->back = null;
        $this->front = null;
    }

    /** Resets the back-buffer to blank. No-op when no session is open. */
    public function clear(): void
    {
        $this->back?->clear();
    }

    /** Blanks one row of the back-buffer. No-op when no session is open. */
    public function clearLine(int $row): void
    {
        $this->back?->clearRow($row);
    }

    /** Paints into the back-buffer. No-op when no session is open. */
    public function paint(int $column, int $row, string $text, ?string $style): void
    {
        $this->back?->paint($column, $row, $text, $style);
    }

    /**
     * Diffs the back-buffer against the previously presented frame, advances the
     * baseline to the current back-buffer, and returns the runs to repaint
     * (empty when nothing changed or no session is open).
     *
     * @return list<array{x:int,y:int,text:string,style:?string}>
     */
    public function collectRuns(): array
    {
        $back = $this->back;
        if ($back === null) {
            return [];
        }

        $previous = $this->front ?? new ScreenBuffer($back->width(), $back->height());
        $runs = $back->diff($previous);
        $this->front = $back->snapshot();

        return $runs;
    }
}
