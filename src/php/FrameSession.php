<?php

declare(strict_types=1);

namespace PhelCliGui;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Frame batching: while a frame is open, draws are accumulated in a buffer and
 * flushed to the terminal in a single write when the outermost frame closes.
 * Frames nest — only the outermost begin/end pair allocates and flushes.
 *
 * Owns just the batching state; TerminalGui routes draws to output() / cursor()
 * while a frame is active and writes end()'s payload to the real terminal.
 */
final class FrameSession
{
    private ?BufferedOutput $buffer = null;
    private ?Cursor $cursor = null;
    private int $depth = 0;

    /**
     * Set by a draw to request the trailing "park the cursor at max-bounds"
     * move. Coalesced — emitted once by end() instead of once per draw — so the
     * single flush carries one cursor move, not one per call.
     */
    private bool $pendingFinalize = false;

    public function isActive(): bool
    {
        return $this->buffer !== null;
    }

    /** The buffer draws are accumulated into while active, or null. */
    public function output(): ?OutputInterface
    {
        return $this->buffer;
    }

    /** The cursor bound to the frame buffer while active, or null. */
    public function cursor(): ?Cursor
    {
        return $this->cursor;
    }

    /**
     * Opens a frame nesting level. The outermost open allocates a buffer (and a
     * cursor on it) configured from $base; inner opens just bump the depth.
     */
    public function begin(OutputInterface $base): void
    {
        if ($this->depth++ > 0) {
            return;
        }

        $this->pendingFinalize = false;
        $this->buffer = new BufferedOutput(
            $base->getVerbosity(),
            $base->isDecorated(),
            $base->getFormatter(),
        );
        $this->cursor = new Cursor($this->buffer);
    }

    /** Requests the coalesced cursor-park move at the next outermost end(). */
    public function requestFinalize(): void
    {
        $this->pendingFinalize = true;
    }

    /**
     * Closes one nesting level. Returns the flush payload when the outermost
     * frame closes — parking the cursor at (maxColumn, maxRow) first if a draw
     * requested it — or null while frames remain open, none are open, or the
     * frame produced no output.
     */
    public function end(int $maxColumn, int $maxRow): ?string
    {
        if ($this->depth === 0 || --$this->depth > 0) {
            return null;
        }

        if ($this->pendingFinalize) {
            $this->pendingFinalize = false;
            $this->cursor?->moveToPosition($maxColumn, $maxRow);
        }

        $payload = $this->buffer?->fetch() ?? '';
        $this->buffer = null;
        $this->cursor = null;

        return $payload === '' ? null : $payload;
    }
}
