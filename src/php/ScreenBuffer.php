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

    /**
     * Longest run of unchanged same-style cells absorbed into a run instead
     * of splitting it: repositioning the cursor costs ~5-8 bytes of escape,
     * so rewriting up to this many identical cells is the cheaper output.
     */
    private const GAP_MERGE = 4;

    /** Rows fragmented into at least this many runs try a full-span rewrite. */
    private const ROW_REWRITE_MIN_RUNS = 4;

    /** Approximate byte cost of one cursor-move escape between runs. */
    private const MOVE_COST = 7;

    /** @var array<int, string> one glyph byte per cell; self::WIDE = see $wide */
    private array $rowChars;

    /** @var array<int, string> one interned style-id byte per cell */
    private array $rowStyles;

    /** @var array<int, array<int, string>> multibyte glyphs, keyed [row][column] */
    private array $wide = [];

    /** A full row of blank glyphs, reused by clear()/clearRow(). */
    private readonly string $blankChars;

    /** A full row of unstyled ids, reused by clear()/clearRow(). */
    private readonly string $blankStyles;

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

        $this->blankChars = str_repeat(' ', $width);
        $this->blankStyles = str_repeat(self::UNSTYLED, $width);

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
        $this->rowChars = array_fill(0, $this->height, $this->blankChars);
        $this->rowStyles = array_fill(0, $this->height, $this->blankStyles);
        $this->wide = [];
    }

    /** Resets one row to blank, unstyled spaces. Out-of-range rows are ignored. */
    public function clearRow(int $row): void
    {
        if ($row < 0 || $row >= $this->height) {
            return;
        }

        $this->rowChars[$row] = $this->blankChars;
        $this->rowStyles[$row] = $this->blankStyles;
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
        if (Text::isPrintableAscii($text)) {
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
     * (a) changed versus `previous` and (b) share one style. Style boundaries
     * break runs; so do unchanged gaps, except that a gap of at most
     * GAP_MERGE unchanged same-style cells is absorbed (rewritten verbatim)
     * because that costs fewer bytes than repositioning the cursor.
     *
     * When the buffers differ in size every cell is treated as changed.
     *
     * @return list<array{x:int,y:int,text:string,style:?string}>
     */
    public function diff(self $previous): array
    {
        $sameSize = $previous->width === $this->width && $previous->height === $this->height;
        $runs = [];

        // On a size mismatch every cell counts as changed, which the loop below
        // expresses as an all-ones mask — one allocation for the whole diff.
        $allChanged = $sameSize ? '' : str_repeat("\xff", $this->width);

        for ($y = 0; $y < $this->height; $y++) {
            $chars = $this->rowChars[$y];
            $styles = $this->rowStyles[$y];
            $wide = $this->wide[$y] ?? [];

            if ($sameSize) {
                $prevChars = $previous->rowChars[$y];
                $prevStyles = $previous->rowStyles[$y];
                $prevWide = $previous->wide[$y] ?? [];

                if ($chars === $prevChars && $styles === $prevStyles && $wide === $prevWide) {
                    continue;
                }

                // Byte mask of the row: NUL where the cell is unchanged,
                // non-NUL where its glyph byte or style id differs. Two
                // *different* multibyte glyphs share the sentinel byte, so the
                // XOR misses them — those cells are marked by hand, after which
                // the mask is exact and every scan below is a C-level
                // strspn/strcspn rather than a per-cell PHP loop. (A cell whose
                // wide glyph was dropped differs in its glyph byte, so the XOR
                // already caught it.)
                $mask = ($chars ^ $prevChars) | ($styles ^ $prevStyles);
                if ($wide !== $prevWide) {
                    foreach ($wide as $x => $glyph) {
                        if (($prevWide[$x] ?? null) !== $glyph) {
                            $mask[$x] = "\xff";
                        }
                    }
                }

                $first = strspn($mask, "\0");
                if ($first === $this->width) {
                    continue; // only the side tables' key order differed
                }

                $scanEnd = strlen(rtrim($mask, "\0"));
            } else {
                $mask = $allChanged;
                $first = 0;
                $scanEnd = $this->width;
            }

            $x = $first;
            $rowRuns = [];
            $rowStart = -1;
            $rowEnd = $first;
            $runCells = 0;

            while ($x < $scanEnd) {
                $x += strspn($mask, "\0", $x, $scanEnd - $x);
                if ($x >= $scanEnd) {
                    break;
                }

                // A run never crosses a style boundary, so the same-style span
                // starting here caps how far it can reach.
                $styleByte = $styles[$x];
                $styleEnd = $x + strspn($styles, $styleByte, $x, $scanEnd - $x);
                $startX = $x;

                while (true) {
                    $x += strcspn($mask, "\0", $x, $styleEnd - $x);
                    $end = $x;
                    if ($x >= $styleEnd) {
                        break;
                    }

                    // Unchanged cells ahead: absorb up to GAP_MERGE of them when
                    // another same-style change follows: rewriting a short
                    // identical gap beats the cursor escape a split would cost.
                    $gap = strspn($mask, "\0", $x, $styleEnd - $x);
                    if ($gap > self::GAP_MERGE || $x + $gap >= $styleEnd) {
                        break;
                    }
                    $x += $gap;
                }

                if ($rowStart === -1) {
                    $rowStart = $startX;
                }
                $rowEnd = $end;
                $runCells += $end - $startX;

                $rowRuns[] = [
                    'x' => $startX,
                    'y' => $y,
                    'text' => self::spanText($chars, $wide, $startX, $end - $startX),
                    'style' => self::$styleNames[ord($styleByte)],
                ];
            }

            if ($rowStart !== -1 && count($rowRuns) >= self::ROW_REWRITE_MIN_RUNS) {
                $rowRuns = $this->maybeRewriteRowSpan($y, $rowRuns, $rowStart, $rowEnd, $runCells);
            }

            foreach ($rowRuns as $rowRun) {
                $runs[] = $rowRun;
            }
        }

        return $runs;
    }

    /**
     * Renders cells [from, from + length) of a packed row as text, expanding
     * sentinel bytes back into their multibyte glyphs. Rows without wide
     * glyphs — the common case — are one substr().
     *
     * @param array<int, string> $wide
     */
    private static function spanText(string $chars, array $wide, int $from, int $length): string
    {
        $text = substr($chars, $from, $length);
        if ($wide === []) {
            return $text;
        }

        $at = strpos($text, self::WIDE);
        if ($at === false) {
            return $text;
        }

        $out = '';
        $cut = 0;
        do {
            $out .= substr($text, $cut, $at - $cut) . $wide[$from + $at];
            $cut = $at + 1;
            $at = strpos($text, self::WIDE, $cut);
        } while ($at !== false);

        return $out . substr($text, $cut);
    }

    /**
     * A row fragmented into many runs can cost more in cursor escapes than
     * rewriting its whole changed span once. Rebuilds the span [start, end)
     * split only at style boundaries — unchanged cells repaint verbatim, so
     * nothing changes visually — and returns whichever variant is fewer
     * output bytes.
     *
     * @param non-empty-list<array{x:int,y:int,text:string,style:?string}> $rowRuns
     *
     * @return non-empty-list<array{x:int,y:int,text:string,style:?string}>
     */
    private function maybeRewriteRowSpan(int $y, array $rowRuns, int $start, int $end, int $runCells): array
    {
        $chars = $this->rowChars[$y];
        $styles = $this->rowStyles[$y];
        $wide = $this->wide[$y] ?? [];

        if ($start >= $end) {
            return $rowRuns;
        }

        $segments = [];
        $x = $start;

        do {
            $styleByte = $styles[$x];
            $length = strspn($styles, $styleByte, $x, $end - $x);

            $segments[] = [
                'x' => $x,
                'y' => $y,
                'text' => self::spanText($chars, $wide, $x, $length),
                'style' => self::$styleNames[ord($styleByte)],
            ];

            $x += $length;
        } while ($x < $end);

        $extraCells = ($end - $start) - $runCells;
        $movesSaved = count($rowRuns) - count($segments);

        return $extraCells <= $movesSaved * self::MOVE_COST ? $segments : $rowRuns;
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
