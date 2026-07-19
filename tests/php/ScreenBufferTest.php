<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use InvalidArgumentException;
use PhelCliGui\ScreenBuffer;
use PHPUnit\Framework\TestCase;

final class ScreenBufferTest extends TestCase
{
    public function test_rejects_non_positive_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Screen buffer dimensions must be at least 1.');

        new ScreenBuffer(0, 4);
    }

    public function test_two_blank_buffers_have_no_diff(): void
    {
        $buffer = new ScreenBuffer(10, 3);

        self::assertSame([], $buffer->diff(new ScreenBuffer(10, 3)));
    }

    public function test_painted_text_is_a_single_run_against_blank(): void
    {
        $buffer = new ScreenBuffer(10, 3);
        $buffer->paint(2, 1, 'hi', 'info');

        self::assertSame(
            [['x' => 2, 'y' => 1, 'text' => 'hi', 'style' => 'info']],
            $buffer->diff(new ScreenBuffer(10, 3)),
        );
    }

    public function test_only_changed_cells_appear_in_diff(): void
    {
        $previous = new ScreenBuffer(10, 1);
        $previous->paint(0, 0, 'hello', null);
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(2, 0, 'X', null); // change one cell of "hello" -> "heXlo"

        self::assertSame(
            [['x' => 2, 'y' => 0, 'text' => 'X', 'style' => null]],
            $next->diff($baseline),
        );
    }

    public function test_style_boundary_splits_runs(): void
    {
        $buffer = new ScreenBuffer(4, 1);
        $buffer->paint(0, 0, 'ab', 'red');
        $buffer->paint(2, 0, 'cd', 'blue');

        self::assertSame(
            [
                ['x' => 0, 'y' => 0, 'text' => 'ab', 'style' => 'red'],
                ['x' => 2, 'y' => 0, 'text' => 'cd', 'style' => 'blue'],
            ],
            $buffer->diff(new ScreenBuffer(4, 1)),
        );
    }

    public function test_small_unchanged_gap_merges_into_one_run(): void
    {
        // "a_b" where the middle cell matches the previous frame: rewriting
        // the identical '_' costs one byte, a second run's cursor move costs
        // several — the gap is absorbed into a single run.
        $previous = new ScreenBuffer(3, 1);
        $previous->paint(1, 0, '_', null);
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(0, 0, 'a', null);
        $next->paint(2, 0, 'b', null);

        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'a_b', 'style' => null]],
            $next->diff($baseline),
        );
    }

    public function test_gap_wider_than_merge_window_splits_runs(): void
    {
        $previous = new ScreenBuffer(10, 1);
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(0, 0, 'a', null);
        $next->paint(6, 0, 'b', null); // 5 unchanged blanks between: too far

        self::assertSame(
            [
                ['x' => 0, 'y' => 0, 'text' => 'a', 'style' => null],
                ['x' => 6, 'y' => 0, 'text' => 'b', 'style' => null],
            ],
            $next->diff($baseline),
        );
    }

    public function test_gap_with_different_style_splits_runs(): void
    {
        // The unchanged cell between the runs is styled: repainting it with
        // the runs' unstyled state would change its colour, so no merge.
        $previous = new ScreenBuffer(3, 1);
        $previous->paint(1, 0, '_', 'dim');
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(0, 0, 'a', null);
        $next->paint(2, 0, 'b', null);

        self::assertSame(
            [
                ['x' => 0, 'y' => 0, 'text' => 'a', 'style' => null],
                ['x' => 2, 'y' => 0, 'text' => 'b', 'style' => null],
            ],
            $next->diff($baseline),
        );
    }

    public function test_trailing_unchanged_cells_are_never_absorbed(): void
    {
        // Gap merging only bridges toward another change; a run must not
        // drag unchanged cells behind the last changed one.
        $previous = new ScreenBuffer(5, 1);
        $previous->paint(0, 0, 'abcde', null);
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(0, 0, 'X', null);

        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'X', 'style' => null]],
            $next->diff($baseline),
        );
    }

    public function test_paint_clips_out_of_bounds_columns(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(2, 0, 'abc', null); // only 'a' fits at x=2

        self::assertSame(
            [['x' => 2, 'y' => 0, 'text' => 'a', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 1)),
        );
    }

    public function test_paint_clips_negative_columns(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(-1, 0, 'abc', null); // 'a' falls off the left edge

        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'bc', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 1)),
        );
    }

    public function test_paint_fully_out_of_bounds_is_noop(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(-5, 0, 'ab', null);
        $buffer->paint(3, 0, 'ab', null);
        $buffer->paint(0, 0, '', 'red');

        self::assertSame([], $buffer->diff(new ScreenBuffer(3, 1)));
    }

    public function test_paint_normalizes_empty_style_to_unstyled(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(0, 0, 'a', '');
        $buffer->paint(1, 0, 'b', null);

        // '' and null are the same unstyled state, so the cells form one run.
        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'ab', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 1)),
        );
    }

    public function test_paint_stores_one_multibyte_glyph_per_cell(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(0, 0, '─│x', null);
        $buffer->paint(1, 0, '║', null); // overwrite the middle cell

        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => '─║x', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 1)),
        );
    }

    public function test_style_change_alone_marks_cell_changed(): void
    {
        $previous = new ScreenBuffer(3, 1);
        $previous->paint(0, 0, 'abc', 'red');
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(1, 0, 'b', 'blue'); // same glyph, different style

        self::assertSame(
            [['x' => 1, 'y' => 0, 'text' => 'b', 'style' => 'blue']],
            $next->diff($baseline),
        );
    }

    public function test_ascii_overwrite_clears_multibyte_cells(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(0, 0, '─│x', null);
        $buffer->paint(0, 0, 'ab', null); // ASCII fast path over two wide cells

        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'abx', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 1)),
        );
    }

    public function test_diff_detects_multibyte_glyph_swapped_for_another(): void
    {
        // Both frames store a sentinel byte for the cell, so the byte-level
        // row comparison alone cannot see this change — the side table must.
        $previous = new ScreenBuffer(5, 1);
        $previous->paint(0, 0, 'a─b─c', null);
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(3, 0, '║', null); // swap the second box glyph only

        self::assertSame(
            [['x' => 3, 'y' => 0, 'text' => '║', 'style' => null]],
            $next->diff($baseline),
        );
    }

    public function test_diff_detects_multibyte_glyph_replaced_and_removed(): void
    {
        $previous = new ScreenBuffer(4, 1);
        $previous->paint(0, 0, '─x─x', null);
        $baseline = $previous->snapshot();

        $next = $previous->snapshot();
        $next->paint(0, 0, 'y', null);   // wide -> ASCII
        $next->clearRow(0);              // wide entries dropped entirely
        $next->paint(1, 0, 'x', null);
        $next->paint(3, 0, 'x', null);

        // Cells 0 and 2 changed (wide glyphs blanked); the unchanged 'x'
        // between them is absorbed by gap merging, the trailing 'x' is not.
        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => ' x ', 'style' => null]],
            $next->diff($baseline),
        );
    }

    public function test_paint_ignores_out_of_range_rows(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(0, 5, 'x', null);
        $buffer->paint(0, -1, 'y', null);

        self::assertSame([], $buffer->diff(new ScreenBuffer(3, 1)));
    }

    public function test_clear_resets_every_cell(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(0, 0, 'abc', 'red');
        $buffer->clear();

        self::assertSame([], $buffer->diff(new ScreenBuffer(3, 1)));
    }

    public function test_clear_row_blanks_only_one_row(): void
    {
        $buffer = new ScreenBuffer(3, 2);
        $buffer->paint(0, 0, 'abc', null);
        $buffer->paint(0, 1, 'xyz', null);
        $buffer->clearRow(0);

        // Row 0 blanked, row 1 intact.
        self::assertSame(
            [['x' => 0, 'y' => 1, 'text' => 'xyz', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 2)),
        );
    }

    public function test_clear_row_ignores_out_of_range(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(0, 0, 'abc', null);
        $buffer->clearRow(9);
        $buffer->clearRow(-1);

        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'abc', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 1)),
        );
    }

    public function test_snapshot_is_independent(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(0, 0, 'abc', null);
        $snapshot = $buffer->snapshot();

        $buffer->clear(); // mutate the original after snapshotting

        // The snapshot still holds the painted state.
        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'abc', 'style' => null]],
            $snapshot->diff(new ScreenBuffer(3, 1)),
        );
    }

    public function test_size_mismatch_repaints_every_changed_cell(): void
    {
        $buffer = new ScreenBuffer(2, 1);
        $buffer->paint(0, 0, 'ab', null);

        // Previous frame has different dimensions: treat all as changed.
        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => 'ab', 'style' => null]],
            $buffer->diff(new ScreenBuffer(5, 5)),
        );
    }
}
