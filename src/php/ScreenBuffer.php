<?php

declare(strict_types=1);

namespace PhelCliGui;

use InvalidArgumentException;

/**
 * A virtual screen: a fixed grid of cells, each a single grapheme plus an
 * optional style name. Draw operations paint into the grid; diff() compares
 * against a previous buffer and yields only the runs that changed, so a frame
 * rewrites just the cells that moved instead of the whole screen.
 *
 * Cells are stored as two flat arrays indexed `row * width + column`: one for
 * the glyph, one for the style name (null = unstyled). Flat storage keeps the
 * per-frame diff scan and the snapshot() clone cheap.
 */
final class ScreenBuffer
{
    /** @var array<int, string> one glyph per cell, blank = ' ', indexed row*width+column */
    private array $chars;

    /** @var array<int, ?string> style name per cell, null = unstyled, indexed row*width+column */
    private array $styles;

    public function __construct(
        private readonly int $width,
        private readonly int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Screen buffer dimensions must be at least 1.');
        }

        $this->clear();
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    /** Resets every cell back to a blank, unstyled space. */
    public function clear(): void
    {
        $size = $this->width * $this->height;
        $this->chars = array_fill(0, $size, ' ');
        $this->styles = array_fill(0, $size, null);
    }

    /** Resets one row to blank, unstyled spaces. Out-of-range rows are ignored. */
    public function clearRow(int $row): void
    {
        if ($row < 0 || $row >= $this->height) {
            return;
        }

        $base = $row * $this->width;
        for ($x = 0; $x < $this->width; $x++) {
            $this->chars[$base + $x] = ' ';
            $this->styles[$base + $x] = null;
        }
    }

    /**
     * Writes `text` starting at (column, row), one grapheme per cell to the
     * right. Cells outside the grid are clipped silently. An empty or null
     * style stores null (unstyled).
     */
    public function paint(int $column, int $row, string $text, ?string $style): void
    {
        if ($text === '' || $row < 0 || $row >= $this->height) {
            return;
        }

        $normalizedStyle = ($style === null || $style === '') ? null : $style;
        $base = $row * $this->width;

        foreach (Text::graphemes($text) as $offset => $glyph) {
            $cellColumn = $column + $offset;
            if ($cellColumn < 0 || $cellColumn >= $this->width) {
                continue;
            }

            $idx = $base + $cellColumn;
            $this->chars[$idx] = $glyph;
            $this->styles[$idx] = $normalizedStyle;
        }
    }

    /**
     * Compares this buffer against `previous` and returns the minimal set of
     * runs to repaint. A run is a maximal horizontal span of cells that all
     * (a) changed versus `previous` and (b) share one style. Unchanged cells
     * and style boundaries break runs.
     *
     * When the buffers differ in size every cell is treated as changed.
     *
     * @return list<array{x:int,y:int,text:string,style:?string}>
     */
    public function diff(self $previous): array
    {
        $sameSize = $previous->width === $this->width && $previous->height === $this->height;
        $runs = [];

        for ($y = 0; $y < $this->height; $y++) {
            $base = $y * $this->width;
            $x = 0;

            while ($x < $this->width) {
                $idx = $base + $x;
                if ($sameSize
                    && $this->chars[$idx] === $previous->chars[$idx]
                    && $this->styles[$idx] === $previous->styles[$idx]
                ) {
                    $x++;
                    continue;
                }

                $style = $this->styles[$idx];
                $startX = $x;
                $text = '';

                while ($x < $this->width) {
                    $j = $base + $x;
                    if ($this->styles[$j] !== $style) {
                        break;
                    }
                    if ($sameSize
                        && $this->chars[$j] === $previous->chars[$j]
                        && $this->styles[$j] === $previous->styles[$j]
                    ) {
                        break;
                    }

                    $text .= $this->chars[$j];
                    $x++;
                }

                $runs[] = ['x' => $startX, 'y' => $y, 'text' => $text, 'style' => $style];
            }
        }

        return $runs;
    }

    /**
     * Returns an independent copy of the current cell state. Cheap: the cell
     * arrays share storage copy-on-write until either buffer is next mutated.
     */
    public function snapshot(): self
    {
        return clone $this;
    }
}
