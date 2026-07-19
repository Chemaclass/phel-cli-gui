<?php

declare(strict_types=1);

namespace PhelCliGui;

use InvalidArgumentException;
use RuntimeException;

/**
 * A virtual screen: a fixed grid of cells, each a single grapheme plus an
 * optional style name. Draw operations paint into the grid; diff() compares
 * against a previous buffer and yields only the runs that changed, so a frame
 * rewrites just the cells that moved instead of the whole screen.
 *
 * Rows are stored packed: one byte string of glyphs and one byte string of
 * interned style ids per row. That makes the per-frame diff a string
 * comparison per row (memcmp speed) with an XOR scan to find the changed
 * span, instead of a PHP loop over every cell. Multibyte glyphs are marked
 * with a sentinel byte and kept in a small per-row side table.
 */
final class ScreenBuffer
{
    /** Sentinel glyph byte: the cell's real glyph lives in $wide. */
    private const WIDE = "\0";

    /** Style-id byte for the unstyled state. */
    private const UNSTYLED = "\0";

    /** @var array<int, string> one glyph byte per cell; self::WIDE = see $wide */
    private array $rowChars;

    /** @var array<int, string> one interned style-id byte per cell */
    private array $rowStyles;

    /** @var array<int, array<int, string>> multibyte glyphs, keyed [row][column] */
    private array $wide = [];

    /**
     * Style ids are interned process-wide so the same byte always means the
     * same style name in any buffer — diffs between buffers stay a plain
     * byte comparison. The table only ever grows.
     *
     * @var array<string, string> style name => id byte
     */
    private static array $styleIds = [];

    /** @var array<int, string|null> id => style name; 0 = unstyled */
    private static array $styleNames = [null];

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
        $this->rowChars = array_fill(0, $this->height, str_repeat(' ', $this->width));
        $this->rowStyles = array_fill(0, $this->height, str_repeat(self::UNSTYLED, $this->width));
        $this->wide = [];
    }

    /** Resets one row to blank, unstyled spaces. Out-of-range rows are ignored. */
    public function clearRow(int $row): void
    {
        if ($row < 0 || $row >= $this->height) {
            return;
        }

        $this->rowChars[$row] = str_repeat(' ', $this->width);
        $this->rowStyles[$row] = str_repeat(self::UNSTYLED, $this->width);
        unset($this->wide[$row]);
    }

    /**
     * Writes `text` starting at (column, row), one grapheme per cell to the
     * right. Cells outside the grid are clipped silently. An empty or null
     * style stores the unstyled state.
     */
    public function paint(int $column, int $row, string $text, ?string $style): void
    {
        if ($text === '' || $row < 0 || $row >= $this->height) {
            return;
        }

        $styleByte = ($style === null || $style === '') ? self::UNSTYLED : self::styleIdByte($style);

        // Printable-ASCII fast path: bytes are glyphs, so the clipped slice
        // splices into the packed row in two C-level string operations.
        if (strspn($text, Text::ASCII_PRINTABLE) === strlen($text)) {
            $first = $column < 0 ? -$column : 0;
            $last = min(strlen($text), $this->width - $column);
            if ($first >= $last) {
                return;
            }

            $at = $column + $first;
            $length = $last - $first;
            $this->rowChars[$row] = substr_replace($this->rowChars[$row], substr($text, $first, $length), $at, $length);
            $this->rowStyles[$row] = substr_replace($this->rowStyles[$row], str_repeat($styleByte, $length), $at, $length);

            if (isset($this->wide[$row])) {
                $this->clearWideSpan($row, $at, $length);
            }

            return;
        }

        $glyphs = Text::graphemes($text);

        $first = $column < 0 ? -$column : 0;
        $last = min(count($glyphs), $this->width - $column);
        if ($first >= $last) {
            return;
        }

        for ($i = $first; $i < $last; $i++) {
            $x = $column + $i;
            $glyph = $glyphs[$i];

            if (strlen($glyph) === 1 && $glyph !== self::WIDE) {
                $this->rowChars[$row][$x] = $glyph;
                unset($this->wide[$row][$x]);
            } else {
                $this->rowChars[$row][$x] = self::WIDE;
                $this->wide[$row][$x] = $glyph;
            }

            $this->rowStyles[$row][$x] = $styleByte;
        }

        if (isset($this->wide[$row]) && $this->wide[$row] === []) {
            unset($this->wide[$row]);
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
            $chars = $this->rowChars[$y];
            $styles = $this->rowStyles[$y];
            $wide = $this->wide[$y] ?? [];

            $prevChars = '';
            $prevStyles = '';
            $prevWide = [];

            if ($sameSize) {
                $prevChars = $previous->rowChars[$y];
                $prevStyles = $previous->rowStyles[$y];
                $prevWide = $previous->wide[$y] ?? [];

                if ($chars === $prevChars && $styles === $prevStyles && $wide === $prevWide) {
                    continue;
                }

                // Byte mask of cells whose glyph byte or style id differs;
                // strspn over the leading/trailing NULs finds the changed
                // span at C speed.
                $mask = ($chars ^ $prevChars) | ($styles ^ $prevStyles);
                $first = strspn($mask, "\0");
                $last = $first === $this->width ? -1 : $this->width - 1 - strspn(strrev($mask), "\0");

                // Two different multibyte glyphs share the sentinel byte, so
                // the mask misses them — widen the span over the side tables.
                if ($wide !== $prevWide) {
                    foreach ($wide as $x => $glyph) {
                        if (($prevWide[$x] ?? null) !== $glyph) {
                            $first = min($first, $x);
                            $last = max($last, $x);
                        }
                    }
                    foreach ($prevWide as $x => $glyph) {
                        if (!isset($wide[$x])) {
                            $first = min($first, $x);
                            $last = max($last, $x);
                        }
                    }
                }
            } else {
                $first = 0;
                $last = $this->width - 1;
            }

            $x = $first;
            while ($x <= $last) {
                if ($sameSize
                    && $chars[$x] === $prevChars[$x]
                    && $styles[$x] === $prevStyles[$x]
                    && ($chars[$x] !== self::WIDE || ($wide[$x] ?? null) === ($prevWide[$x] ?? null))
                ) {
                    $x++;
                    continue;
                }

                $styleByte = $styles[$x];
                $startX = $x;
                $text = '';

                while ($x <= $last) {
                    if ($styles[$x] !== $styleByte) {
                        break;
                    }
                    if ($sameSize
                        && $chars[$x] === $prevChars[$x]
                        && $styles[$x] === $prevStyles[$x]
                        && ($chars[$x] !== self::WIDE || ($wide[$x] ?? null) === ($prevWide[$x] ?? null))
                    ) {
                        break;
                    }

                    $glyph = $chars[$x];
                    $text .= $glyph === self::WIDE ? $wide[$x] : $glyph;
                    $x++;
                }

                $runs[] = [
                    'x' => $startX,
                    'y' => $y,
                    'text' => $text,
                    'style' => self::$styleNames[ord($styleByte)],
                ];
            }
        }

        return $runs;
    }

    /**
     * Returns an independent copy of the current cell state. Cheap: the row
     * strings share storage copy-on-write until either buffer is next mutated.
     */
    public function snapshot(): self
    {
        return clone $this;
    }

    /** Drops side-table entries for cells just overwritten with ASCII glyphs. */
    private function clearWideSpan(int $row, int $at, int $length): void
    {
        for ($x = $at, $end = $at + $length; $x < $end; $x++) {
            unset($this->wide[$row][$x]);
        }

        if ($this->wide[$row] === []) {
            unset($this->wide[$row]);
        }
    }

    /** Interns a style name and returns its one-byte id. */
    private static function styleIdByte(string $style): string
    {
        $byte = self::$styleIds[$style] ?? null;
        if ($byte !== null) {
            return $byte;
        }

        $id = count(self::$styleNames);
        if ($id > 255) {
            throw new RuntimeException('Screen buffers support at most 255 distinct style names per process.');
        }

        self::$styleNames[$id] = $style;

        return self::$styleIds[$style] = chr($id);
    }
}
