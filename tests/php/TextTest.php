<?php

declare(strict_types=1);

namespace PhelCliGui\Tests;

use PhelCliGui\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function test_first_char_returns_first_character_of_string(): void
    {
        self::assertSame('h', Text::firstChar('hello', '?'));
    }

    public function test_first_char_returns_fallback_for_null(): void
    {
        self::assertSame('*', Text::firstChar(null, '*'));
    }

    public function test_first_char_returns_fallback_for_empty_string(): void
    {
        self::assertSame('*', Text::firstChar('', '*'));
    }

    public function test_first_char_handles_multibyte_input(): void
    {
        self::assertSame('─', Text::firstChar('─────', '?'));
    }

    public function test_display_width_returns_zero_for_empty_string(): void
    {
        self::assertSame(0, Text::displayWidth(''));
    }

    public function test_display_width_counts_ascii_characters(): void
    {
        self::assertSame(5, Text::displayWidth('hello'));
    }

    public function test_display_width_handles_multibyte_characters(): void
    {
        self::assertSame(5, Text::displayWidth('─────'));
    }
}
