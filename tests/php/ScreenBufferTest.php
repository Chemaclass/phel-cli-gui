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

    public function test_unchanged_gap_breaks_a_run(): void
    {
        // "a_b" where the middle cell matches the previous frame: two runs.
        $previous = new ScreenBuffer(3, 1);
        $previous->paint(1, 0, '_', null);
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

    public function test_paint_clips_out_of_bounds_columns(): void
    {
        $buffer = new ScreenBuffer(3, 1);
        $buffer->paint(2, 0, 'abc', null); // only 'a' fits at x=2

        self::assertSame(
            [['x' => 2, 'y' => 0, 'text' => 'a', 'style' => null]],
            $buffer->diff(new ScreenBuffer(3, 1)),
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
