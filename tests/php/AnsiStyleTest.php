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
}
