<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\Ansi;
use PHPUnit\Framework\TestCase;

final class AnsiTest extends TestCase
{
    public function test_move_to_is_one_indexed_on_both_axes(): void
    {
        // CUP counts from 1, so the origin is "\e[1;1H" and each 0-indexed
        // coordinate is written one higher.
        self::assertSame("\033[1;1H", Ansi::moveTo(0, 0));
        self::assertSame("\033[1;2H", Ansi::moveTo(1, 0));
        self::assertSame("\033[3;6H", Ansi::moveTo(5, 2));
    }

    public function test_distinct_columns_never_share_a_sequence(): void
    {
        self::assertNotSame(Ansi::moveTo(0, 0), Ansi::moveTo(1, 0));
    }

    public function test_move_right_emits_cuf(): void
    {
        self::assertSame("\033[7C", Ansi::moveRight(7));
    }
}
