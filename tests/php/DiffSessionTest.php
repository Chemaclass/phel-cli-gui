<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\DiffSession;
use PHPUnit\Framework\TestCase;

final class DiffSessionTest extends TestCase
{
    public function test_starts_inactive_and_collects_no_runs(): void
    {
        $diff = new DiffSession();

        self::assertFalse($diff->isActive());
        self::assertSame([], $diff->collectRuns());
    }

    public function test_inactive_paint_and_clear_are_noops(): void
    {
        $diff = new DiffSession();
        $diff->paint(0, 0, 'x', null); // no session: dropped
        $diff->clear();

        self::assertSame([], $diff->collectRuns());
    }

    public function test_begin_activates(): void
    {
        $diff = new DiffSession();
        $diff->begin(10, 3);

        self::assertTrue($diff->isActive());
    }

    public function test_collect_runs_returns_painted_cells_against_blank(): void
    {
        $diff = new DiffSession();
        $diff->begin(10, 1);
        $diff->paint(2, 0, 'hi', 'info');

        self::assertSame(
            [['x' => 2, 'y' => 0, 'text' => 'hi', 'style' => 'info']],
            $diff->collectRuns(),
        );
    }

    public function test_collect_runs_advances_baseline_so_unchanged_frame_is_empty(): void
    {
        $diff = new DiffSession();
        $diff->begin(10, 1);
        $diff->paint(0, 0, 'abc', null);
        $diff->collectRuns(); // first frame: baseline now holds "abc"

        $diff->paint(0, 0, 'abc', null); // same content again

        self::assertSame([], $diff->collectRuns());
    }

    public function test_clear_then_collect_blanks_the_previous_frame(): void
    {
        $diff = new DiffSession();
        $diff->begin(3, 1);
        $diff->paint(0, 0, 'abc', null);
        $diff->collectRuns();

        $diff->clear(); // back-buffer now blank; previous frame had "abc"

        self::assertSame(
            [['x' => 0, 'y' => 0, 'text' => '   ', 'style' => null]],
            $diff->collectRuns(),
        );
    }

    public function test_clear_line_blanks_only_that_row(): void
    {
        $diff = new DiffSession();
        $diff->begin(5, 2);
        $diff->paint(0, 0, 'aaaaa', null);
        $diff->paint(0, 1, 'bbbbb', null);
        $diff->collectRuns();

        $diff->clearLine(1); // row 1 -> blank; row 0 stays

        self::assertSame(
            [['x' => 0, 'y' => 1, 'text' => '     ', 'style' => null]],
            $diff->collectRuns(),
        );
    }

    public function test_end_deactivates(): void
    {
        $diff = new DiffSession();
        $diff->begin(10, 1);
        $diff->end();

        self::assertFalse($diff->isActive());
    }
}
