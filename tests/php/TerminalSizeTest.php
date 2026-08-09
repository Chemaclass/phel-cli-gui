<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\TerminalSize;
use PHPUnit\Framework\TestCase;

final class TerminalSizeTest extends TestCase
{
    protected function tearDown(): void
    {
        TerminalSize::override(null, null);
    }

    public function test_override_wins_over_every_other_source(): void
    {
        TerminalSize::override(33, 9);

        self::assertSame([33, 9], TerminalSize::get());
        self::assertSame(33, TerminalSize::width());
        self::assertSame(9, TerminalSize::height());
    }

    public function test_clearing_the_override_resumes_measuring(): void
    {
        TerminalSize::override(33, 9);
        TerminalSize::override(null, null);

        $size = TerminalSize::get();
        self::assertGreaterThan(0, $size[0]);
        self::assertGreaterThan(0, $size[1]);
    }

    public function test_a_half_specified_override_is_no_override(): void
    {
        TerminalSize::override(33, null);

        self::assertNotSame(33, TerminalSize::get()[0]);
    }

    public function test_invalidate_does_not_disturb_an_override(): void
    {
        TerminalSize::override(33, 9);
        TerminalSize::invalidate();

        self::assertSame([33, 9], TerminalSize::get());
    }

    public function test_a_stale_columns_variable_never_outranks_a_measurement(): void
    {
        // Shells export COLUMNS/LINES and update them on resize for themselves
        // only, so a child process inherits a value frozen at launch. Symfony's
        // Terminal lets that outrank its probe; a measured terminal must not.
        if (!function_exists('posix_isatty') || !@posix_isatty(STDIN)) {
            self::markTestSkipped('no controlling terminal to measure against');
        }

        TerminalSize::invalidate();
        $measured = TerminalSize::get();

        putenv('COLUMNS=' . ($measured[0] + 17));
        try {
            TerminalSize::invalidate();
            self::assertSame($measured, TerminalSize::get());
        } finally {
            putenv('COLUMNS');
        }
    }

    public function test_columns_is_the_fallback_when_there_is_no_terminal(): void
    {
        // Piped output or CI: nothing to measure, so the environment (and then
        // Symfony's defaults) is all that is left.
        if (function_exists('posix_isatty') && @posix_isatty(STDIN)) {
            self::markTestSkipped('a terminal is available, so it is measured instead');
        }

        putenv('COLUMNS=137');
        try {
            TerminalSize::invalidate();
            self::assertSame(137, TerminalSize::width());
        } finally {
            putenv('COLUMNS');
            TerminalSize::invalidate();
        }
    }
}
