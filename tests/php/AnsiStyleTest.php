<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\AnsiStyle;
use PHPUnit\Framework\TestCase;

final class AnsiStyleTest extends TestCase
{
    public function test_apply_wraps_text_in_the_sgr_sequence(): void
    {
        $style = new AnsiStyle('38;5;196');

        self::assertSame("\033[38;5;196mboom\033[0m", $style->apply('boom'));
    }

    public function test_apply_with_empty_sgr_returns_text_unchanged(): void
    {
        $style = new AnsiStyle('');

        self::assertSame('plain', $style->apply('plain'));
    }

    public function test_formatter_interface_setters_never_mutate_the_sgr(): void
    {
        // The style is defined entirely by its SGR string; the interface's
        // mutators are contractual no-ops and must not change the output.
        $style = new AnsiStyle('38;5;196');
        $style->setForeground('blue');
        $style->setBackground('white');
        $style->setOption('bold');
        $style->unsetOption('bold');
        $style->setOptions(['underscore']);

        self::assertSame("\033[38;5;196mboom\033[0m", $style->apply('boom'));
    }
}
